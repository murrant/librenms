<?php

namespace App\Observers;

use App\Models\Port;
use DB;
use LibreNMS\Modules\Ports;
use LibreNMS\Polling\FillsDerivedPeriodFields;

class PortObserver
{
    use FillsDerivedPeriodFields;

    /**
     * Handle the Port "created" event.
     *
     * @param  \App\Models\Port  $port
     * @return void
     */
    public function created(Port $port): void
    {
        //
    }

    /**
     * Handle the Port "updating" event.
     *
     * @param  \App\Models\Port  $port
     * @return void
     */
    public function updating(Port $port): void
    {
        if ($port->isDirty('poll_time')) {
            $port->poll_prev = $port->getOriginal('poll_time');
            if ($port->poll_prev) {
                $port->poll_period = $port->poll_time - $port->poll_prev;
            }
        }

        // only update derived fields when poll_period changed and is non-zero
        if ($port->isDirty('poll_period') && $port->poll_period) {
            $this->fillDerivedPeriodFields($port, Ports::BASE_PORT_STATS_FIELDS, $port->poll_period);
        }

        foreach (Ports::PORT_PREV_FIELDS as $field) {
            if ($port->isDirty($field)) {
                $port->setAttribute("{$field}_prev", $port->getOriginal($field));
            }
        }
    }

    /**
     * Handle the Port "deleting" event.
     *
     * @param  \App\Models\Port  $port
     * @return void
     */
    public function deleting(Port $port): void
    {
        // delete related data
        $port->adsl()->delete();
        $port->vdsl()->delete();
        $port->fdbEntries()->delete();
        $port->ipv4()->delete();
        $port->ipv6()->delete();
        $port->macAccounting()->delete();
        $port->macs()->delete();
        $port->nac()->delete();
        $port->ospfNeighbors()->delete();
        $port->ospfPorts()->delete();
        $port->pseudowires()->delete();
        $port->statistics()->delete();
        $port->stp()->delete();
        $port->vlans()->delete();

        // dont have relationships yet
        DB::table('juniAtmVp')->where('port_id', $port->port_id)->delete();
        DB::table('ports_perms')->where('port_id', $port->port_id)->delete();
        DB::table('links')->where('local_port_id', $port->port_id)->orWhere('remote_port_id', $port->port_id)->delete();
        DB::table('ports_stack')->where('port_id_low', $port->port_id)->orWhere('port_id_high', $port->port_id)->delete();

        \Rrd::purge(optional($port->device)->hostname, \Rrd::portName($port->port_id)); // purge all port rrd files
    }

    /**
     * Handle the Port "restored" event.
     *
     * @param  \App\Models\Port  $port
     * @return void
     */
    public function restored(Port $port): void
    {
        //
    }

    /**
     * Handle the Port "force deleted" event.
     *
     * @param  \App\Models\Port  $port
     * @return void
     */
    public function forceDeleted(Port $port): void
    {
        //
    }
}
