<?php

namespace App\Observers;

use App\Models\PortStatistic;
use LibreNMS\Modules\Ports;
use LibreNMS\Polling\FillsDerivedPeriodFields;

class PortStatisticObserver
{
    use FillsDerivedPeriodFields;

    /**
     * Handle the PortStatistic "created" event.
     *
     * @param  \App\Models\PortStatistic  $port_statistic
     * @return void
     */
    public function created(PortStatistic $port_statistic)
    {
        //
    }

    /**
     * Handle the Port "updating" event.
     *
     * @param  \App\Models\PortStatistic  $port_statistic
     * @return void
     */
    public function updating(PortStatistic $port_statistic): void
    {
        // only update derived fields when poll_period changed and is non-zero
        if ($port_statistic->port->poll_period) {
            $this->fillDerivedPeriodFields($port_statistic, Ports::PORT_STATS_FIELDS, $port_statistic->port->poll_period);
        }
    }

    /**
     * Handle the PortStatistic "deleted" event.
     *
     * @param  \App\Models\PortStatistic  $port_statistic
     * @return void
     */
    public function deleted(PortStatistic $port_statistic)
    {
        //
    }

    /**
     * Handle the PortStatistic "restored" event.
     *
     * @param  \App\Models\PortStatistic  $port_statistic
     * @return void
     */
    public function restored(PortStatistic $port_statistic)
    {
        //
    }

    /**
     * Handle the PortStatistic "force deleted" event.
     *
     * @param  \App\Models\PortStatistic  $port_statistic
     * @return void
     */
    public function forceDeleted(PortStatistic $port_statistic)
    {
        //
    }
}
