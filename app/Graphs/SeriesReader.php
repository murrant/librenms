<?php

namespace App\Graphs;

use App\Data\Graphing\DataSeries;
use App\Data\Graphing\SeriesSum;
use App\Data\TimeSeries\SeriesData;

/**
 * Reads the values of graph series from a datastore, for drawing in the browser
 */
interface SeriesReader
{
    /**
     * Values are in the units of the series. The lowest and highest of formulas and sums are those of their averages, so they are left out.
     *
     * @param  array<string, DataSeries|SeriesSum>  $series  sums add up series listed before them
     * @param  int  $from  unix time
     * @param  int  $to  unix time
     * @param  int  $points  longer ranges are consolidated to about this many
     *
     * @throws \Exception when the datastore fails
     */
    public function read(array $series, int $from, int $to, int $points): SeriesData;
}
