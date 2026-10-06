<?php

namespace App\Graphs\Device;

use App\Graphing\BaseGraph;
use App\Graphing\Definition\Axis;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Series;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;
use App\TimeSeries\MetricIdentity;

class NetstatIpGraph extends BaseGraph
{
    private const FIELDS = [
        'ipForwDatagrams' => 'Fwd Datagrams',
        'ipInDelivers' => 'In Delivers',
        'ipInReceives' => 'In Receives',
        'ipOutRequests' => 'Out Requests',
        'ipInDiscards' => 'In Discards',
        'ipOutDiscards' => 'Out Discards',
        'ipOutNoRoutes' => 'Out No Routes',
    ];

    public function title(GraphSubject $subject): string
    {
        return $subject->device?->display . ' :: IP NetStats';
    }

    public function define(GraphSubject $subject, GraphQuery $query): GraphDefinition
    {
        $metric = new MetricIdentity('netstats-ip', ['device_id' => $subject->device?->device_id]);

        $series = [];
        foreach (self::FIELDS as $field => $label) {
            $series[] = new Series($field, $metric, $field, $label, invert: str_contains($field, 'Out'));
        }

        return new GraphDefinition($series, axis: new Axis(min: 0));
    }
}
