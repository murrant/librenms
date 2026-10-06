<?php

namespace App\Graphing\Exceptions;

class GraphSubjectNotFound extends GraphException
{
    public function __construct(string $message = 'Not Found')
    {
        parent::__construct($message, 'Not Found');
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
