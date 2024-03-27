<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LibreNMS\Interfaces\Models\Keyable;

class Transceiver extends PortRelatedModel implements Keyable
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'port_id',
        'index',
        'type',
        'vendor',
        'oui',
        'model',
        'revision',
        'serial',
        'date',
        'ddm',
        'encoding',
        'distance',
        'connector',
        'channels',
    ];
    protected $casts = ['ddm' => 'boolean'];

    public function metrics(): HasMany
    {
        return $this->hasMany(TransceiverMetric::class);
    }

    public function getCompositeKey()
    {
        return $this->index;
    }
}
