<?php

namespace App\TimeSeries;

/**
 * A stored time series, shared by the poller that writes it and the graphs that read it.
 * Implement one small class per metric family in App\TimeSeries\Metrics.
 *
 * The name and label order are a storage format: rrd files are named
 * <name>-<label values>.rrd, changing either renames the files and orphans existing data.
 */
interface Metric
{
    public function deviceId(): int;

    public function name(): string;

    /**
     * Label name => value, in storage order
     *
     * @return array<string, string|int>
     */
    public function labels(): array;
}
