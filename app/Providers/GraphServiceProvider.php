<?php

namespace App\Providers;

use App\Graphing\Contracts\GraphHandler;
use App\Graphing\GraphRegistry;
use App\Graphing\GraphService;
use Illuminate\Support\ServiceProvider;

class GraphServiceProvider extends ServiceProvider
{
    /**
     * Graphs implemented as classes, graph name (type_subtype) => handler.
     * Graphs not listed here are rendered by their legacy includes/html/graphs template.
     * Removing an entry reverts that graph to its legacy template.
     *
     * @var array<string, class-string<GraphHandler>>
     */
    public const GRAPHS = [
    ];

    public function register(): void
    {
        $this->app->singleton(GraphRegistry::class, fn () => new GraphRegistry(self::GRAPHS));
        $this->app->singleton(GraphService::class);
    }
}
