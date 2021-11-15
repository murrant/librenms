<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credential extends Model
{
    use HasFactory;

    protected $table = 'credentials';

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

    // ---- Accessors/Mutators ----

    public function getCredentialsAttribute(string $value): array
    {
        try {
            return \Crypt::decrypt($value);
        } catch (DecryptException $e) {}

        return [];
    }

    public function setCredentialsAttribute(array $credentials): void
    {
        try {
            $this->attributes['credentials'] = \Crypt::encrypt($credentials);
        } catch (EncryptException $e) {}
    }
}
