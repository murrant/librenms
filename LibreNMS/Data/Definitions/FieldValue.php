<?php

/**
 * FieldDef.php
 *
 * This is a decorator to add extra data to a field value
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

namespace LibreNMS\Data\Definitions;

class FieldValue
{
    public function __construct(
        public readonly ?string $value,
        protected readonly StorageType $storage = StorageType::GAUGE,
        protected ?int $step = null,
        protected ?int $max = null,
        protected ?int $min = 0,
    ) {
    }

    public function step(int $step): self
    {
        $this->step = $step;

        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;

        return $this;
    }

    public static function asInt(?string $value, StorageType $storage = StorageType::GAUGE): static
    {
        if ($value !== null) {
            $pos = strpos($value, '.');
            if ($pos !== false) {
                $value = substr($value, 0, $pos);
            }
        }

        return new static($value, $storage);
    }

    public static function asFloat(?string $value, StorageType $storage = StorageType::GAUGE): static
    {
        if ($value !== null && ! str_contains($value, '.')) {
            $value .= '.0';
        }

        return new static($value, $storage);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
