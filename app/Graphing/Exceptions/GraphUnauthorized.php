<?php

namespace App\Graphing\Exceptions;

class GraphUnauthorized extends GraphException
{
    public function __construct(string $message = 'No Authorization')
    {
        parent::__construct($message, 'No Auth');
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
