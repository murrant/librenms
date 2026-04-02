<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LibreNMS\Enum\SecretType;

class Secret extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'secret_type',
        'default',
        'data',
    ];

    public $casts = [
        'secret_type' => SecretType::class,
        'data' => EncryptedArray::class,
    ];
}
