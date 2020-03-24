<?php
namespace Bobby\ServersRunner\Utils;

use Bobby\Servers\EventHandler;
use InvalidArgumentException;

trait EventRegistrarTrait
{
    protected $eventHandler;

    public function getEventHandler(): EventHandler
    {
        if (is_null($this->eventHandler)) {
            $this->eventHandler = new EventHandler();
        }

        return $this->eventHandler;
    }

    public function isRegisteredEvent(string $event)
    {
        if (is_null($this->eventHandler)) {
            return false;
        }

        return $this->getEventHandler()->exist($event);
    }

    public function on(string $event, callable $callback)
    {
        if (in_array($event, $this->allowListenEvents)) {
            $this->getEventHandler()->register($event, $callback);
        } else {
            throw new InvalidArgumentException("Event $event now allow set.");
        }
    }

    public function emitOnEvent(string $event)
    {
        if ($this->isRegisteredEvent($event)) {
            $this->getEventHandler()->trigger($event, $this);
        }
    }
}