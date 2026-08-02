<?php

namespace LibreNMS\Interfaces\Data\Graphing;

use LibreNMS\Data\Graphing\DataSeries;

interface GraphDataInterface
{
    /**
     * @return array<string, DataSeries>
     */
    public function getSeries(): array;
}
