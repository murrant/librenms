<?php

namespace App\Models;

use App\Scopes\CredentialTypeScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpmiCredential extends Credential
{
    use HasFactory;

    protected $attributes = [
        'credential_type' => 'ipmi',
        'credentials' => [],
    ];

    protected static function boot()
    {
        static::addGlobalScope(new CredentialTypeScope('ipmi'));
        parent::boot();
    }

    // ---- Define Relationships ----

    public function devices(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'ipmi_credential_id');
    }
}
