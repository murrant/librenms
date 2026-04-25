<?php

namespace LibreNMS\Enum;

use App\Data\Polling\Methods\Icmp;
use App\Data\Polling\Methods\Ipmi;
use App\Data\Polling\Methods\Snmp;
use App\Data\Polling\Methods\UnixAgent;
use App\Data\Secrets\IpmiSecret;
use App\Data\Secrets\SecretData;
use App\Data\Secrets\SnmpSecret;
use LibreNMS\Interfaces\PollingMethod;

enum PollingMethodType: string
{
    case Icmp      = 'icmp';
    case Ipmi      = 'ipmi';
    case Snmp      = 'snmp';
    case UnixAgent = 'unix-agent';

    /** @return class-string<PollingMethod> */
    public function methodClass(): string
    {
        return match($this) {
            self::Icmp      => Icmp::class,
            self::Ipmi      => Ipmi::class,
            self::Snmp      => Snmp::class,
            self::UnixAgent => UnixAgent::class,
        };
    }

    /** @return class-string<SecretData>|null */
    public function secretClass(): ?string
    {
        return match($this) {
            self::Snmp => SnmpSecret::class,
            self::Ipmi => IpmiSecret::class,
            default    => null,
        };
    }

    public function hasSecret(): bool
    {
        return $this->secretClass() !== null;
    }
}
