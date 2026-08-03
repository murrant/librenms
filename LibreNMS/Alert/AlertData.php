<?php

/**
 * AlertData.php
 *
 * Alert Data DTO
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

namespace LibreNMS\Alert;

use App\Facades\DeviceCache;
use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Models\AlertLog;
use App\Models\AlertTemplate;
use App\Models\Device;
use ArrayAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LibreNMS\Enum\AlertRuleOperationPhase;
use LibreNMS\Enum\AlertState;
use LibreNMS\Exceptions\RrdException;
use LibreNMS\Polling\ConnectivityHelper;
use LibreNMS\Util\Number;
use LibreNMS\Util\Time;

/**
 * @implements ArrayAccess<string, mixed>
 */
class AlertData implements ArrayAccess
{
    public function __construct(
        // Device info
        public ?string $hostname = null,
        public ?string $sysName = null,
        public ?string $display = null,
        public ?string $sysDescr = null,
        public ?string $sysContact = null,
        public ?string $os = null,
        public ?string $type = null,
        public ?string $ip = null,
        /** @var array<int, string> */
        public array $device_groups = [],
        public ?string $hardware = null,
        public ?string $version = null,
        public ?string $serial = null,
        public ?string $features = null,
        public ?string $location = null,
        public ?int $uptime = null,
        public ?string $uptime_short = null,
        public ?string $uptime_long = null,
        public ?string $description = null,
        public ?string $notes = null,
        public bool $status = false,
        public ?string $status_reason = null,
        public ?int $device_id = null,
        // Alert info
        public ?string $alert_notes = null,
        public string|int|null $rule_id = null,
        public string|int|null $id = null,
        public ?string $proc = null,
        public ?string $title = null,
        public ?array $faults = null,
        public ?string $elapsed = null,
        public ?string $builder = null,
        public string|int|null $uid = null,
        public string|int|null $alert_id = null,
        public ?string $severity = null,
        public ?string $rule = null,
        public ?string $name = null,
        public ?string $timestamp = null,
        public ?array $contacts = null,
        public int $state = AlertState::ACTIVE,
        public int $alerted = 0,
        public ?AlertTemplate $template = null,
        public ?string $operation_phase = null,
        public int $escalation_step = 1,
        // Conditional - ping data
        public ?int $ping_timestamp = null,
        public ?float $ping_loss = null,
        public int|float|null $ping_min = null,
        public int|float|null $ping_max = null,
        public int|float|null $ping_avg = null,
        public ?string $debug = null,
        // Conditional - alert processing
        public ?array $diff = null,
        public ?string $transport = null,
        public ?string $transport_name = null,
        public ?self $alert = null,
        public ?string $msg = null,
        public ?string $string = null,
        // Collections
        public ?Collection $applications = null,
        public ?array $applications_metrics = null,
    ) {
    }

    public static function describe(array $results): ?self
    {
        $device = DeviceCache::get($results['device_id']);
        $device->loadMissing(['applications.metrics', 'groups']);

        $applications = $device->applications->groupBy('app_type');

        $data = new self(
            hostname: $device->hostname,
            sysName: $device->sysName,
            display: $device->display,
            sysDescr: $device->sysDescr,
            sysContact: $device->sysContact,
            os: $device->os,
            type: $device->type,
            ip: $device->ip,
            device_groups: $device->groups->pluck('name', 'id')->all(),
            hardware: $device->hardware,
            version: $device->version,
            serial: $device->serial,
            features: $device->features,
            location: (string) $device->location,
            uptime: $device->uptime,
            uptime_short: Time::formatInterval($device->uptime, true),
            uptime_long: Time::formatInterval($device->uptime),
            description: $device->purpose,
            notes: $device->notes,
            status: $device->status,
            status_reason: $device->status_reason,
            device_id: $device->device_id,
            alert_notes: $results['note'],
            rule_id: $results['rule_id'],
            id: $results['id'],
            proc: $results['proc'],
            applications: $applications,
            applications_metrics: $applications->map(fn ($instances) => $instances->map(
                fn ($application) => $application->metrics->mapWithKeys(fn ($metric) => [
                    $metric->metric => ['value' => $metric->value, 'value_prev' => $metric->value_prev],
                ])->all()
            )->all())->all(),
        );

        self::addPingData($data, $device);

        $details = $results['details'];
        $template = (new Template)->getTemplate($data);
        $state = (int) $results['state'];

        if ($state >= AlertState::ACTIVE) {
            $data->title = ($template->title ?: "Alert for device {$data->display} - {$results['name']}") . match ($state) {
                AlertState::ACKNOWLEDGED => ' Has been acknowledged',
                AlertState::WORSE => ' Has worsened',
                AlertState::BETTER => ' Has improved',
                AlertState::CHANGED => ' changed',
                default => '',
            };
            $data->faults = self::formatFaults($details['rule'], ' = ');
            $data->elapsed = Time::formatInterval(time() - strtotime((string) $results['time_logged']), true) ?: 'none';
            if (! empty($details['diff'])) {
                $data->diff = $details['diff'];
            }
        } elseif ($state === AlertState::RECOVERED) {
            $previous = AlertLog::query()
                ->select(['id', 'time_logged', 'details'])
                ->whereNotIn('state', [AlertState::ACKNOWLEDGED, AlertState::RECOVERED])
                ->where('rule_id', $results['rule_id'])
                ->where('device_id', $results['device_id'])
                ->where('id', '<', $results['id'])
                ->latest('id')
                ->first();
            if ($previous === null) {
                return null;
            }

            $details = $previous->details ?? [];
            $details['count'] = 0;
            $previous->update(['details' => $details]);

            $data->title = $template->title_rec ?: "Device {$data->display} recovered from " . ($results['name'] ?: $results['rule']);
            $data->elapsed = Time::formatInterval(strtotime((string) $results['time_logged']) - strtotime((string) $previous->time_logged), true) ?: 'none';
            $data->id = $previous->id;
            $data->faults = self::formatFaults($details['rule'], ' => ');
        } else {
            return null;
        }

        $operationPhase = AlertUtil::mapAlertStateToOperationPhase($state);
        $data->builder = $results['builder'];
        $data->uid = $results['id'];
        $data->alert_id = $results['alert_id'];
        $data->severity = $results['severity'];
        $data->rule = $results['builder']; // backwards compatibility for old rules
        $data->name = $results['name'];
        $data->timestamp = $results['time_logged'];
        $data->contacts = $details['contacts'];
        $data->state = $state;
        $data->alerted = $results['alerted'];
        $data->template = $template;
        $data->operation_phase = $operationPhase;
        $data->escalation_step = $operationPhase === AlertRuleOperationPhase::PROBLEM ? max(1, (int) ($details['count'] ?? 0)) : 1;

        return $data;
    }

