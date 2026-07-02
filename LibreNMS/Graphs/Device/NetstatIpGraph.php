<?php

namespace LibreNMS\Graphs\Device;

use App\Facades\Rrd;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Data\Graphing\AbstractGraph;
use LibreNMS\Data\Graphing\Builders\MultiLineGraphBuilder;

class NetstatIpGraph extends AbstractGraph
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->device);
    }

    public function rrdDefinition(): array
    {
        $rrd_file = Rrd::name($this->device->hostname, 'netstats-ip');

        return (new MultiLineGraphBuilder())
            ->scaleMin(0)
            ->noTotal()
            ->colours('mixed')
            ->addDataset($rrd_file, 'ipForwDatagrams', 'Fwd Datagrams')
            ->addDataset($rrd_file, 'ipInDelivers', 'In Delivers')
            ->addDataset($rrd_file, 'ipInReceives', 'In Receives')
            ->addDataset($rrd_file, 'ipOutRequests', 'Out Requests', invert: true)
            ->addDataset($rrd_file, 'ipInDiscards', 'In Discards')
            ->addDataset($rrd_file, 'ipOutDiscards', 'Out Discards', invert: true)
            ->addDataset($rrd_file, 'ipOutNoRoutes', 'Out No Routes', invert: true)
            ->build($this->params);
    }

    public function getGraphTitle(): string
    {
        return $this->device->display . ' :: IP NetStats';
    }

    public function getRrdFiles(): array
    {
        return [Rrd::name($this->device->hostname, 'netstats-ip')];
    }
}
