<?php
/**
 * Poller.php
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
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS;

use App\Events\DevicePolled;
use App\Jobs\PollDevice;
use App\Models\Device;
use Illuminate\Support\Facades\Event;
use LibreNMS\Polling\Result;
use Psr\Log\LoggerInterface;

class Poller
{
    /** @var string */
    private $device_spec;
    /** @var array */
    private $module_override;

    /** @var int */
    private $current_device_id;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(string $device_spec, array $module_override, LoggerInterface $logger)
    {
        $this->device_spec = $device_spec;
        $this->module_override = $module_override;
        $this->logger = $logger;
    }

    public function poll(): Result
    {
        $results = new Result;

        $this->logger->info("Starting polling run:\n");

        // listen for the device polled events to mark the device completed
        Event::listen(function (DevicePolled $event) use ($results) {
            if ($event->device->device_id == $this->current_device_id) {
                $results->markCompleted($event->device->status);
            }
        });

        foreach (Device::whereDeviceSpec($this->device_spec)->pluck('device_id') as $device_id) {
            $this->current_device_id = $device_id;
            $results->markAttempted();
            PollDevice::dispatchSync($device_id, $this->module_override);
        }

        return $results;
    }
}
