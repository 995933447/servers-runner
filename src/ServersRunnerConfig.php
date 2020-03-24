<?php
namespace Bobby\ServersRunner;

use Bobby\ServersRunner\Utils\MagicGetterTrait;

class ServersRunnerConfig
{
    use MagicGetterTrait;

    protected $daemonize = false;

    protected $pidFile = '';

    protected $stdoutFile;

    protected $stderrFile;

    protected $stdinFile;

    public function setDaemonize(bool $daemonize)
    {
        $this->daemonize = $daemonize;

        if ($daemonize) {
            if (is_null($this->stdoutFile)) {
                $this->setStdoutFile('/dev/null');
            }

            if (is_null($this->stderrFile)) {
                $this->setStderrFile('/dev/null');
            }

            if (is_null($this->stdinFile)) {
                $this->setStdinFile('/dev/null');
            }
        }
    }

    public function setPidFile(string $pidFile)
    {
        $this->pidFile = $pidFile;
    }

    public function setStdoutFile(string $stdoutFile)
    {
        $this->stdoutFile = $stdoutFile;
    }

    public function setStderrFile(string $stderrFile)
    {
        $this->stderrFile = $stderrFile;
    }

    public function setStdinFile(string $stdinFile)
    {
        $this->stdinFile = $stdinFile;
    }
}