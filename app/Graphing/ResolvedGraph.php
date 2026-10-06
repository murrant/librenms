<?php

namespace App\Graphing;

use App\Graphing\Contracts\GraphHandler;
use App\Graphing\Contracts\RenderPlan;
use App\Graphing\Exceptions\GraphException;
use App\Graphing\Plans\PrebuiltImage;
use App\Graphing\Plans\RrdCommand;
use App\Graphing\Rrd\RrdtoolRenderer;
use LogicException;

/**
 * A graph whose subject has been resolved and access authorized
 */
final class ResolvedGraph
{
    private ?GraphDescription $description = null;
    private ?RenderPlan $plan = null;

    public function __construct(
        private readonly GraphHandler $handler,
        public readonly GraphSubject $subject,
        public readonly GraphQuery $query,
        private readonly RrdtoolRenderer $rrdtool,
    ) {
    }

    public function description(): GraphDescription
    {
        return $this->description ??= $this->handler->describe($this->subject);
    }

    /**
     * @throws GraphException
     */
    public function render(): GraphImage
    {
        $plan = $this->plan();

        return match (true) {
            $plan instanceof PrebuiltImage => $plan->image,
            $plan instanceof RrdCommand => $this->rrdtool->render($plan, $this->query),
            default => throw new LogicException('Unsupported render plan ' . $plan::class),
        };
    }

    /**
     * The rrdtool command that renders this graph, null when the graph is not drawn by rrdtool
     *
     * @throws GraphException
     */
    public function command(): ?string
    {
        $plan = $this->plan();

        return $plan instanceof RrdCommand ? $this->rrdtool->command($plan) : null;
    }

    /**
     * @throws GraphException
     */
    private function plan(): RenderPlan
    {
        return $this->plan ??= $this->handler->plan($this->subject, $this->query);
    }
}
