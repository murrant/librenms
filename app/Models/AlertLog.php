<?php

namespace App\Models;

use App\Casts\CompressedJson;
use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LibreNMS\Enum\AlertLogState;

class AlertLog extends DeviceRelatedModel
{
    use Filterable;
    use HasFactory;

    public const UPDATED_AT = null;
    public const CREATED_AT = 'time_logged';
    protected $table = 'alert_log';
    protected $fillable = [
        'device_id',
        'rule_id',
        'state',
        'details',
    ];
    protected array $filterable = [
        'device_id',
        'rule_id',
        'state',
        'rule.severity',
        'device.groups.id',
    ];
    protected $casts = [
        'state' => AlertLogState::class,
        'details' => CompressedJson::class,
        'time_logged' => 'datetime',
    ];

    /**
     * @return BelongsTo<AlertRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'rule_id', 'id');
    }

    /**
     * @return array<array{key: string, label: string, type: string, endpoint?: string, options?: array<string, string>}>
     */
    public static function filterFieldDefinitions(?int $deviceId = null): array
    {
        $fields = [];

        if ($deviceId === null) {
            $fields[] = [
                'key' => 'device_id',
                'label' => __('Device'),
                'type' => 'select',
                'endpoint' => route('ajax.select.device'),
            ];
            $fields[] = [
                'key' => 'device.groups.id',
                'label' => __('Device group'),
                'type' => 'select',
                'endpoint' => route('ajax.select.device-group'),
            ];
        }

        return array_merge($fields, [
            [
                'key' => 'rule_id',
                'label' => __('Alert Rule'),
                'type' => 'select',
                'endpoint' => route('ajax.select.alert-rule'),
            ],
            [
                'key' => 'state',
                'label' => __('State'),
                'type' => 'select',
                'options' => [
                    '0' => __('Ok'),
                    '1' => __('Alert'),
                    '2' => __('Acknowledged'),
                    '3' => __('Worse'),
                    '4' => __('Better'),
                    '5' => __('Changed'),
                ],
            ],
            [
                'key' => 'rule.severity',
                'label' => __('Severity'),
                'type' => 'select',
                'options' => [
                    'critical' => __('Critical'),
                    'warning' => __('Warning'),
                    'ok' => __('OK'),
                ],
            ],
        ]);
    }
}
