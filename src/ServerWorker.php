<?php
namespace Bobby\ServersRunner;

use Bobby\MultiProcesses\Process;
use Bobby\MultiProcesses\Quit;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\StreamEventLoop\LoopContract;
use function Clue\StreamFilter\register;

class ServerWorker
{
    protected $server;

    protected $config;

    protected $processes = [];

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

            throw new \RuntimeException("Worker process name:{$process->getRealName()} pid:{$process->getPid()} event-loop exit.");
        });

        if (!empty($this->config->name)) {
            $process->setName($this->config->name);
        }

        $pid = $process->run();

        $this->processes[$pid] = $process;
    }

    protected function startServer()
    {
        $this->server->getEventLoop()->installSignal(SIGQUIT, function () {
            $this->server->pause();

            $graceQuit = function () {
                if ($this->server->getEventLoop()->isEmptyReadyWriteStream()) {
                    $this->server->getEventLoop()->stop();
                    Quit::normalQuit();
                }
            };

            $graceQuit();

            $this->server->getEventLoop()->addTick(2, $graceQuit);
        });

        $this->server->listen();
        $this->server->getEventLoop()->poll();
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