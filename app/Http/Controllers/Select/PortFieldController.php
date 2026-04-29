<?php

/**
 * PortFieldController.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Http\Controllers\Select;

use App\Models\Port;
use Illuminate\Database\Eloquent\Builder;
use LibreNMS\Util\Number;
use LibreNMS\Util\Rewrite;

class PortFieldController extends SelectController
{
    /**
     * Defines validation rules (will override base validation rules for select2 responses too)
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'field' => 'required|in:ifType,ifSpeed,port_descr_type',
            'device' => 'nullable|int',
        ];
    }

    /**
     * Defines fields that can be used as filters
     *
     * @param  $request
     * @return string[]
     */
    protected function filterFields($request)
    {
        return [
            'device_id' => 'device',
        ];
    }

    /**
     * Defines search fields will be searched in order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function searchFields($request)
    {
        return [$request->string('field')];
    }

    /**
     * Defines the base query for this resource
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function baseQuery($request)
    {
        $this->idField = $request->string('field');
        return Port::hasAccess($request->user())
            ->whereNotNull($this->idField)
            ->select($this->idField)
            ->distinct()
            ->orderBy($this->idField);
    }

    public function formatItem($model)
    {
        $field = array_key_first($model->getAttributes());
        $value = array_first($model->getAttributes());
        \Debugbar::log("field: $field, value: $value");

        return [
            'id' => $value,
            'text' => match($field) {
                'ifSpeed' => Number::formatSi($value, suffix: 'bps'),
                'ifType' => Rewrite::normalizeIfType($value),
                default => $value,
            },
        ];
    }
}
