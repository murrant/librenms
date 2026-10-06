<?php

namespace App\Graphing\Rrd;

use App\Graphing\Definition\Series;

/**
 * A series ready to be written as rrdtool options
 */
final readonly class CompiledSeries
{
    /**
     * @param  string  $file  rrd file as passed to rrdtool
     * @param  string  $id  unique vname prefix
     */
    public function __construct(
        public Series $series,
        public string $file,
        public string $color,
        public string $id,
    ) {
    }

    public function def(string $suffix, string $cf): string
    {
        return 'DEF:' . $this->id . $suffix . '=' . $this->file . ':' . $this->series->field . ':' . $cf;
    }
}
