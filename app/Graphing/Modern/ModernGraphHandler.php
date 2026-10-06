<?php

namespace App\Graphing\Modern;

use App\Graphing\Contracts\Graph;
use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Definition\GraphDefinition;
use App\Graphing\GraphAccess;
use App\Graphing\GraphDescription;
use App\Graphing\GraphQuery;
use App\Graphing\GraphSubject;
use Illuminate\Support\Facades\Gate;

/**
 * Adapts graph classes to the graph service
 */
class ModernGraphHandler implements GraphHandler
{
    public function __construct(private readonly Graph $graph)
    {
    }

    public function subject(GraphQuery $query, GraphAccess $access): GraphSubject
    {
        return $this->graph->subject($query);
    }

    public function authorize(GraphSubject $subject, GraphAccess $access): bool
    {
        // trusted contexts were authenticated elsewhere (signed url, allow_unauth_graphs, alerts)
        if ($access->isTrusted()) {
            return true;
        }

        $authorizable = $subject->authorizable();

        return $access->user !== null
            && $authorizable !== null
            && Gate::forUser($access->user)->allows($this->graph->ability(), $authorizable);
    }

    public function describe(GraphSubject $subject): GraphDescription
    {
        return new GraphDescription(
            title: $this->graph->title($subject),
            device: $subject->device,
            port: $subject->port,
        );
    }

    public function plan(GraphSubject $subject, GraphQuery $query): GraphDefinition
    {
        $definition = $this->graph->define($subject, $query);

        return $definition->title === '' ? $definition->withTitle($this->graph->title($subject)) : $definition;
    }

    public function rules(): array
    {
        return $this->graph->rules();
    }
}
