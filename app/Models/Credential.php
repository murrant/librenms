<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LibreNMS\Enum\CredentialType;

class Credential extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'credential_type',
        'default',
        'data',
    ];

    public $casts = [
        'credential_type' => CredentialType::class,
        'data' => EncryptedArray::class,
    ];
}
