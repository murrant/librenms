<?php

namespace App\Models;

use App\Facades\DeviceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use LibreNMS\Enum\PortAssociationMode;
use LibreNMS\Interfaces\Models\Keyable;
use LibreNMS\Util\Rewrite;
use Permissions;

class Port extends DeviceRelatedModel implements Keyable
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'port_id';
    protected $fillable = [
        'ifIndex',
        'device_id',
        'ifDescr',
        'ifName',
        'ifIndex',
        'ifSpeed',
        'ifConnectorPresent',
        'ifOperStatus',
        'ifAdminStatus',
        'ifDuplex',
        'ifMtu',
        'ifType',
        'ifAlias',
        'ifPhysAddress',
        'ifLastChange',
        'ifVlan',
        'ifTrunk',
        'ifVrf',
        'poll_time',
        'ifInErrors',
        'ifOutErrors',
        'ifInUcastPkts',
        'ifOutUcastPkts',
        'ifInOctets',
        'ifOutOctets',
    ];

    // ---- Helper Functions ----

    /**
     * Returns a human readable label for this port
     *
     * @return string
     */
    public function getLabel()
    {
        $os = optional($this->device)->os;

        if (\LibreNMS\Config::getOsSetting($os, 'ifname')) {
            $label = $this->ifName;
        } elseif (\LibreNMS\Config::getOsSetting($os, 'ifalias')) {
            $label = $this->ifAlias;
        }

        if (empty($label)) {
            $label = $this->ifDescr;

            if (\LibreNMS\Config::getOsSetting($os, 'ifindex')) {
                $label .= " $this->ifIndex";
            }
        }

        foreach ((array) \LibreNMS\Config::get('rewrite_if', []) as $src => $val) {
            if (Str::contains(strtolower($label), strtolower($src))) {
                $label = $val;
            }
        }

        foreach ((array) \LibreNMS\Config::get('rewrite_if_regexp', []) as $reg => $val) {
            $label = preg_replace($reg . 'i', $val, $label);
        }

        return $label;
    }

    /**
     * Get the shortened label for this device.  Replaces things like GigabitEthernet with GE.
     *
     * @return string
     */
    public function getShortLabel()
    {
        return Rewrite::shortenIfName(Rewrite::normalizeIfName($this->ifName ?: $this->ifDescr));
    }

    /**
     * Get the description of this port
     */
    public function getDescription(): string
    {
        return (string) $this->ifAlias;
    }

    /**
     * Check if user can access this port.
     *
     * @param  User|int  $user
     * @return bool
     */
    public function canAccess($user)
    {
        if (! $user) {
            return false;
        }

        if ($user->hasGlobalRead()) {
            return true;
        }

        return Permissions::canAccessDevice($this->device_id, $user) || Permissions::canAccessPort($this->port_id, $user);
    }

    // ---- Accessors/Mutators ----

    public function getIfPhysAddressAttribute($mac)
    {
        if (! empty($mac)) {
            return preg_replace('/(..)(..)(..)(..)(..)(..)/', '\\1:\\2:\\3:\\4:\\5:\\6', $mac);
        }

        return null;
    }

    // ---- Query scopes ----

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsDeleted($query)
    {
        return $query->where([
            ['deleted', 1],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsNotDeleted($query)
    {
        return $query->where([
            ['deleted', 0],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsUp($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['ignore', '=', 0],
            ['disabled', '=', 0],
            ['ifOperStatus', '=', 'up'],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsDown($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['ignore', '=', 0],
            ['disabled', '=', 0],
            ['ifOperStatus', '!=', 'up'],
            ['ifAdminStatus', '=', 'up'],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsShutdown($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['ignore', '=', 0],
            ['disabled', '=', 0],
            ['ifAdminStatus', '=', 'down'],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsIgnored($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['ignore', '=', 1],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsDisabled($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['disabled', '!=', 0],
        ]);
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeHasErrors($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['ignore', '=', 0],
            ['disabled', '=', 0],
        ])->where(function ($query) {
            /** @var Builder $query */
            $query->where('ifInErrors_delta', '>', 0)
                ->orWhere('ifOutErrors_delta', '>', 0);
        });
    }

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeIsValid($query)
    {
        return $query->where([
            ['deleted', '=', 0],
            ['disabled', '=', 0],
        ]);
    }

    public function scopeHasAccess($query, User $user)
    {
        return $this->hasPortAccess($query, $user);
    }

    public function scopeInPortGroup($query, $portGroup)
    {
        return $query->whereIn($query->qualifyColumn('port_id'), function ($query) use ($portGroup) {
            $query->select('port_id')
                ->from('port_group_port')
                ->where('port_group_id', $portGroup);
        });
    }

    // ---- Define Relationships ----

    public function adsl(): HasMany
    {
        return $this->hasMany(PortAdsl::class, 'port_id');
    }

    public function vdsl(): HasMany
    {
        return $this->hasMany(PortVdsl::class, 'port_id');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(Eventlog::class, 'events', 'type', 'reference');
    }

    public function fdbEntries(): HasMany
    {
        return $this->hasMany(\App\Models\PortsFdb::class, 'port_id', 'port_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\PortGroup::class, 'port_group_port', 'port_id', 'port_group_id');
    }

    public function ipv4(): HasMany
    {
        return $this->hasMany(\App\Models\Ipv4Address::class, 'port_id');
    }

    public function ipv6(): HasMany
    {
        return $this->hasMany(\App\Models\Ipv6Address::class, 'port_id');
    }

    public function macAccounting(): HasMany
    {
        return $this->hasMany(MacAccounting::class, 'port_id');
    }

    public function macs(): HasMany
    {
        return $this->hasMany(Ipv4Mac::class, 'port_id');
    }

    public function nac(): HasMany
    {
        return $this->hasMany(PortsNac::class, 'port_id');
    }

    public function ospfNeighbors(): HasMany
    {
        return $this->hasMany(OspfNbr::class, 'port_id');
    }

    public function ospfPorts(): HasMany
    {
        return $this->hasMany(OspfPort::class, 'port_id');
    }

    public function pseudowires(): HasMany
    {
        return $this->hasMany(Pseudowire::class, 'port_id');
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(PortStatistic::class, 'port_id');
    }

    public function stp(): HasMany
    {
        return $this->hasMany(PortStp::class, 'port_id');
    }

    public function users(): BelongsToMany
    {
        // FIXME does not include global read
        return $this->belongsToMany(\App\Models\User::class, 'ports_perms', 'port_id', 'user_id');
    }

    public function vlans(): HasMany
    {
        return $this->hasMany(PortVlan::class, 'port_id');
    }

    /**
     * @inheritDoc
     */
    public function getCompositeKey()
    {
        $device = $this->relationLoaded('device') ? $this->device : DeviceCache::get($this->device_id);
        $port_assoc_mode = $device->port_association_mode ? PortAssociationMode::getName($device->port_association_mode) : \LibreNMS\Config::get('default_port_association_mode');

        return $this->device_id . '-' . $this->$port_assoc_mode;
    }
}
