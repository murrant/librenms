<?php

/**
 * MetricSchema.php
 *
 * The labels each metric is identified by, in storage order.
 * Readers (graphs) and writers (pollers) must agree on these, the rrd file name is built from them.
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

namespace App\TimeSeries;

use App\TimeSeries\Exceptions\InvalidMetric;

class MetricSchema
{
    /** Label every device metric has, not part of the per metric label list */
    public const DEVICE_LABEL = 'device_id';

    /**
     * Metric name => ordered label names (excluding device_id).
     * Each entry must have a test in MetricWriterContractTest matching the poller that writes it.
     *
     * @var array<string, list<string>>
     */
    public const METRICS = [
        'netstats-ip' => [], // LibreNMS\Modules\Netstats
        'processor' => ['processor_type', 'processor_index'], // LibreNMS\Device\Processor
    ];

    /**
     * @param  array<string, list<string>>  $metrics
     */
    public function __construct(private readonly array $metrics = self::METRICS)
    {
    }

    /**
     * @return list<string> metric names
     */
    public function names(): array
    {
        return array_keys($this->metrics);
    }

    /**
     * @return list<string>
     *
     * @throws InvalidMetric
     */
    public function labels(string $name): array
    {
        return $this->metrics[$name] ?? throw new InvalidMetric("Unknown metric $name, add it to " . self::class);
    }

    /**
     * Label values in schema order. Labels must match the schema exactly.
     *
     * @return list<scalar|null>
     *
     * @throws InvalidMetric
     */
    public function orderedValues(MetricIdentity $identity): array
    {
        $expected = $this->labels($identity->name);
        $given = array_values(array_diff(array_keys($identity->labels), [self::DEVICE_LABEL]));

        $missing = array_diff($expected, $given);
        $extra = array_diff($given, $expected);
        if ($missing !== [] || $extra !== []) {
            throw new InvalidMetric(sprintf('Metric %s labels do not match schema, missing: [%s] unexpected: [%s]',
                $identity->name, implode(', ', $missing), implode(', ', $extra)));
        }

        return array_map(fn (string $label) => $identity->labels[$label], $expected);
    }
}
