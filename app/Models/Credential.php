<?php

namespace App\Models;

use App\Casts\EncryptedArray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    use HasFactory;

    protected $casts = [
        'credentials' => EncryptedArray::class,
    ];

    // ---- Helper Functions ----

    public function storeUserPassword(string $username, string $password): void
    {
        $this->credentials = ['username' => $username, 'password' => $password];
    }

    public function storeToken(string $token): void
    {
        $this->credentials = ['token' => $token];
    }

    public function getUsername(): string
    {
        return $this->credentials['username'] ?? '';
    }

    public function getPassword(): string
    {
        return $this->credentials['password'] ?? '';
    }

    public function getToken(): string
    {
        return $this->credentials['token'] ?? '';
    }
}
