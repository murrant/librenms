<?php

/**
 * UpdateServiceRequest.php
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

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['sometimes', 'integer'],
            'service_type' => ['sometimes', 'string', 'max:255'],
            'service_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_desc' => ['sometimes', 'nullable', 'string'],
            'service_param' => ['sometimes', 'nullable', 'string'],
            'service_ip' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_ignore' => ['sometimes', 'boolean'],
            'service_disabled' => ['sometimes', 'boolean'],
            'service_status' => ['sometimes', 'integer', 'between:0,2'],
            'service_message' => ['sometimes', 'nullable', 'string'],
            'service_ds' => ['sometimes', 'nullable', 'string', 'max:255'],
            'service_template_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
