<?php
namespace Bobby\ServersRunner;

use Bobby\ServersRunner\Utils\MagicGetterTrait;

class ServersRunnerConfig
{
    use MagicGetterTrait;

    protected $daemonize = false;

    protected $pidFile = '';

    public function setDaemonize(bool $daemonize)
    {
        $this->daemonize = $daemonize;
    }

    public function setPidFile(string $pidFile)
    {
        $this->pidFile = $pidFile;
    }
}