<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Enum\SecretType;

class Secret extends BaseModel
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

    // ---- Query Scopes ----

    /**
     * @param  Builder<Secret>  $query
     */
    public function scopeHasAccess(Builder $query, User $user): Builder
    {
        if (Gate::forUser($user)->allows('viewAll', Secret::class) || Gate::forUser($user)->allows('viewAll', Device::class)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->whereHas('devices', function (Builder $query) use ($user) {
                $query->whereIntegerInRaw('devices.device_id', \Permissions::devicesForUser($user));
            })->orWhere($query->qualifyColumn('default'), true);
        });
    }

    // ---- Define Relationships ----

    /**
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_polling_methods', 'secret_id', 'device_id')
            ->withPivot('method_type');
    }
}
