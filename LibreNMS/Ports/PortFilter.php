<?php

namespace LibreNMS\Ports;

use App\Facades\LibrenmsConfig;
use Illuminate\Support\Str;

final class PortFilter
{
    private readonly bool $allowEmptyDescription;
    private readonly array $allowed;
    private readonly array $excluded;
    private readonly array $patterns;

    public function __construct(?string $os)
    {
        $this->allowEmptyDescription = LibrenmsConfig::getOsSetting($os, 'empty_ifdescr', LibrenmsConfig::get('empty_ifdescr', false));
        $this->allowed = (array) LibrenmsConfig::getOsSetting($os, 'good_if', LibrenmsConfig::get('good_if'));
        $this->excluded = [
            'ifDescr' => LibrenmsConfig::getCombined($os, 'bad_if'),
            'ifType' => LibrenmsConfig::getCombined($os, 'bad_iftype'),
            'ifOperStatus' => LibrenmsConfig::getCombined($os, 'bad_ifoperstatus'),
        ];
        $this->patterns = [
            'ifDescr' => LibrenmsConfig::getCombined($os, 'bad_if_regexp'),
            'ifName' => LibrenmsConfig::getCombined($os, 'bad_ifname_regexp'),
            'ifAlias' => LibrenmsConfig::getCombined($os, 'bad_ifalias_regexp'),
        ];
    }

    public function accepts(array $port): bool
    {
        $description = $port['ifDescr'] ?? '';
        if ($description === '' && (! $this->allowEmptyDescription || ($port['ifName'] ?? '') === '' && ($port['ifAlias'] ?? '') === '')) {
            return false;
        }
        if (Str::contains($description, $this->allowed, ignoreCase: true)) {
            return true;
        }
        foreach ($this->excluded as $field => $values) {
            if (Str::contains($port[$field] ?? '', $values, ignoreCase: $field === 'ifDescr')) {
                return false;
            }
        }
        foreach ($this->patterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern . 'i', $port[$field] ?? '')) {
                    return false;
                }
            }
        }

        return true;
    }
}
