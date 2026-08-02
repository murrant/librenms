<?php

namespace LibreNMS\Graphs\Device;

use App\Facades\Rrd;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Data\Graphing\AbstractGraph;
use LibreNMS\Data\Graphing\Builders\MultiLineGraphBuilder;
use LibreNMS\Data\Graphing\DataSeries;
use LibreNMS\Interfaces\Data\Graphing\GraphDataInterface;

class NetstatIpGraph extends AbstractGraph implements GraphDataInterface
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->device);
    }

    public function getSeries(): array
    {
        $metric = $this->device->toMetricIdentity('netstats-ip');

        return [
            'ipForwDatagrams' => new DataSeries($metric, 'ipForwDatagrams', 'Fwd Datagrams'),
            'ipInDelivers' => new DataSeries($metric, 'ipInDelivers', 'In Delivers'),
            'ipInReceives' => new DataSeries($metric, 'ipInReceives', 'In Receives'),
            'ipOutRequests' => new DataSeries($metric, 'ipOutRequests', 'Out Requests'),
            'ipInDiscards' => new DataSeries($metric, 'ipInDiscards', 'In Discards'),
            'ipOutDiscards' => new DataSeries($metric, 'ipOutDiscards', 'Out Discards'),
            'ipOutNoRoutes' => new DataSeries($metric, 'ipOutNoRoutes', 'Out No Routes'),
        ];
//        ->addDataset($rrd_file, 'ipForwDatagrams', 'Fwd Datagrams')
//        ->addDataset($rrd_file, 'ipInDelivers', 'In Delivers')
//        ->addDataset($rrd_file, 'ipInReceives', 'In Receives')
//        ->addDataset($rrd_file, 'ipOutRequests', 'Out Requests', invert: true)
//        ->addDataset($rrd_file, 'ipInDiscards', 'In Discards')
//        ->addDataset($rrd_file, 'ipOutDiscards', 'Out Discards', invert: true)
//        ->addDataset($rrd_file, 'ipOutNoRoutes', 'Out No Routes', invert: true)
    }

    public function rrdDefinition(): array
    {
        $rrd_file = Rrd::name($this->device->hostname, 'netstats-ip');

        return (new MultiLineGraphBuilder($this))
            ->scaleMin(0)
            ->noTotal()
            ->setSeriesOptions(['ipOutRequests', 'ipOutDiscards', 'ipOutNoRoutes'], invert: true)
            ->colours('mixed')
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
