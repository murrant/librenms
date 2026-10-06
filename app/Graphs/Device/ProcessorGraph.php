<?php

namespace App\Graphs\Device;

use App\Facades\LibrenmsConfig;
use App\Graphing\BaseGraph;
use App\Graphing\Definition\Axis;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Definition\Layout;
use App\Graphing\Definition\Series;
use App\Graphing\Exceptions\GraphSubjectNotFound;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;
use App\Models\Processor;
use App\TimeSeries\MetricIdentity;

class ProcessorGraph extends BaseGraph
{
    public function subject(GraphQuery $query): GraphSubject
    {
        $device = $this->device($query);

        if ($device->processors->isEmpty()) {
            throw new GraphSubjectNotFound('No Processors');
        }

        return new GraphSubject($device);
    }

    public function define(GraphSubject $subject, GraphQuery $query): GraphDefinition
    {
        $processors = $subject->device->processors ?? collect();
        $stacked = (bool) LibrenmsConfig::getOsSetting($subject->device?->os, 'processor_stacked');

        $series = $processors->values()->map(fn (Processor $processor) => new Series(
            key: "processor_$processor->processor_id",
            metric: new MetricIdentity('processor', [
                'device_id' => $processor->device_id,
                'processor_type' => $processor->processor_type,
                'processor_index' => $processor->processor_index,
            ]),
            field: 'usage',
            label: $processor->getFormattedDescription(),
            optional: true, // processors are polled independently, one without data should not hide the others
            area: ! $stacked,
            // stacked processors show the share of total load
            multiplier: $stacked ? 1 / $processors->count() : 1.0,
        ))->all();

        if ($stacked) {
            return new GraphDefinition(
                $series,
                layout: Layout::StackedArea,
                axis: new Axis(label: 'Load %', min: 0, max: 100),
                palette: 'oranges',
                legendRawValues: true,
            );
        }

        return new GraphDefinition($series, axis: new Axis(label: 'Load %', min: 0, max: 100));
    }
}
