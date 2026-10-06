<?php

namespace App\Graphing\Exceptions;

use Throwable;

class GraphNoData extends GraphException
{
    /**
     * @param  list<string>  $missing  descriptions of the missing data (file names, metrics)
     */
    public function __construct(public readonly array $missing = [], ?Throwable $previous = null)
    {
        $message = $missing === [] ? 'No Data' : 'No Data file ' . implode(', ', $missing);

        parent::__construct($message, 'No Data', $previous);
    }
}
