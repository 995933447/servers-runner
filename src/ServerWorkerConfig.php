<?php
namespace Bobby\ServersRunner;

use Bobby\ServersRunner\Utils\MagicGetterTrait;

class ServerWorkerConfig
{
    use MagicGetterTrait;

    protected $workerNum = 1;

    protected $name;

    protected $user;

    protected $group;

    protected $stdoutFile;

    protected $stderrFile;

    protected $stdinFile;

    public function setWorkerNum(int $workerNum)
    {
        if ($workerNum < 0) {
            throw new \InvalidArgumentException("First argument must be more than 0.");
        }
        $this->workerNum = $workerNum;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function setUser(string $user)
    {
        $this->user = $user;
    }

    public function setGroup(string $group)
    {
        $this->group = $group;
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