<?php

namespace App\Models;

use LibreNMS\Interfaces\Models\Keyable;

class PortStatistic extends PortRelatedModel implements Keyable
{
    protected $table = 'ports_statistics';
    protected $primaryKey = 'port_id';
    public $timestamps = false;
    protected $fillable = [
        'ifInNUcastPkts',
        'ifOutNUcastPkts',
        'ifInDiscards',
        'ifOutDiscards',
        'ifInUnknownProtos',
        'ifInBroadcastPkts',
        'ifOutBroadcastPkts',
        'ifInMulticastPkts',
        'ifOutMulticastPkts',
    ];

    /**
     * @inheritDoc
     */
    public function getCompositeKey()
    {
        return $this->port_id;
    }
}
