<?php

/**
 * InfluxDB.php
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
 * @copyright  2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Store;

use App\Facades\LibrenmsConfig;
use App\Polling\Measure\Measurement;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Driver\UDP;
use Log;

class InfluxDB extends BaseDatastore
{
    /** @var Database */
    private Database $connection;

    public function __construct(Database $influx)
    {
        parent::__construct();
        $this->connection = $influx;

        // if the database doesn't exist, create it.
        try {
            if (! $influx->exists()) {
                $influx->create();
            }
        } catch (\Exception $e) {
            Log::warning('InfluxDB: Could not create database');
        }
    }

    public function getName(): string
    {
        return 'InfluxDB';
    }

    public static function isEnabled(): bool
    {
        return LibrenmsConfig::get('influxdb.enable', false);
    }

    /**
     * @inheritDoc
     */
    public function write(string $measurement, array $fields, array $tags = [], array $meta = []): void
    {
        $stat = Measurement::start('write');

        $timestamp = time();
        $tags = array_filter($tags, fn ($v) => $v !== null && $v !== '');
        $fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');

        $tmp_fields = [];
        foreach ($fields as $k => $v) {
            if ($k == 'time') {
                $k = 'rtime';
            }

            if (($value = $this->forceType($v)) !== null) {
                $tmp_fields[$k] = $value;
            }
        }

        if (empty($tmp_fields)) {
            Log::warning('All fields empty, skipping update', ['orig_fields' => $fields]);

            return;
        }

        Log::debug('InfluxDB data: ', [
            'measurement' => $measurement,
            'tags' => $tags,
            'fields' => $tmp_fields,
        ]);

        try {
            $points = [
                new \InfluxDB\Point(
                    $measurement,
                    null, // the measurement value
                    $tags,
                    $tmp_fields, // optional additional fields
                    $timestamp,
                ),
            ];

            $this->connection->writePoints($points);
            $this->recordStatistic($stat->end());
        } catch (\InfluxDB\Exception $e) {
            Log::error('InfluxDB exception: ' . $e->getMessage());
            Log::debug($e->getTraceAsString());
        }
    }

    /**
     * Create a new client and select the database
     *
     * @return Database
     */
    public static function createFromConfig(): Database
    {
        $host = LibrenmsConfig::get('influxdb.host', 'localhost');
        $port = LibrenmsConfig::get('influxdb.port', 8086);
        $timeout = LibrenmsConfig::get('influxdb.timeout', 0);

        $client = new Client(
            host: $host,
            port: $port,
            username: LibrenmsConfig::get('influxdb.username', ''),
            password: LibrenmsConfig::get('influxdb.password', ''),
            ssl: LibrenmsConfig::get('influxdb.transport', 'http') == 'https',
            verifySSL: LibrenmsConfig::get('influxdb.verifySSL', false),
            timeout: $timeout,
            connectTimeout: $timeout);

        if (LibrenmsConfig::get('influxdb.transport', 'http') == 'udp') {
            $client->setDriver(new UDP($host, $port));
        }

        return $client->selectDB(LibrenmsConfig::get('influxdb.db', 'librenms'));
    }

    private function forceType($data): ?float
    {
        /*
         * It is not trivial to detect if something is a float or an integer, and
         * therefore may cause breakages on inserts.
         * Just setting every number to a float gets around this, but may introduce
         * inefficiencies.
         */

        if (is_numeric($data)) {
            return floatval($data);
        }

        return null;
    }
}
