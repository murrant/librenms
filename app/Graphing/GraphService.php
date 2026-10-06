<?php

/**
 * GraphService.php
 *
 * Single entry point for resolving, authorizing, and rendering graphs.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Graphing;

use App\Graphing\Exceptions\GraphException;
use App\Graphing\Exceptions\GraphUnauthorized;
use App\Graphing\Rrd\RrdtoolRenderer;

class GraphService
{
    public function __construct(
        private readonly GraphRegistry $registry,
        private readonly RrdtoolRenderer $rrdtool,
    ) {
    }

    /**
     * @throws GraphException
     */
    public function resolve(GraphQuery $query, GraphAccess $access): ResolvedGraph
    {
        $handler = $this->registry->handler($query->type, $query->subtype);
        $subject = $handler->subject($query, $access);

        if (! $handler->authorize($subject, $access)) {
            throw new GraphUnauthorized;
        }

        return new ResolvedGraph($handler, $subject, $query, $this->rrdtool);
    }

    /**
     * @throws GraphException
     */
    public function render(GraphQuery $query, GraphAccess $access): GraphImage
    {
        return $this->resolve($query, $access)->render();
    }
}
