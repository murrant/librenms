<?php

namespace App\Data\Secrets;

class IcmpSecret extends SecretData
{
    public function __construct()
    {
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }

    public static function rules(): array
    {
        return [];
    }

    public static function getUiSchema(): array
    {
        return [];
    }
}
