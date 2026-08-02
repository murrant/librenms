<?php

namespace App\Models;

use App\TimeSeries\Contracts\MetricIdentifiable;
use App\TimeSeries\MetricIdentity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Processor extends DeviceRelatedModel implements MetricIdentifiable
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'processor_id';

    // ---- Helper Functions ----

    /**
     * Return Processor Description, formatted for display
     *
     * @return string
     */
    public function getFormattedDescription(): string
    {
        $bad_descr = [
            'GenuineIntel:',
            'AuthenticAMD:',
            'Intel(R)',
            'CPU',
            '(R)',
            '(tm)',
        ];

        $descr = str_replace($bad_descr, '', $this->processor_descr);

        // reduce extra spaces
        return str_replace('  ', ' ', $descr);
    }

    public function toMetricIdentity(string $metricName): MetricIdentity
    {
        return new MetricIdentity($metricName, [
            'device_id' => $this->device_id,
            'processor_type' => $this->processor_type,
            'processor_index' => $this->processor_index,
        ]);
    }
}
