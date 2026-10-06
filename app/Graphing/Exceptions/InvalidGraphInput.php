<?php

namespace App\Graphing\Exceptions;

use Illuminate\Validation\ValidationException;

class InvalidGraphInput extends GraphException
{
    public static function fromValidation(ValidationException $e): self
    {
        $message = implode(' ', array_merge(...array_values($e->errors())));

        return new self($message ?: 'Invalid Input', 'Invalid Input', $e);
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
