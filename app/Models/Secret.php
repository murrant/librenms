<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LibreNMS\Enum\SecretType;

class Secret extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'secret_type',
        'default',
        'data',
    ];

    public $casts = [
        'secret_type' => SecretType::class,
        'data' => EncryptedArray::class,
    ];

    // ---- Define Relationships ----

    /**
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_secrets', 'secret_id', 'device_id')
            ->withPivot('secret_type');
    }
}
