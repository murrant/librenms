<?php

/**
 * FillDerivedPeriodFields.php
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
 * @copyright  2023 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Polling;

use Illuminate\Database\Eloquent\Model;

trait FillsDerivedPeriodFields
{
    protected function fillDerivedPeriodFields(Model $model, array $fields, int $period = 0): void
    {
        foreach ($fields as $field) {
            if ($model->isDirty($field)) {
                $current = $model->getAttribute($field);
                $prev = $model->getOriginal($field);
                $model->setAttribute("{$field}_prev", $prev);

                // don't fill delta and rate if the previous value was null
                if ($prev === null || $prev == $current) {
                    continue;
                }

                $diff = $current - $prev;
                $model->setAttribute("{$field}_delta", $diff);

                if ($period) {
                    $rate = $diff / $period;
                    $model->setAttribute("{$field}_rate", $rate);
                }
            }
        }
    }
}
