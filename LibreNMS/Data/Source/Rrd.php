<?php

namespace LibreNMS\Data\Source;

use App\Facades\LibrenmsConfig;
use LibreNMS\Util\Number;
use Symfony\Component\Process\Process;

class Rrd
{
    private array $options;
    private int $start;
    private ?int $end = null;
    private array $xport = [];

    private function __construct(
    ) {
        $this->start = time() - 3600;
        $rrdcached = LibrenmsConfig::get('rrdcached');
        $this->options = [LibrenmsConfig::get('rrdtool'), 'xport'];
        if ($rrdcached) {
            array_push($this->options, '--daemon', $rrdcached);
        }
    }

    public static function make(): static
    {
        return new static;
    }

    public function get(): array
    {
        $timeframe = ['--start', $this->start];
        if ($this->end) {
            array_push($timeframe, '--end', $this->end);
        }
        foreach($this->xport as $def) {
            $this->options[] = "XPORT:$def";
        }

        $proc = new Process($this->options + $timeframe);
        $proc->run();

        $xport_data = [];
        $timestamps = [];
        $output = $proc->getOutput();
        $error = $proc->getErrorOutput();
        if($error) {
            throw new \Exception($error);
        }

        $data = new \SimpleXMLElement($output);
        $current_timestamp = (int) $data->meta->start;
        $step = (int) $data->meta->step;

        foreach($data->data->row as $row) {
            foreach ($this->xport as $index => $def) {
                $value = (string) $row->v[$index];
                $xport_data[$def][] = $value == 'NaN' ? null : Number::cast($value);
            }
            $timestamps[] = $current_timestamp;
            $current_timestamp += $step;
        }

        return [
            'data' => $xport_data,
            'timestamps' => $timestamps,
        ];
    }

    public function def(string $label, string $dataset, string $rrd = null, string $aggregation = 'AVERAGE'): static
    {
        $def = "DEF:$label";
        if ($rrd) {
            $def .= "=$rrd";
        }
        $def .= ":$dataset:$aggregation";

        $this->options[] = $def;

        return $this;
    }

    public function cdef(string $label, string $source): static
    {
        $this->options[] = "CDEF:$label=$source";

        return $this;
    }

    public function xport(array $defs): static
    {
        $this->xport = $defs;

        return $this;
    }
}
