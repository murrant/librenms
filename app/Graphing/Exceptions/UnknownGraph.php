<?php

namespace App\Graphing\Exceptions;

class UnknownGraph extends GraphException
{
    public static function named(string $name): self
    {
        return new self("{$name} template missing", "{$name} missing");
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
