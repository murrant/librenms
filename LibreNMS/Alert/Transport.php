<?php

namespace LibreNMS\Alert;

use LibreNMS\Config;
use LibreNMS\Interfaces\Alert\Transport as TransportInterface;

abstract class Transport implements TransportInterface
{
    protected $config;

    // Sets config field to an associative array of transport config values
    public function __construct($transport_id = null)
    {
        if (!empty($transport_id)) {
            $sql = "SELECT `transport_config` FROM `alert_transports` WHERE `transport_id`=?";
            $this->config = json_decode(dbFetchCell($sql, [$transport_id]), true);
        }
    }

    /**
     * Get the legacy configuration
     *
     * @return mixed
     */
    public function getLegacyConfig()
    {
        $name = $this->getName();

        return Config::get("alert.transports.$name", []);
    }

    /**
     * Get the name of this transport (usually just the lowercase class name)
     *
     * @return string
     */
    public function getName()
    {
        try {
            return strtolower((new \ReflectionClass($this))->getShortName());
        } catch (\ReflectionException $e) {
            return strtolower(array_pop(explode('\\', get_class($this))));
        }
    }

    public function hasLegacyConfig()
    {
        return empty($this->config);
    }
}
