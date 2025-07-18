<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use LibreNMS\Interfaces\Models\Keyable;
use LibreNMS\Util\Number;

class Processor extends DeviceRelatedModel implements Keyable
{
    public $timestamps = false;
    protected $primaryKey = 'processor_id';
    protected $fillable = [
        'entPhysicalIndex',
        'hrDeviceIndex',
        'processor_oid',
        'processor_index',
        'processor_type',
        'processor_usage',
        'processor_descr',
        'processor_precision',
        'processor_perc_warn',
    ];

    // ---- Attribute Mutators / Casting ----

    protected function processorDescr(): Attribute
    {
        return Attribute::make(
            set: function (?string $value) {
                if (empty($value)
                    || $value === 'Unknown Processor Type' // Windows: Unknown Processor Type
                    || $value === 'An electronic chip that makes the computer work.'
                ) {
                    return 'Processor';
                }

                $bad_descr = [
                    'GenuineIntel:',
                    'AuthenticAMD:',
                    'Intel(R)',
                    'CPU',
                    '(R)',
                    '(tm)',
                ];
                $descr = str_replace($bad_descr, '', $value);
                $descr = str_replace('  ', ' ', $descr); // reduce extra spaces;

                return $descr ?: $value;

            },
        );
    }

    protected function processorUsage(): Attribute
    {
        return Attribute::make(
            set: function (?string $value, array $attributes) {
                if ($value === null) {
                    return null;
                }

                // negative precision represents free, subtract from 100
                $precision = $attributes['processor_precision'] ?: 1;
                $base = $precision < 0 ? 100 : 0;
                $raw_usage = Number::extract($value);

                return $base + ($raw_usage / $precision);
            }
        );
    }

    public function getCompositeKey(): string
    {
        return $this->processor_type . '_' . $this->processor_index;
    }
}
