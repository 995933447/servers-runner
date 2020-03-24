<?php
namespace Bobby\ServersRunner;

use Bobby\MultiProcesses\Process;
use Bobby\MultiProcesses\Quit;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\Servers\EventHandler;
use Bobby\ServersRunner\Utils\EventRegistrarTrait;
use Bobby\ServersRunner\Utils\ResetStdTrait;

class ServersRunner
{
    use EventRegistrarTrait;
    use ResetStdTrait;

    const START_EVENT = 'start';

    const STOP_EVENT = 'stop';

    protected $allowListenEvents = [self::START_EVENT, self::STOP_EVENT];

    protected $eventLoop;

    protected $serverWorkers = [];

    protected $config;

    protected $eventHandler;

    protected $asyncListenSignals = false;

    protected $isRunning = false;

    public function __construct(ServersRunnerConfig $config)
    {
        $this->config = $config;
        $this->eventHandler = new EventHandler();
    }

    public function addServerWorker(ServerContract $server, ServerWorkerConfig $config)
    {
        if (!is_null($this->config->stdinFile) && is_null($config->stdinFile)) {
            $config->setStdinFile($this->config->stdinFile);
        }

        if (!is_null($this->config->stdoutFile) && is_null($config->stdoutFile)) {
            $config->setStdoutFile($this->config->stdoutFile);
        }

        if (!is_null($this->config->stderrFile) && is_null($config->stderrFile)) {
            $config->setStderrFile($this->config->stderrFile);
        }

        $this->serverWorkers[] = new ServerWorker($server, $config);
    }

    public function run()
    {
        $this->isRunning = true;

        if ($this->config->daemonize) {
            (new Process(function () {
                $this->work();
            }, true))->run();

            Quit::normalQuit();
        } else {
            $this->work();
        }
    }

    protected function work()
    {
        $this->runServerWorkers();

        $this->readyMonitorServerWorkers();

        $this->savePidFile();

        $this->resetStd();

        $this->emitOnEvent(static::START_EVENT);

        $this->monitorServerWorkers();
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

    protected function savePidFile()
    {
        if (trim($this->config->pidFile)) {
            if (file_exists($this->config->pidFile)) {
                if (!$fp = fopen($this->config->pidFile, 'r')) {
                    throw new \RuntimeException("Pid file:{$this->config->pidFile} open failed.");
                }

                if (!flock($fp, LOCK_EX | LOCK_NB)) {
                    throw new \RuntimeException("Pid file:{$this->config->pidFile} has been locked!");
                }

                $fp = fopen($this->config->pidFile, 'w+');

                fwrite($fp, posix_getpid());
            } else {
                if (file_put_contents($this->config->pidFile, posix_getpid()) === false) {
                    throw new \RuntimeException("Pid file:{$this->config->pidFile} save failed.");
                }
            }
        }
    }

    protected function runServerWorkers()
    {
        foreach ($this->serverWorkers as $serverWorker) {
            $serverWorker->run();
        }
    }

    protected function stop()
    {
        $this->isRunning = false;
        $this->emitOnEvent(static::STOP_EVENT);
    }

    protected function readyMonitorServerWorkers()
    {
        $forceExitSignals = [SIGINT, SIGTERM];
        foreach ($forceExitSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    $serverWorker->exit();
                }

                $this->stop();
            });
        }

        $gracefulExitSignals = [SIGQUIT];
        foreach ($gracefulExitSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    $serverWorker->exit(true);
                }

                $this->stop();
            });
        }

        $restartSignals = [SIGUSR1];
        foreach ($restartSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    $serverWorker->exit();
                }

                $this->runServerWorkers();
            });
        }

        $reloadSignals = [SIGUSR2];
        foreach ($reloadSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    foreach ($serverWorker->getProcesses() as $process) {
                        posix_kill($process->getPid(), SIGQUIT);
                    }
                }
            });
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals() || pcntl_async_signals(true);
            $this->asyncListenSignals = true;
        }
    }

    protected function monitorServerWorkers()
    {
        while (1) {
            $this->dispatchSignals();

            // php7.2使用pcntl_wait($status)阻塞并不会被SIGKILL以外的被信号中断,这是个bug
            $pid = pcntl_wait($status, WNOHANG);

            $this->dispatchSignals();

            if (!$this->isRunning) {
                break;
            }

            if ($pid > 0) {
                foreach ($this->serverWorkers as $serverWorker) {
                    if ($serverWorker->isForkedWorkerProcess($pid)) {
                        $serverWorker->forgetWorkerProcess($pid);
                        $serverWorker->forkAndWorkProcess();
                        break;
                    }
                }
            } else {
                sleep(1);
            }
        }
    }

    protected function dispatchSignals()
    {
        if (!$this->asyncListenSignals) {
            pcntl_signal_dispatch();
        }
    }
}