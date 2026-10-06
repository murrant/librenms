<?php

namespace App\Graphing\Rrd;

use App\Facades\Rrd;
use App\Graphing\Exceptions\GraphNoData;
use App\Graphing\Exceptions\GraphRenderFailed;
use App\Graphing\GraphImage;
use App\Graphing\GraphQuery;
use App\Graphing\Plans\RrdCommand;
use LibreNMS\Exceptions\RrdException;
use LibreNMS\Exceptions\RrdNotFoundException;

class RrdtoolRenderer
{
    /**
     * @throws GraphNoData
     * @throws GraphRenderFailed
     */
    public function render(RrdCommand $command, GraphQuery $query): GraphImage
    {
        try {
            return new GraphImage($query->format, $command->title, Rrd::graph($command->options));
        } catch (RrdNotFoundException $e) {
            throw new GraphNoData(self::missingFiles($e), $e);
        } catch (RrdException $e) {
            throw GraphRenderFailed::fromRrdtool($e);
        }
    }

    public function command(RrdCommand $command): string
    {
        return implode(' ', array_map(escapeshellarg(...), [
            'rrdtool',
            ...Rrd::buildCommand('graph', '-', $command->options),
        ]));
    }

    /**
     * @return list<string>
     */
    private static function missingFiles(RrdNotFoundException $e): array
    {
        if (preg_match("/opening '([^']+)'/", $e->getMessage(), $matches)) {
            return [basename($matches[1])];
        }

        return [];
    }
}
