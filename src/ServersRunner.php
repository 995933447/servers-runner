<?php
namespace Bobby\ServersRunner;

use Bobby\MultiProcesses\Process;
use Bobby\MultiProcesses\Quit;
use Bobby\Servers\Contracts\ServerContract;
use Bobby\Servers\EventHandler;
use InvalidArgumentException;

class ServersRunner
{
    const START_EVENT = 'start';

    const WORKER_START_EVENT = 'worker_start';

    const WORKER_ERROR_EVENT = 'worker_error';

    const WORKER_STOP_EVENT = 'worker_stop';

    const STOP_EVENT = 'stop';

    protected $allowListenEvents = [self::START_EVENT, self::WORKER_START_EVENT, self::WORKER_ERROR_EVENT, self::WORKER_STOP_EVENT, self::STOP_EVENT];

    protected $eventLoop;

    protected $serverWorkers = [];

    protected $config;

    protected $eventHandler;

    protected $asyncListenSignals = false;

    public function __construct(ServersRunnerConfig $config)
    {
        $this->config = $config;
        $this->eventHandler = new EventHandler();
    }

    public function addServerWorker(ServerContract $server, ServerWorkerConfig $config)
    {
        $this->serverWorkers[] = new ServerWorker($server, $config);
    }

    public function on(string $event, callable $callback)
    {
        if (in_array($event, $this->allowListenEvents)) {
            $this->eventHandler->register($event, $callback);
        } else {
            throw new InvalidArgumentException("Event $event now allow set.");
        }
    }

    public function run()
    {
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
        $this->readyWork();
        $this->startWork();
    }

    protected function readyWork()
    {
        $forceExitSignals = [SIGINT, SIGTERM];
        foreach ($forceExitSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    $serverWorker->exit();
                }

                Quit::normalQuit();
            });
        }

        $gracefulExitSignals = [SIGQUIT];
        foreach ($gracefulExitSignals as $signalNo) {
            pcntl_signal($signalNo, function () {
                foreach ($this->serverWorkers as $serverWorker) {
                    $serverWorker->exit(true);
                }

                Quit::normalQuit();
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
                    $serverWorker->exit(true);
                }
            });
        }

        if (function_exists('pcntl_async_signals')) {
            if (!pcntl_async_signals()) {
                $this->asyncListenSignals = pcntl_async_signals(true);
            } else {
                $this->asyncListenSignals = true;
            }
        }
    }

    protected function startWork()
    {
        $this->runServerWorkers();

        $this->savePidFile();

        $this->resetStd();

        $this->monitorServerWorkers();
    }

    protected function resetStd()
    {
        if ($this->config->daemonize) {
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
        }
    }

    protected function savePidFile()
    {
        if (!empty($this->config->pidFile)) {
            if (file_exists($this->config->pidFile)) {
                if (!$fp = fopen($this->config->pidFile, 'r')) {
                    throw new \RuntimeException("Pid file:{$this->config->pidFile} open failed.");
                }

                if (!flock($fp, LOCK_EX | LOCK_NB)) {
                    throw new \RuntimeException("Pid file:{$this->config->pidFile} has been locked!");
                }

                $fp = fopen($this->config->pidFile, 'w+');

                fwrite($fp, posix_getpid());
            }
        }
    }

    protected function runServerWorkers()
    {
        foreach ($this->serverWorkers as $serverWorker) {
            $serverWorker->run();
        }
    }

    protected function monitorServerWorkers()
    {
        while (1) {
            $this->dispatchSignals();

            $pid = pcntl_wait($status);

            $this->dispatchSignals();

            if ($pid > 0) {
                foreach ($this->serverWorkers as $serverWorker) {
                    if ($serverWorker->isForkedWorkerProcess($pid)) {
                        $serverWorker->forgetWorkerProcess($pid);
                        $serverWorker->forkAndWorkProcess();
                        break;
                    }
                }
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