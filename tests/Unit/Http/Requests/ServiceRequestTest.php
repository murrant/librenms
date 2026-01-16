<?php
/**
 * ServiceRequestTest.php
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

use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use Illuminate\Support\Facades\Validator;

it('store requires device_id and service_type', function (): void {
    $request = app(StoreServiceRequest::class);
    $rules = $request->rules();

    $data = [];
    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())
        ->toContain('device_id', 'service_type');
});

it('store validates optional fields and types', function (): void {
    $request = app(StoreServiceRequest::class);
    $rules = $request->rules();

    $data = [
        'device_id' => 1,
        'service_type' => 'http',
        'service_ignore' => true,
        'service_disabled' => false,
        'service_status' => 2,
        'service_desc' => 'desc',
        'service_param' => 'param',
        'service_ip' => 'example.local',
        'service_message' => 'ok',
        'service_ds' => 'ds',
        'service_template_id' => 5,
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeFalse();
});

it('store rejects invalid types and out of range status', function (): void {
    $request = app(StoreServiceRequest::class);
    $rules = $request->rules();

    $data = [
        'device_id' => 'not-int',
        'service_type' => 123,
        'service_status' => 5,
        'service_ignore' => 'yes',
    ];

    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())
        ->toContain('device_id', 'service_type', 'service_status', 'service_ignore');
});

it('update allows partial updates and validates provided fields', function (): void {
    $request = app(UpdateServiceRequest::class);
    $rules = $request->rules();

    // only updating description
    $data = [
        'service_desc' => 'new description',
    ];
    $validator = Validator::make($data, $rules);
    expect($validator->fails())->toBeFalse();

    // invalid provided field should fail
    $badData = [
        'service_status' => 99,
    ];
    $badValidator = Validator::make($badData, $rules);
    expect($badValidator->fails())->toBeTrue()
        ->and($badValidator->errors()->keys())
        ->toContain('service_status');
});
