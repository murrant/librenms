<?php
/**
 * Parameter.php
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
 * @copyright  2025 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Services;

readonly class ServiceArgument
{
    public function __construct(
        public string $name,
        public array $validation = [],
        public mixed $default = null,
        public int $mode = 0,
    ) {
    }

    /**
     * @param  scalar  $value
     * @return string[]
     */
    public function format(float|bool|int|string|null $value): array
    {
        return [(string) $value];
    }
}