    private static function addPingData(self $data, Device $device): void
    {
        if (! (new ConnectivityHelper($device))->icmpIsEnabled()) {
            return;
        }

        try {
            $lastPing = Rrd::lastUpdate(Rrd::name($device->hostname, 'icmp-perf'));
            if ($lastPing) {
                $data->ping_timestamp = $lastPing->timestamp;
                $data->ping_loss = Number::calculatePercent($lastPing->get('xmt') - $lastPing->get('rcv'), $lastPing->get('xmt'));
                $data->ping_min = $lastPing->get('min');
                $data->ping_max = $lastPing->get('max');
                $data->ping_avg = $lastPing->get('avg');
                $data->debug = 'unsupported';
            }
        } catch (RrdException $e) {
            Log::error("Error getting last ping for device {$device->hostname}: {$e->getMessage()}");
        }
    }

    private static function formatFaults(array $incidents, string $separator): array
    {
        return collect($incidents)->mapWithKeys(function (array $incident, int $index) use ($separator): array {
            $incident['string'] = collect($incident)
                ->filter(fn ($value, $key) => ! empty($value)
                    && $key !== 'device_id'
                    && substr_count((string) $key, '_') <= 1
                    && preg_match('/id|desc|msg/i', (string) $key))
                ->map(fn ($value, $key) => $key . $separator . $value . '; ')
                ->implode('');

            return [$index + 1 => $incident];
        })->all();
    }

    public static function testData(Device $device, array $faults = []): self
    {
        return new self(
            hostname: $device->hostname,
            sysName: $device->sysName,
            display: $device->display,
            sysDescr: $device->sysDescr,
            sysContact: $device->sysContact,
            os: $device->os,
            type: $device->type,
            ip: $device->ip,
            hardware: $device->hardware,
            version: $device->version,
            serial: $device->serial,
            features: $device->features,
            location: (string) $device->location,
            uptime: $device->uptime,
            uptime_short: Time::formatInterval($device->uptime, true),
            uptime_long: Time::formatInterval($device->uptime),
            description: $device->purpose,
            notes: $device->notes,
            status: $device->status,
            status_reason: $device->status_reason,
            device_id: $device->device_id,
            alert_notes: 'This is the note for the test alert',
            rule_id: '000',
            id: '000',
            proc: 'This is the procedure for the test alert',
            title: 'Testing transport from ' . LibrenmsConfig::get('project_name'),
            faults: $faults,
            elapsed: '11s',
            builder: '{}',
            uid: '000',
            alert_id: '000',
            severity: 'critical',
            rule: 'macros.device = 1',
            name: 'Test-Rule',
            timestamp: date('Y-m-d H:i:s'),
            contacts: AlertUtil::getContacts([$device->toArray()]),
            state: AlertState::ACTIVE,
            alerted: 0,
            msg: 'This is a test alert',
            string: '#1: test => string;',
        );
    }

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Magic getter for Blade template access.
     * Returns the property value or an error message for invalid property names.
     */
    public function __get(string $key): mixed
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return "$key is not a valid \$alert data name";
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->$offset = null;
    }
}
