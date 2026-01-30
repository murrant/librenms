<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use LibreNMS\Enum\Severity;

class Service extends DeviceRelatedModel
{
    protected $primaryKey = 'service_id';
    protected $fillable = [
        'device_id',
        'service_ip',
        'service_type',
        'service_desc',
        'service_param',
        'service_ignore',
        'service_status',
        'service_message',
        'service_disabled',
        'service_ds',
        'service_template_id',
        'service_name',
    ];
    protected $casts = [
        'service_ignore' => 'bool',
        'service_disabled' => 'bool',
        'service_status' => 'int',
        'service_ds' => 'array',
    ];

    protected function serviceParam(): Attribute
    {
        return Attribute::make(
            get: function (string $value): array {
                $params = json_decode($value, true);

                if (is_array($params)) {
                    return $params;
                }

                $params = [];
                $values = explode(' ', $value);
                foreach ($values as $index => $value) {
                    if (str_starts_with($value, '-')) {
                    }
                }

                return $params;
            },
        );
    }

    // ---- Helper Functions ----

    public function statusAsSeverity(): Severity
    {
        return match ($this->service_status) {
            0 => Severity::Ok,
            1 => Severity::Warning,
            2 => Severity::Error,
            default => Severity::Unknown,
        };
    }

    // ---- Query Scopes ----

    public function scopeIsActive(Builder $query): Builder
    {
        return $query->where([
            ['service_ignore', '=', 0],
            ['service_disabled', '=', 0],
        ]);
    }

    public function scopeIsOk(Builder $query): Builder
    {
        return $query->where([
            ['service_ignore', '=', 0],
            ['service_disabled', '=', 0],
            ['service_status', '=', 0],
        ]);
    }

    public function scopeIsCritical(Builder $query): Builder
    {
        return $query->where([
            ['service_ignore', '=', 0],
            ['service_disabled', '=', 0],
            ['service_status', '=', 2],
        ]);
    }

    public function scopeIsWarning(Builder $query): Builder
    {
        return $query->where([
            ['service_ignore', '=', 0],
            ['service_disabled', '=', 0],
            ['service_status', '=', 1],
        ]);
    }

    public function scopeIsIgnored(Builder $query): Builder
    {
        return $query->where([
            ['service_ignore', '=', 1],
            ['service_disabled', '=', 0],
        ]);
    }

    public function scopeIsDisabled(Builder $query): Builder
    {
        return $query->where('service_disabled', 1);
    }
}
