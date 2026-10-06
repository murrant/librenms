<?php

namespace App\Graphing\Exceptions;

use LibreNMS\Exceptions\RrdGraphException;
use Throwable;

class GraphRenderFailed extends GraphException
{
    public static function fromRrdtool(Throwable $e): self
    {
        return new self('Error: ' . $e->getMessage(), 'Draw Error', $e);
    }

    /**
     * Errors thrown by legacy graph templates keep their messages
     */
    public static function fromLegacy(RrdGraphException $e): self
    {
        return new self($e->getMessage(), $e->getShortText(), $e);
    }

    public function report(): bool
    {
        return false; // use default reporting
    }
}
