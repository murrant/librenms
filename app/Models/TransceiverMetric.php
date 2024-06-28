<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LibreNMS\Enum\Severity;
use LibreNMS\Enum\Status;
use LibreNMS\Interfaces\Models\Keyable;

class TransceiverMetric extends DeviceRelatedModel implements Keyable
{
    use HasFactory;
    protected $fillable = [
        'transceiver_id',
        'channel',
        'type',
        'description',
        'oid',
        'value',
        'multiplier',
        'divisor',
        'transform_function',
        'threshold_min_critical',
        'threshold_min_warning',
        'threshold_max_warning',
        'threshold_max_critical',
    ];
    protected $attributes = ['channel' => 0];
    protected $casts = [
        'value' => 'double',
        'value_prev' => 'double',
        'threshold_min_critical' => 'double',
        'threshold_min_warning' => 'double',
        'threshold_max_warning' => 'double',
        'threshold_max_critical' => 'double',
    ];

    public function getStatus(): Severity
    {
        $value = $this->attributes['value'];

        // no thresholds
        if (empty($this->attributes['threshold_min_critical']) && empty($this->attributes['threshold_max_critical']) && empty($this->attributes['threshold_min_warning']) && empty($this->attributes['threshold_max_warning'])) {
            return Severity::Unknown;
        }

        if ($value <= $this->attributes['threshold_min_critical'] || $value >= $this->attributes['threshold_max_critical']) {
            return Severity::Error;
        }

        if ($value <= $this->attributes['threshold_min_warning'] || $value >= $this->attributes['threshold_max_warning']) {
            return Severity::Warning;
        }

        return Severity::Ok;
    }

    public function transceiver(): BelongsTo
    {
        return $this->belongsTo(Transceiver::class);
    }

    public function getCompositeKey(): string
    {
        return $this->transceiver_id . '|' . $this->channel . '|' . $this->type;
    }

    public function defaultOrder(): int
    {
        $channelMod = $this->attributes['channel'] * 10;

        return $channelMod + match($this->attributes['type']) {
            'power-rx' => 0,
            'power-tx' => 1,
            'temperature' => 2,
            'bias' => 3,
            'voltage' => 4,
            default => 9,
        };
    }
}
