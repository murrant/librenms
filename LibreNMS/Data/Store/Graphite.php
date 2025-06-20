<?php

/**
 * Graphite.php
 *
 * -Description-
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
 * @copyright  2020 Tony Murray
 * @copyright  2017 Falk Stern <https://github.com/fstern/>
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Store;

use App\Polling\Measure\Measurement;
use Carbon\Carbon;
use LibreNMS\Config;
use Log;

class Graphite extends BaseDatastore
{
    protected ?\Socket\Raw\Socket $connection = null;

    protected mixed $prefix;

    public function __construct(\Socket\Raw\Factory $socketFactory)
    {
        parent::__construct();
        $host = Config::get('graphite.host');
        $port = Config::get('graphite.port', 2003);
        try {
            if (self::isEnabled() && $host && $port) {
                $this->connection = $socketFactory->createClient("$host:$port");
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        if ($this->connection) {
            Log::notice("Graphite connection made to $host");
        } else {
            Log::error("Graphite connection to $host has failed!");
        }

        $this->prefix = Config::get('graphite.prefix', '');
        if ($this->prefix) {
            $this->prefix .= '.';
        }
    }

    public function getName(): string
    {
        return 'Graphite';
    }

    public static function isEnabled(): bool
    {
        return Config::get('graphite.enable', false);
    }

    /**
     * @inheritDoc
     */
    public function write(string $measurement, array $fields, array $tags = [], array $meta = []): void
    {
        if (! $this->connection) {
            Log::error("Graphite Error: not connected\n");

            return;
        }

        $measurement = $this->prefix . $this->sanitizeMetricString($measurement);
        $tags = $this->serializeTags($tags);
        $timestamp = Carbon::now()->timestamp;


        foreach ($fields as $field => $value) {
            // Skip fields without values
            if (is_null($value)) {
                continue;
            }

            $metric = "$measurement.$field$tags";
            $this->writeData($metric, $value, $timestamp);
        }
    }

    /**
     * @param  string  $metric
     * @param  int|float|string  $value
     * @param  int  $timestamp
     */
    private function writeData(string $metric, int|float|string $value, int $timestamp): void
    {
        try {
            $stat = Measurement::start('write');

            $line = "$metric $value $timestamp\n";

            Log::debug("Sending to Graphite: $line");
            $this->connection->write($line);

            $this->recordStatistic($stat->end());
        } catch (\Socket\Raw\Exception $e) {
            Log::error('Graphite write error: ' . $e->getMessage());
        }
    }

    protected function serializeTags(array $tags): string
    {
        if (empty($tags)) {
            return '';
        }

        $tag_pairs = [];

        foreach ($tags as $tag => $value) {
            $tag_pairs[] = $this->sanitizeMetricString($tag) . '=' . $this->sanitizeMetricString($value);
        }

        return ';' . implode(';', $tag_pairs);
    }

    protected function sanitizeMetricString(string $string): string
    {
        return str_replace(['.', ';', '=', ' '], '_', $string);
    }
}
