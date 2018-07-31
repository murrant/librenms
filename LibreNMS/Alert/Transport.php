<?php

namespace LibreNMS\Alert;

use App\Models\AlertTransport;
use LibreNMS\Alert\Transport\Dummy;
use LibreNMS\Config;
use LibreNMS\Interfaces\Alert\Transport as TransportInterface;

abstract class Transport implements TransportInterface
{
    protected $config;

    // Sets config field to an associative array of transport config values
    public function __construct($transport_id = null)
    {
        if (!empty($transport_id)) {
            $this->config = AlertTransport::where('transport_id', $transport_id)->value('transport_config');
        }
    }

    /**
     * @param int $transport_id
     * @return \LibreNMS\Interfaces\Alert\Transport
     */
    public static function make($transport_id)
    {
        // grab the name of the alert transport
        $alert_transport = AlertTransport::find($transport_id);
        $type = $alert_transport->transport_type;

        $class  = 'LibreNMS\\Alert\\Transport\\' . ucfirst($type);
        if (class_exists($class)) {
            // save an sql query and load config from existing data
            $transport = new $class();
            $transport->config = $alert_transport->transport_config;
            return $transport;
        }

        \Log::error("Failed to load alert transport: $type");
        return new Dummy();
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
