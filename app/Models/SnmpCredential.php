<?php

namespace App\Models;

use App\Scopes\CredentialTypeScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnmpCredential extends Credential
{
    use HasFactory;

    protected $attributes = [
        'credential_type' => 'snmp',
        'credentials' => [],
    ];

    protected $appends = [
        'version',
        'port',
        'transport',
        'community',
        'auth_level',
        'auth_name',
        'auth_algo',
        'auth_pass',
        'crypto_algo',
        'crypto_pass',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected static function boot()
    {
        static::addGlobalScope(new CredentialTypeScope('snmp'));
        parent::boot();
    }

    public static function fromDeviceArray(array $device): SnmpCredential
    {
        return new static([
            'version' => $device['snmpver'] ?? null,
            'community' => $device['community'] ?? null,
            'level' => $device['authlevel'] ?? null,
            'auth_name' => $device['authname'] ?? null,
            'auth_pass' => $device['authpass'] ?? null,
            'auth_algo' => $device['authalgo'] ?? null,
            'crypto_pass' => $device['cryptopass'] ?? null,
            'crypto_algo' => $device['cryptoalgo'] ?? null,
            'transport' => $device['transport'] ?? null,
            'port' => $device['port'] ?? null,
        ]);
    }

    public static function makeV1(string $community, string $transport = 'udp', int $port = 161): SnmpCredential
    {
        $new = new static;
        $new->credentials = [
            'version' => 'v1',
            'community' => $community,
            'transport' => $transport,
            'port' => $port,
        ];

        return $new;
    }

    public static function makeV2C(string $community, string $transport = 'udp', int $port = 161): SnmpCredential
    {
        $new = new static;
        $new->credentials = [
            'version' => 'v2c',
            'community' => $community,
            'transport' => $transport,
            'port' => $port,
        ];

        return $new;
    }

    public static function makeV3(string $level, string $auth_name = 'root', string $auth_pass = null, string $auth_algo = null, string $crypto_pass = null, string $crypto_algo = null, string $transport = 'udp', int $port = 161): SnmpCredential
    {
        $new = new static;
        $new->credentials = [
            'version' => 'v2c',
            'level' => $level,
            'auth_name' => $auth_name,
            'auth_pass' => $auth_pass,
            'auth_algo' => $auth_algo,
            'crypto_pass' => $crypto_pass,
            'crypto_algo' => $crypto_algo,
            'transport' => $transport,
            'port' => $port,
        ];

        return $new;
    }

    public function toNetSnmpOptions($context = null): array
    {
        $options = [];

        switch ($this->version) {
            case 'v3':
                array_push($options, '-v3', '-l', $this->auth_level);
                array_push($options, '-n', $context);

                switch (strtolower($this->auth_level)) {
                    case 'authpriv':
                        array_push($options, '-x', $this->crypto_algo);
                        array_push($options, '-X', $this->crypto_pass);
                        // fallthrough
                    case 'authnopriv':
                        array_push($options, '-a', $this->auth_algo);
                        array_push($options, '-A', $this->auth_pass);
                        // fallthrough
                    case 'noauthnopriv':
                        array_push($options, '-u', $this->auth_name);
                        return $options;
                    default:
                        \Log::debug("Unsupported SNMPv3 AuthLevel: {$this->auth_level}");
                        return $options;
                }
            case 'v1':
                // fallthrough
            case 'v2c':
                array_push($options, '-' . $this->version, '-c', $context ? "{$this->community}@$context" : $this->community);
                return $options;
            default:
                \Log::debug("Unsupported SNMP Version: {$this->version}");
                return $options;
        }
    }

    // ---- Accessors/Mutators ----

    public function getVersionAttribute(): string
    {
        return $this->credentials['version'] ?? 'v2c';
    }

    public function setVersionAttribute(string $version): void
    {
        $this->credentials['version'] = $version;
    }

    public function getTransportAttribute(): string
    {
        return $this->credentials['transport'] ?? 'udp';
    }

    public function setTransportAttribute(string $transport): void
    {
        $this->credentials['transport'] = $transport;
    }

    public function getPortAttribute(): int
    {
        return $this->credentials['port'] ?? 161;
    }

    public function setPortAttribute(int $port): void
    {
        $this->credentials['port'] = $port;
    }

    public function getCommunityAttribute(): string
    {
        return $this->credentials['community'] ?? '';
    }

    public function setCommunityAttribute(string $community): void
    {
        $this->credentials['community'] = $community;
    }

    public function getAuthLevelAttribute(): string
    {
        return $this->credentials['auth_level'] ?? 'noAuthNoPriv';
    }

    public function setAuthLevelAttribute(string $level): void
    {
        $this->credentials['auth_level'] = $level;
    }

    public function getAuthNameAttribute(): string
    {
        return $this->credentials['auth_name'] ?? 'root';
    }

    public function setAuthNameAttribute($name): void
    {
        $this->credentials['auth_name'] = $name;
    }

    public function getAuthAlgoAttribute(): string
    {
        return $this->credentials['auth_algo'] ?? '';
    }

    public function setAuthAlgoAttribute($algo): void
    {
        $this->credentials['auth_algo'] = $algo;
    }

    public function getAuthPassAttribute(): string
    {
        return $this->credentials['auth_pass'] ?? '';
    }

    public function setAuthPassAttribute($pass): void
    {
        $this->credentials['auth_pass'] = $pass;
    }

    public function getCryptoAlgoAttribute(): string
    {
        return $this->credentials['crypto_algo'] ?? '';
    }

    public function setCryptoAlgoAttribute($algo): void
    {
        $this->credentials['crypto_algo'] = $algo;
    }

    public function getCryptoPassAttribute(): string
    {
        return $this->credentials['crypto_pass'] ?? '';
    }

    public function setCryptoPassAttribute($algo): void
    {
        $this->credentials['crypto_pass'] = $algo;
    }

    // ---- Define Relationships ----

    public function devices(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'snmp_credential_id');
    }
}
