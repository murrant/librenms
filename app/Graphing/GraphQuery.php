<?php

/**
 * GraphQuery.php
 *
 * Typed graph request input. The only place raw graph vars are parsed.
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

use LibreNMS\Enum\ImageFormat;
use LibreNMS\Util\Url;

final readonly class GraphQuery
{
    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $vars  raw input vars, passed through to legacy graphs
     */
    public function __construct(
        public string $type,
        public string $subtype,
        public array $ids,
        public ?int $deviceId,
        public int $from,
        public int $to,
        public int $width,
        public int $height,
        public ImageFormat $format,
        public array $vars,
    ) {
    }

    /**
     * @param  array<string, mixed>|string  $vars  graph vars or a legacy graph url/path
     */
    public static function fromVars(array|string $vars): self
    {
        $vars = is_string($vars) ? Url::parseLegacyPathVars($vars) : $vars;

        // GraphParameters holds the canonical parsing rules for graph vars
        $params = new GraphParameters($vars);

        $ids = array_values(array_map(intval(...), array_filter(
            explode(',', (string) ($vars['id'] ?? '')),
            is_numeric(...),
        )));

        $deviceId = isset($vars['device']) && is_numeric($vars['device']) ? (int) $vars['device'] : null;

        return new self(
            type: $params->type,
            subtype: $params->subtype,
            ids: $ids,
            deviceId: $deviceId,
            from: $params->from,
            to: $params->to,
            width: $params->width,
            height: $params->height,
            format: $params->imageFormat,
            vars: $vars,
        );
    }

    public function name(): string
    {
        return "{$this->type}_$this->subtype";
    }

    /**
     * A fresh, mutable parameter object for rrdtool option generation.
     * Legacy templates modify it, so it must never be shared between renders.
     */
    public function parameters(): GraphParameters
    {
        return new GraphParameters($this->vars);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromVars(array_merge($this->vars, $overrides));
    }
}
