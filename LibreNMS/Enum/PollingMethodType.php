<?php

namespace LibreNMS\Enum;

use App\Data\Secrets\IpmiSecret;
use App\Data\Secrets\SecretData;
use App\Data\Secrets\SnmpSecret;
use LibreNMS\Interfaces\PollingMethod;
use LibreNMS\Polling\Method\IcmpPollingMethod;
use LibreNMS\Polling\Method\IpmiPollingMethod;
use LibreNMS\Polling\Method\SnmpPollingMethod;
use LibreNMS\Polling\Method\UnixAgentPollingMethod;

enum PollingMethodType: string
{
    case Icmp = 'icmp';
    case Ipmi = 'ipmi';
    case Snmp = 'snmp';
    case UnixAgent = 'unix-agent';

    /** @return class-string<PollingMethod> */
    public function methodClass(): string
    {
        return match ($this) {
            self::Icmp => IcmpPollingMethod::class,
            self::Ipmi => IpmiPollingMethod::class,
            self::Snmp => SnmpPollingMethod::class,
            self::UnixAgent => UnixAgentPollingMethod::class,
        };
    }

    /** @return class-string<SecretData>|null */
    public function secretClass(): ?string
    {
        return match ($this) {
            self::Snmp => SnmpSecret::class,
            self::Ipmi => IpmiSecret::class,
            default => null,
        };
    }

    public function hasSecret(): bool
    {
        return $this->secretClass() !== null;
    }
}
