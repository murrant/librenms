<?php

/**
 * SnmpResponse.php
 *
 * Represents the response data from an SNMP query.
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

namespace LibreNMS\Data\Source\Snmp;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LibreNMS\Enum\SnmpError;
use LibreNMS\Util\Oid;

class SnmpResponse implements \Stringable
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(
        public readonly array $values = [],
        public readonly ?SnmpDebugInfoInterface $debugInfo = null,
        public readonly string $raw = '',
    ) {
    }

    public function isValid(bool $ignore_partial = false): bool
    {
        if (! $this->hasErrors()) {
            return ! empty($this->values) || ! empty($this->raw);
        }

        if ($ignore_partial && ! empty($this->values)) {
            $fatalErrors = array_filter(
                $this->getErrors(),
                fn (SnmpError $error) => $error !== SnmpError::EndOfMib
            );

            return empty($fatalErrors);
        }

        return false;
    }

    /**
     * @return array<int, SnmpError>
     */
    public function getErrors(): array
    {
        return $this->debugInfo?->getErrors() ?? [];
    }

    public function getError(): ?SnmpError
    {
        return $this->debugInfo?->getError();
    }

    public function hasErrors(): bool
    {
        return $this->debugInfo?->hasErrors() ?? false;
    }

    public function hasError(SnmpError $error): bool
    {
        return $this->debugInfo?->hasError($error) ?? false;
    }

    /**
     * Get the primary error message if any.
     */
    public function getErrorMessage(): string
    {
        return $this->debugInfo?->getErrorMessage() ?? '';
    }

    /**
     * @return array<int, string>
     */
    public function getErrorMessages(): array
    {
        return $this->debugInfo?->getErrorMessages() ?? [];
    }

    public function getDebugInfo(): ?SnmpDebugInfoInterface
    {
        return $this->debugInfo;
    }

    public function getExitCode(): ?int
    {
        return $this->debugInfo?->getExitCode();
    }

    /**
     * Filter bad lines from the raw output.
     */
    public function getRawWithoutBadLines(): string
    {
        return NetSnmp::stripBadLines($this->raw);
    }

    /**
     * Gets the first value of this response.
     * If an oid or list of oids is given, return the first one found.
     * If forceNumeric is set, force the search to use numeric oids even if textual oids are given
     *
     * @throws \LibreNMS\Exceptions\InvalidOidException
     */
    public function value(array|string $oids = [], bool $forceNumeric = false): string
    {
        $values = $this->values();

        if (empty($oids)) {
            return Arr::first($values, default: '');
        }

        $oids = Arr::wrap($oids);

        // search for an exact match
        foreach ($oids as $oid) {
            if ($forceNumeric) {
                // translate all to numeric to make it easier to match
                $oid = Oid::of($oid)->toNumeric();
            }

            if (isset($values[$oid]) && $values[$oid] !== '') {
                return $values[$oid];
            }

            // if this is a textual oid without an index, match the first one at any index
            if (! preg_match('/[.[]\d+]?$/', (string) $oid)) {
                foreach ($values as $key => $value) {
                    if (preg_match('/^' . preg_quote((string) $oid, '/') . '[.[]/', (string) $key) && $value !== '') {
                        return $value;
                    }
                }
            }
        }

        // try to match table format
        if (str_contains($this->raw, '[')) {
            foreach ($oids as $oid) {
                $dot_index_oid = preg_replace('/\.([^.]+)/', '[$1]', (string) $oid);
                // if new oid is different and exists and is not an empty string
                if ($dot_index_oid !== $oid && isset($values[$dot_index_oid]) && $values[$dot_index_oid] !== '') {
                    return $values[$dot_index_oid];
                }
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Create a key to value pair for an OID
     * You may omit $oid if there is only one $oid in the walk
     */
    public function pluck(?string $oid = null): array
    {
        $output = [];
        $oid ??= '[a-zA-Z0-9:.-]+';
        $regex = "/^{$oid}[[.]([\d.[\]]+?)]?$/";

        foreach ($this->values() as $key => $value) {
            if (preg_match($regex, (string) $key, $matches)) {
                $output_key = str_replace('][', '.', $matches[1]);
                $output[$output_key] = $value;
            }
        }

        return $output;
    }

    /**
     * Group values by index as specified by $index_count
     * Useful when dealing with numeric oids
     * (By default this counts from right to left, using a negative index count will count from left to right)
     */
    public function groupByIndex(int $index_count = 1, array &$array = []): array
    {
        foreach ($this->values() as $oid => $value) {
            $parts = $this->getOidParts(ltrim((string) $oid, '.')); // trim leftmost . so negative counts work as expected
            $suffix = array_slice($parts, -$index_count);
            $index = implode('.', $suffix);

            $array[$index][$oid] = $value;
        }

        return $array;
    }

    /**
     * Separate the index from the OID name
     * Insert into array as index => oidName
     */
    public function valuesByIndex(array &$array = []): array
    {
        foreach ($this->values() as $oid => $value) {
            $parts = $this->getOidParts($oid);
            $name = array_shift($parts);
            $index = implode('.', $parts);

            $array[$index][$name] = $value;
        }

        return $array;
    }

    public function table(int $group = 0, array &$array = []): array
    {
        foreach ($this->values() as $key => $value) {
            $parts = $this->getOidParts($key);

            // move the oid name to the correct depth
            array_splice($parts, $group, 0, array_shift($parts));

            // merge the parts into an array, creating keys if they don't exist
            $tmp = &$array;
            foreach ($parts as $part) {
                $key = trim((string) $part, '"');
                $tmp = &$tmp[$key];
            }
            $tmp = $value; // assign the value as the leaf
        }

        return Arr::wrap($array); // if no parts, wrap the value
    }

    /**
     * Map an snmp table with callback. If invalid data is encountered, an empty collection is returned.
     * Variables passed to the callback will be an array of row values followed by each individual index.
     */
    public function mapTable(callable $callback): Collection
    {
        if (! $this->isValid(true)) {
            return new Collection;
        }

        $data = [];
        foreach ($this->values() as $key => $value) {
            $parts = $this->getOidParts($key);
            $oid = array_shift($parts);
            $data[implode('][', $parts)][$oid] = $value;
        }

        $return = new Collection;
        foreach ($data as $index => $values) {
            $return->push(call_user_func($callback, $values, ...explode('][', (string) $index)));
        }

        return $return;
    }

    public function append(SnmpResponse $response): SnmpResponse
    {
        if (empty($this->values) && $this->raw === '' && $this->debugInfo === null) {
            return $response;
        }

        $newValues = array_merge($this->values, $response->values);
        $newRaw = $this->raw . $response->raw;
        $debugInfo = match (true) {
            $this->debugInfo !== null && $response->debugInfo !== null => $this->debugInfo->append($response->debugInfo),
            $this->debugInfo !== null => $this->debugInfo,
            default => $response->debugInfo,
        };

        return new static(
            values: $newValues,
            debugInfo: $debugInfo,
            raw: $newRaw,
        );
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    private function getOidParts(string $key): array
    {
        // table
        if (Str::contains($key, '[')) {
            preg_match_all('/([^[\]]+)/', $key, $parts);

            return $parts[1]; // get all group 1 matches
        }

        // regular oid
        return explode('.', $key);
    }

    public function __sleep()
    {
        return ['values', 'raw', 'debugInfo'];
    }
}
