<?php

namespace LibreNMS\Data\Source;

use App\Facades\LibrenmsConfig;
use LibreNMS\Data\ChartData;
use LibreNMS\Enum\RrdCF;
use Symfony\Component\Process\Process;

class RrdCommand
{
    private array $options = [];

    private function __construct(
        public int $start,
        public ?int $end = null,
    ) {
    }

    public static function make(int $start, ?int $end = null): static
    {
        return new static($start, $end);
    }

    public function get(): array
    {

    }

    public function def(string $label, string $dataset, string $rrd = null, RrdCF $consolidationFunction = RrdCF::AVERAGE): static
    {
        $def = "DEF:$label";
        if ($rrd) {
            $def .= "=$rrd";
        }
        $def .= ":$dataset:$consolidationFunction->name";

        $this->options[] = $def;

        return $this;
    }

    public function cdef(string $label, string $rpnExpression): static
    {
        $this->options[] = "CDEF:$label=$rpnExpression";

        return $this;
    }

    public function graph(): string
    {
        return $this->run('graph');
    }

    public function xport(array $defs): ChartData
    {
        // set up XPORT options either ds only or ds => label
        foreach($defs as $def) {
            $this->options[] = "XPORT:$def:$def";
        }

        $xport = json_decode($this->run('xport'));

        return ChartData::createFromRrd($xport->meta->legend, $xport->data);
    }

    private function run(string $command): string
    {
        $cli = [
            LibrenmsConfig::get('rrdtool'),
            $command,
            '--showtime',
            '--json',
            '--start',
            $this->start,
        ];
        if ($this->end) {
            array_push($cli, '--end', $this->end);
        }
        $rrdcached = LibrenmsConfig::get('rrdcached');
        if ($rrdcached) {
            array_push($cli, '--daemon', $rrdcached);
        }

        $proc = new Process(array_merge($cli, $this->options));
        $proc->run();

        $error = $proc->getErrorOutput();
        if($error) {
            throw new \Exception($error);
        }

        return $proc->getOutput();
    }
}
