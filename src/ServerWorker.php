<?php
namespace Bobby\ServersRunner;

use Bobby\MultiProcesses\Process;
use Bobby\MultiProcesses\Quit;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\ServersRunner\Utils\EventRegistrarTrait;
use Bobby\ServersRunner\Utils\ResetStdTrait;

class ServerWorker
{
    use EventRegistrarTrait;
    use ResetStdTrait;

    const WORKER_START_EVENT = 'worker_start';

    const WORKER_STOP_EVENT = 'worker_stop';

    protected $server;

    protected $config;

    protected $processes = [];

    protected $allowListenEvents = [self::WORKER_START_EVENT, self::WORKER_STOP_EVENT];

    public function __construct(ServerContract $server, ServerWorkerConfig $config)
    {
        $this->server = $server;
        $this->config = $config;
    }

    public function getServer(): ServerContract
    {
        return $this->server;
    }

    public function getConfig(): ServerWorkerConfig
    {
       return $this->config;
    }

    public function getProcesses(): array
    {
        return $this->processes;
    }

    public function isForkedWorkerProcess(int $pid)
    {
        return isset($this->processes[$pid]);
    }

    public function forgetWorkerProcess(int $pid)
    {
        unset($this->processes[$pid]);
    }

    public function killWorkerProcess(int $pid, bool $isGraceful = false)
    {
        if (posix_kill($pid, 0)) {
            if ($isGraceful) {
                $signalNo = SIGQUIT;
            } else {
                $signalNo = SIGKILL;
            }
            posix_kill($pid, $signalNo);
        }

        $this->forgetWorkerProcess($pid);
    }

    public function exit(bool $isGraceful = false)
    {
        foreach ($this->processes as $process) {
            $this->killWorkerProcess($process->getPid(), $isGraceful);
        }
    }

    public function run()
    {
        for ($i = 0; $i < $this->config->workerNum; $i++) {
            $this->forkAndWorkProcess();
        }
    }

    public function forkAndWorkProcess()
    {
        $process = new Process(function (Process $process) {
            $this->setWorkerProcessUid();

            $this->setWorkerProcessGid();

            $this->startServer();

            $this->emitOnEvent(static::WORKER_STOP_EVENT);
        });

        if (trim($this->config->name)) {
            $process->setName($this->config->name);
        }

        $pid = $process->run();

        $this->processes[$pid] = $process;
    }

    protected function startServer()
    {
        $this->server->getEventLoop()->installSignal(SIGQUIT, function () {
            $this->server->pause();

            $gracefulQuit = function () {
                if ($this->server->getEventLoop()->isEmptyReadyWriteStream()) {
                    $this->server->getEventLoop()->stop();

                    $this->emitOnEvent(static::WORKER_STOP_EVENT);

                    Quit::normalQuit();
                }
            };

            $gracefulQuit();

            $this->server->getEventLoop()->addTick(2, $gracefulQuit);
        });

        $this->server->listen();

        $this->resetStd();

        $this->emitOnEvent(static::WORKER_START_EVENT);

        $this->server->getEventLoop()->poll();
    }

    protected function resetStd()
    {
        if (!is_null($this->config->stdinFile)) {
            $this->resetStdin($this->config->stdinFile);
        }

        if (!is_null($this->config->stdoutFile)) {
            $this->resetStdout($this->config->stdoutFile);
        }

        if (!is_null($this->config->stderrFile)) {
            $this->resetStderr($this->config->stderrFile);
        }
    }

    protected function setWorkerProcessGid()
    {
        if (!is_null($this->config->group)) {
            if (empty($groupInfo = posix_getgrnam($this->config->group))) {
                throw new \RuntimeException("Group {$this->config->group} not exists,");
            }

            $groupId = $groupInfo['gid'];

            if ($groupId != posix_getgid() && !posix_setgid($groupId)) {
                throw new \RuntimeException("Change gid failed.");
            }
        }
    }

    protected function setWorkerProcessUid()
    {
        if (!is_null($this->config->user)) {
            if (empty($userInfo = posix_getpwnam($this->config->user))) {
                throw new \RuntimeException("User {$this->config->user} not exists.");
            }

            $uid = $userInfo['uid'];

            if ($uid != posix_getuid() && !posix_seteuid($uid)) {
                throw new \RuntimeException("Change uid failed.");
            }
        }
    }
}