<?php

namespace LibreNMS\Interfaces\Polling;

use App\Models\Port;
use LibreNMS\Ports\PortInventory;

interface PortDataSource
{
    /** Return interface identities and properties, including speed and duplex. */
    public function discoverPorts(): PortInventory;

    /**
     * Return measurements for discovered interfaces, keyed by their inventory index.
     *
     * @param  array<int, Port>  $ports  Enabled ports indexed by ifIndex.
     */
    public function pollPorts(array $ports, bool $selected): array;
}
