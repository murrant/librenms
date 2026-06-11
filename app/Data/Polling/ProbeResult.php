<?php

namespace App\Data\Polling;

readonly class ProbeResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public ?float $latencyMs = null,
    ) {
    }

    public static function success(float $latencyMs): self
    {
        return new self(true, latencyMs: $latencyMs);
    }

    public static function failure(string $error): self
    {
        return new self(false, error: $error);
    }
}
