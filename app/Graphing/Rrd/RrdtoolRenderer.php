<?php

namespace App\Graphing\Rrd;

use App\Facades\Rrd;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\Exceptions\GraphNoData;
use App\Graphing\Exceptions\GraphRenderFailed;
use App\Graphing\GraphImage;
use App\Graphing\GraphQuery;
use App\Graphing\Plans\RrdCommand;
use LibreNMS\Exceptions\RrdException;
use LibreNMS\Exceptions\RrdNotFoundException;
use LibreNMS\RRD\RrdPath;

class RrdtoolRenderer
{
    public function __construct(private readonly RrdtoolCompiler $compiler)
    {
    }

    /**
     * @throws GraphNoData
     * @throws GraphRenderFailed
     */
    public function render(RrdCommand $command, GraphQuery $query): GraphImage
    {
        try {
            return new GraphImage($query->format, $command->title, Rrd::graph($command->options));
        } catch (RrdNotFoundException $e) {
            throw new GraphNoData(self::missingFromMessage($e), $e);
        } catch (RrdException $e) {
            throw GraphRenderFailed::fromRrdtool($e);
        }
    }

    /**
     * Draw a graph definition. Files are not checked up front, rrdtool reports missing files.
     * When it does, the remaining files are checked with one query and the graph is drawn
     * again without the missing optional series. Missing required series means no data.
     *
     * @throws GraphNoData
     * @throws GraphRenderFailed
     */
    public function renderDefinition(GraphDefinition $definition, GraphQuery $query): GraphImage
    {
        $compiled = $this->compiler->compile($definition, $query);

        if ($compiled->isEmpty()) {
            throw new GraphNoData;
        }

        try {
            return $this->draw($compiled, $query);
        } catch (RrdNotFoundException $e) {
            $reported = $compiled->fileInMessage($e->getMessage()) ?? throw GraphRenderFailed::fromRrdtool($e);
        }

        $missing = Rrd::missingFiles($compiled->paths());
        if (! in_array($reported->relativePath(), array_map(fn (RrdPath $path) => $path->relativePath(), $missing), true)) {
            $missing[] = $reported; // trust rrdtool's error over the listing
        }

        $missingKeys = $compiled->keysFor($missing);
        if (! $definition->allOptional($missingKeys)) {
            throw new GraphNoData(self::names($missing));
        }

        $retry = $this->compiler->compile($definition, $query, $missingKeys);
        if ($retry->isEmpty()) {
            throw new GraphNoData(self::names($missing));
        }

        try {
            return $this->draw($retry, $query, self::names($missing));
        } catch (RrdNotFoundException $e) {
            throw new GraphNoData(self::names($missing), $e);
        }
    }

    public function command(RrdCommand|GraphDefinition $plan, GraphQuery $query): string
    {
        $options = $plan instanceof RrdCommand ? $plan->options : $this->compiler->compile($plan, $query)->options;

        return implode(' ', array_map(escapeshellarg(...), [
            'rrdtool',
            ...Rrd::buildCommand('graph', '-', $options),
        ]));
    }

    /**
     * @param  list<string>  $missing
     *
     * @throws RrdNotFoundException
     * @throws GraphRenderFailed
     */
    private function draw(CompiledGraph $compiled, GraphQuery $query, array $missing = []): GraphImage
    {
        try {
            return new GraphImage($query->format, $compiled->title, Rrd::graph($compiled->options), $missing);
        } catch (RrdNotFoundException $e) {
            throw $e;
        } catch (RrdException $e) {
            throw GraphRenderFailed::fromRrdtool($e);
        }
    }

    /**
     * @param  list<RrdPath>  $paths
     * @return list<string>
     */
    private static function names(array $paths): array
    {
        return array_map(fn (RrdPath $path) => basename($path->relativePath()), $paths);
    }

    /**
     * @return list<string>
     */
    private static function missingFromMessage(RrdNotFoundException $e): array
    {
        if (preg_match("/opening '([^']+)'/", $e->getMessage(), $matches)) {
            return [basename($matches[1])];
        }

        return [];
    }
}
