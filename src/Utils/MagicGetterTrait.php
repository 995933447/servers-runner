<?php
namespace Bobby\ServersRunner\Utils;

trait MagicGetterTrait
{
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \RuntimeException("Property " . get_class($this) . "::$name does not exist.");
    }
}