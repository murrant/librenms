<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'threshold_min_critical',
        'threshold_min_warning',
        'threshold_max_warning',
        'threshold_max_critical',
    ];
    protected $attributes = ['channel' => 0];

    public function transceiver(): BelongsTo
    {
        return $this->belongsTo(Transceiver::class);
    }

    public function getCompositeKey()
    {
        return $this->transceiver_id . '|' . $this->channel . '|' . $this->type;
    }
}
