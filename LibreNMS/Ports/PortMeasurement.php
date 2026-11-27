<?php

namespace LibreNMS\Ports;

use App\Facades\LibrenmsConfig;
use App\Facades\Rrd;
use App\Models\Device;
use App\Models\Eventlog;
use App\Models\Port;
use Brick\Math\BigInteger;
use Illuminate\Support\Arr;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\Data\DataStorageInterface;
use LibreNMS\RRD\PortRrd;
use LibreNMS\RRD\RrdDefinition;

final readonly class PortMeasurement
{
    // The first six counters live on ports, the rest on ports_statistics.
    public const array BASE = ['ifInOctets', 'ifOutOctets', 'ifInErrors', 'ifOutErrors', 'ifInUcastPkts', 'ifOutUcastPkts'];
    public const array COUNTERS = [...self::BASE, 'ifInNUcastPkts', 'ifOutNUcastPkts', 'ifInDiscards', 'ifOutDiscards',
        'ifInUnknownProtos', 'ifInBroadcastPkts', 'ifOutBroadcastPkts', 'ifInMulticastPkts', 'ifOutMulticastPkts'];
    public const array ETHERLIKE = ['AlignmentErrors', 'FCSErrors', 'SingleCollisionFrames', 'MultipleCollisionFrames',
        'SQETestErrors', 'DeferredTransmissions', 'LateCollisions', 'ExcessiveCollisions', 'InternalMacTransmitErrors',
        'CarrierSenseErrors', 'FrameTooLongs', 'InternalMacReceiveErrors', 'SymbolErrors'];

    private RrdDefinition $definition;

    public function __construct()
    {
        $this->definition = RrdDefinition::make();
        foreach (self::COUNTERS as $field) {
            $this->definition->addDataset(strtoupper(substr($field, 2)), 'DERIVE', 0, 12500000000);
        }
    }

    public static function powerFields(mixed $allocated, mixed $available, mixed $consumption, mixed $peak): array
    {
        return ['PortPwrAllocated' => $allocated, 'PortPwrAvailable' => $available,
            'PortConsumption' => $consumption, 'PortMaxPwrDrawn' => $peak];
    }

    /** Update live link state and return whether a speed increase requires RRD tuning. */
    public static function updateLink(Device $device, Port $port, array $row): bool
    {
        $data = Arr::only($row, ['ifAdminStatus', 'ifOperStatus', 'ifDuplex']);
        if (isset($row['dot3StatsDuplexStatus'])) {
            $data['ifDuplex'] = $row['dot3StatsDuplexStatus'];
        }
        if (is_numeric($row['ifLastChange'] ?? null)) {
            $data['ifLastChange'] = $row['ifLastChange'];
        }
        $speed = $row['ifSpeed'] ?? null;
        if (is_numeric($row['ifHighSpeed'] ?? null) && ($row['ifHighSpeed'] > 0 || ! $speed)) {
            $speed = (int) $row['ifHighSpeed'] * 1000000;
        }
        $speed = $device->getAttrib('ifSpeed:' . $port->ifName, $speed);
        if (is_numeric($speed)) {
            $data['ifSpeed'] = $speed;
        }
        $tune = false;
        if ($speed > $port->ifSpeed) {
            $setting = $device->getAttrib('ifName_tune:' . $port->ifName)
                ?? $device->getAttrib('override_rrdtool_tune') ?? LibrenmsConfig::get('rrdtool_tune');
            $tune = filter_var($setting, FILTER_VALIDATE_BOOL);
        }
        $port->fill($data);
        foreach (['ifOperStatus', 'ifAdminStatus', 'ifSpeed'] as $field) {
            if (isset($data[$field])) {
                $port->{$field . '_prev'} = $port->getRawOriginal($field) ?? $data[$field];
            }
        }
        if ($port->isDirty()) {
            foreach (array_intersect_key($port->getDirty(), $data) as $field => $value) {
                Eventlog::log("$field: " . ($port->getRawOriginal($field) ?? 'NULL') . ' -> ' . ($value ?? 'NULL'), $device, 'interface', Severity::Notice, $port->port_id);
            }
            $port->save();
        }

        return $tune;
    }

    /** Build the main and extended counter rows from the last committed sample. */
    public static function sample(Port $port, array $row, int $time): array
    {
        $period = $port->poll_time ? $time - $port->poll_time : null;
        $previous = $port->getRawOriginal() + ($port->statistics->first()?->getRawOriginal() ?? []);
        $base = ['poll_time' => $time, 'poll_prev' => $port->poll_time, 'poll_period' => $period];
        $extended = [];
        foreach (self::COUNTERS as $field) {
            $hc = $row['ifHC' . substr($field, 2)] ?? null;
            $current = ctype_digit((string) $hc) ? $hc : ($row[$field] ?? null);
            $current = ctype_digit((string) $current) ? $current : null;
            $old = $previous[$field] ?? null;
            $delta = null;
            if ($period > 0 && $old !== null && $current !== null) {
                // Counter64 values can exceed PHP_INT_MAX. A reset starts a new baseline.
                $difference = BigInteger::of($current)->minus((string) $old);
                $delta = $difference->isNegative() ? '0' : (string) $difference;
            }
            $values = [
                $field => $current,
                $field . '_prev' => $old,
                $field . '_delta' => $delta,
                $field . '_rate' => $delta === null ? null : round((float) $delta / $period),
            ];
            if (in_array($field, self::BASE, true)) {
                $base += $values;
            } else {
                $extended += $values;
            }
        }

        return [$base, $extended];
    }

    public function store(DataStorageInterface $store, Device $device, Port $port, array $counters, array $row, bool $tune): void
    {
        $fields = [];
        foreach (self::COUNTERS as $field) {
            $ds = strtoupper(substr($field, 2));
            $fields[$ds] = $counters[$field];
        }
        foreach (self::BASE as $field) {
            $fields[$field . '_rate'] = $counters[$field . '_rate'];
        }
        foreach (['In', 'Out'] as $direction) {
            $delta = $counters["if{$direction}Octets_delta"];
            $fields["if{$direction}Bits_rate"] = $delta === null ? null : round(8 * $delta / $counters['poll_period']);
        }

        $name = Rrd::portName($port->port_id);
        $limits = $tune ? PortRrd::tuneLimits((int) $port->ifSpeed) : [];
        $path = $tune ? Rrd::name($device->hostname, $name) : '';
        if ($limits && Rrd::tune($path, $limits)) {
            $limits = [];
        }
        $store->put($device, 'ports', $port->only(['ifName', 'ifAlias', 'ifIndex', 'port_descr_type']) + [
            'rrd_name' => $name, 'rrd_def' => $this->definition,
        ], $fields);
        if ($limits) {
            Rrd::tune($path, $limits);
        }

        if (isset($row['poe'])) {
            self::storeExtra($store, $device, $port, 'poe', 'poe', 'GAUGE', $row['poe']);
        }
        if (isset($row['cie'])) {
            self::storeExtra($store, $device, $port, 'drops', 'cie', 'DERIVE', $row['cie']);
        }
        if (isset($row['dot3StatsIndex']) && $port->ifType === 'ethernetCsmacd') {
            $counters = [];
            foreach (self::ETHERLIKE as $field) {
                $counters['dot3Stats' . $field] = $row['dot3Stats' . $field] ?? null;
            }
            self::storeExtra($store, $device, $port, 'dot3', 'dot3', 'COUNTER', $counters);
        }
    }

    private static function storeExtra(DataStorageInterface $store, Device $device, Port $port, string $measurement, string $suffix, string $type, array $fields): void
    {
        $definition = RrdDefinition::make();
        $tags = [];
        if ($measurement === 'dot3') {
            // Other stores use the full MIB names. Existing EtherLike RRDs use the suffixes in this order.
            $definition->disableNameChecking();
            $tags['rrd_oldname'] = 'etherlike-' . $port->ifIndex;
            foreach (self::ETHERLIKE as $field) {
                $definition->addDataset($field, $type, null, 100000000000);
            }
        } else {
            foreach (array_keys($fields) as $name) {
                $definition->addDataset($name, $type, 0);
            }
        }
        $store->put($device, $measurement, $tags + [
            'ifName' => $port->ifName, 'rrd_name' => Rrd::portName($port->port_id, $suffix), 'rrd_def' => $definition,
        ], $fields);
    }
}
