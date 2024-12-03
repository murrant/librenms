<?php

namespace LibreNMS\Data;

class ChartDataset
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $color = null,
        public ?string $fill = null,
    ) {
    }
}
