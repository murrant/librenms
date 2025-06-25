<?php

namespace App\Discovery;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use LibreNMS\Util\IP;
use LibreNMS\Util\Mac;
use LibreNMS\Util\Validate;

class Neighbor
{
    protected string $domain;
    private ?Device $device;

    public function __construct(
        public readonly string $sysName = '',
        public readonly string $sysDescr = '',
        public readonly string $platform = '',
        public readonly ?IP $ipAddress = null,
        public readonly ?Mac $macAddress = null,
    ) {
        $this->domain = LibrenmsConfig::get('mydomain', '');
    }

    public function canSkipDiscovery(): bool
    {
        if ($this->sysName) {
            foreach ((array) LibrenmsConfig::get('autodiscovery.xdp_exclude.sysname_regexp') as $needle) {
                if (preg_match($needle . 'i', $this->sysName)) {
                    Log::debug("$this->sysName - regexp '$needle' matches '$this->sysName' - skipping device discovery \n");

                    return true;
                }
            }
        }

        if ($this->sysDescr) {
            foreach ((array) LibrenmsConfig::get('autodiscovery.xdp_exclude.sysdesc_regexp') as $needle) {
                if (preg_match($needle . 'i', $this->sysDescr)) {
                    Log::debug("$this->sysName - regexp '$needle' matches '$this->sysDescr' - skipping device discovery \n");

                    return true;
                }
            }
        }

        if ($this->platform) {
            foreach ((array) LibrenmsConfig::get('autodiscovery.cdp_exclude.platform_regexp') as $needle) {
                if (preg_match($needle . 'i', $this->platform)) {
                    Log::debug("$this->sysName - regexp '$needle' matches '$this->platform' - skipping device discovery \n");

                    return true;
                }
            }
        }

        return false;
    }

    public function findDevice(): int
    {
        // Build packed IP if possible
        $packedIp = $this->ipAddress?->packed();

        // Single query approach with priority ordering
        $this->device = Device::query()
            // Priority 1: Hostname matches (if valid)
            ->when($this->sysName && Validate::hostname($this->sysName), fn (Builder $query) => $query->addSelect([
                'priority' => \DB::raw('1'),
                'device_id',
            ])
                ->where(fn (Builder $q) => $q->where('hostname', $this->sysName)
                    ->when($this->domain,
                        fn (Builder $subQ) => $subQ->orWhere('hostname', "$this->sysName.$this->domain")
                            ->orWhereRaw('CONCAT(hostname, \'.\', ?) = ?', [$this->domain, $this->sysName])
                    )
                )
            )
            // Priority 2: IP matches
            ->when($this->ipAddress, fn (Builder $query) => $query->unionAll(
                Device::select(['device_id', \DB::raw('2 as priority')])
                    ->where(fn (Builder $q) => $q->where('hostname', $this->ipAddress)
                        ->when($packedIp, fn (Builder $subQ) => $subQ->orWhere('ip', $packedIp))
                    )
            )
            )
            // Priority 3: MAC address via ports
            ->when($this->macAddress?->isValid(), fn (Builder $query) => $query->unionAll(
                Port::select(['device_id', \DB::raw('3 as priority')])
                    ->where('ifPhysAddress', $this->macAddress->hex())
            )
            )
            // Priority 4: sysName (with duplicate handling)
            ->when($this->sysName, fn (Builder $query) => $query->unionAll(
                Device::select(['device_id', \DB::raw('4 as priority')])
                    ->where(fn (Builder $q) => $q->where('sysName', $this->sysName)
                        ->when($this->domain,
                            fn (Builder $subQ) => $subQ->orWhere('sysName', "$this->sysName.$this->domain")
                                ->orWhereRaw('CONCAT(sysName, \'.\', ?) = ?', [$this->domain, $this->sysName])
                        )
                    )
                    ->limit(2)
            )
            )
            ->orderBy('priority')
            ->first();

        return (int) $this->device?->device_id;
    }

    /**
     * Try to find a port by ifDescr, ifName, ifAlias, or MAC
     */
    public function findPort(
        ?string $portName = null,
        ?string $portIdentifier = null,
        ?Mac $macAddress = null
    ): int {
        if (! $this->device && ! $macAddress) {
            Log::warning('Cannot find port without device or MAC address.');

            return 0;
        }

        return Port::query()
            ->when($this->device, fn (Builder $query) => $query->where('device_id', $this->device->device_id))
            ->when($portName, function (Builder $query) use ($portName) {
                $query->orWhere(function (Builder $q) use ($portName) {
                    $q->where('ifDescr', $portName)
                        ->orWhere('ifName', $portName)
                        ->orWhere('ifAlias', $portName);
                });
            })
            ->when($portIdentifier, function (Builder $query) use ($portIdentifier) {
                $query->orWhere(function (Builder $q) use ($portIdentifier) {
                    if (is_numeric($portIdentifier)) {
                        $q->where('ifIndex', $portIdentifier)
                            ->orWhere('ifAlias', $portIdentifier);
                    } else {
                        $q->where('ifDescr', $portIdentifier)
                            ->orWhere('ifName', $portIdentifier);
                    }
                });
            })
            ->when($macAddress?->isValid(), function (Builder $query) use ($macAddress) {
                $query->orWhere('ifPhysAddress', $macAddress->hex());
            })
            ->value('port_id') ?? 0;
    }
}
