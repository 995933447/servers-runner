<?php
namespace Bobby\ServersRunner;

use Bobby\ServersRunner\Utils\MagicGetterTrait;

class ServerWorkerConfig
{
    use MagicGetterTrait;

    protected $workerNum;

    protected $name;

    protected $user;

    protected $group;

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
}