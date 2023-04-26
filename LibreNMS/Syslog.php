<?php
/**
 * Syslog.php
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

namespace LibreNMS;

use App\Facades\DeviceCache;
use App\Models\Device;
use App\Models\Ipv4Address;
use Illuminate\Support\Arr;

class Syslog
{
    private array $host_map;
    private array $filter;
    private array $host_xlate;
    private bool $hooks_enabled;

    public function __construct() {
        $this->host_map = [];
        $this->filter = Arr::wrap(Config::get('syslog_filter'));
        $this->host_xlate = Arr::wrap(Config::get('syslog_xlate'));
        $this->hooks_enabled = (bool) Config::get('enable_syslog_hooks', false);
    }

    public function process(array $entry, bool $update = true) {
        foreach ($this->filter as $bi) {
            if (isset($entry['msg']) && str_contains($entry['msg'], $bi)) {
                return $entry;
            }
        }

        $entry['host'] = preg_replace('/^::ffff:/', '', $entry['host']); // remove ipv6 socket prefix for ipv4
        $entry['host'] = $this->host_xlate[$entry['host']] ?? $entry['host']; // translate host based on config

        $device = $this->findHost($entry['host']);

        if ($device->exists) {
            $entry['device_id'] = $device->device_id;
            $this->executeHooks($device, $entry);
            $entry = $this->handleOsSpecificTweaks($device, $entry);

            // handle common case fields were msg is missing
            if (! isset($entry['program'])) {
                $entry['program'] = $entry['msg'];
                unset($entry['msg']);
            }

            if (isset($entry['program'])) {
                $entry['program'] = strtoupper($entry['program']);
            }

            $entry = array_map(fn($value) => is_string($value) ? trim($value) : $value, $entry);

            if ($update) {
                \App\Models\Syslog::create($entry);
            }
        }

        return $entry;
    }

    public function findHost(string $host): Device
    {
        if (empty($this->host_map[$host])) {
            // try hostname
            $device = DeviceCache::getByField(['hostname', 'sysName', 'ip'], $host);

            if (! $device->exists) {
                // If failed, try by IPs on interfaces
                $device_id = Ipv4Address::query()->leftJoin('ports', 'ipv4_addresses.port_id', '=', 'ports.port_id')
                    ->where('ipv4_address', $host)->value('device_id');
                $device = DeviceCache::get($device_id);
            }

            $this->host_map[$host] = $device->device_id; // save to map
        }

        return DeviceCache::get($this->host_map[$host]);
    }

    private function executeHooks(Device $device, array $entry): void
    {
        if ($this->hooks_enabled && is_array($hooks = Config::getOsSetting($device->os, 'syslog_hook'))) {
            foreach ($hooks as $hook) {
                $syslogprogmsg = $entry['program'] . ': ' . $entry['msg'];
                if ((isset($hook['script'])) && (isset($hook['regex'])) && preg_match($hook['regex'], $syslogprogmsg)) {
                    shell_exec(escapeshellcmd($hook['script']) . ' ' . escapeshellarg($device->hostname) . ' ' . escapeshellarg($device->os) . ' ' . escapeshellarg($syslogprogmsg) . ' >/dev/null 2>&1 &');
                }
            }
        }
    }

    private function handleOsSpecificTweaks(Device $device, array $entry): array
    {
        // ios like
        if (in_array($device->os, ['ios', 'iosxe', 'catos'])) {
            // multipart message
            if (strpos($entry['msg'], ':') !== false) {
                $timestamp_prefix = '([\*\.]?[A-Z][a-z]{2} \d\d? \d\d:\d\d:\d\d(.\d\d\d)?( [A-Z]{3})?: )?';
                $program_match = '(?<program>%?[A-Za-z\d\-_]+(:[A-Z]* %[A-Z\d\-_]+)?)';
                $message_match = '(?<msg>.*)';
                if (preg_match('/^' . $timestamp_prefix . $program_match . ': ?' . $message_match . '/', $entry['msg'], $matches)) {
                    $entry['program'] = $matches['program'];
                    $entry['msg'] = $matches['msg'];
                }
            } else {
                // if this looks like a program (no groups of 2 or more lowercase letters), move it to program
                if (! preg_match('/[(a-z)]{2,}/', $entry['msg'])) {
                    $entry['program'] = $entry['msg'];
                    unset($entry['msg']);
                }
            }

            return $entry;
        }

        if ($device->os == 'linux') {
            // Cisco WAP200 and similar
            if ($device->version == 'Point') {
                if (preg_match('#Log: \[(?P<program>.*)\] - (?P<msg>.*)#', $entry['msg'], $matches)) {
                    $entry['msg'] = $matches['msg'];
                    $entry['program'] = $matches['program'];
                }

                return $entry;
            }

            // regular linux
            // pam_krb5(sshd:auth): authentication failure; logname=root uid=0 euid=0 tty=ssh ruser= rhost=123.213.132.231
            // pam_krb5[sshd:auth]: authentication failure; logname=root uid=0 euid=0 tty=ssh ruser= rhost=123.213.132.231
            if (empty($entry['program']) && isset($entry['msg']) && preg_match('#^(?P<program>([^(:]+\([^)]+\)|[^\[:]+\[[^\]]+\])) ?: ?(?P<msg>.*)$#', $entry['msg'], $matches)) {
                $entry['msg'] = $matches['msg'];
                $entry['program'] = $matches['program'];
            } elseif (empty($entry['program']) && ! empty($entry['facility'])) {
                // SYSLOG CONNECTION BROKEN; FD='6', SERVER='AF_INET(123.213.132.231:514)', time_reopen='60'
                // pam_krb5: authentication failure; logname=root uid=0 euid=0 tty=ssh ruser= rhost=123.213.132.231
                // Disabled because broke this:
                // diskio.c: don't know how to handle 10 request
                // elseif($pos = strpos($entry['msg'], ';') or $pos = strpos($entry['msg'], ':')) {
                // $entry['program'] = substr($entry['msg'], 0, $pos);
                // $entry['msg'] = substr($entry['msg'], $pos+1);
                // }
                // fallback, better than nothing...
                $entry['program'] = $entry['facility'];
            }

            return $entry;
        }

        // HP ProCurve
        if ($device->os == 'procurve') {
            if (preg_match('/^(?P<program>[A-Za-z]+): {2}(?P<msg>.*)/', $entry['msg'], $matches)) {
                $entry['msg'] = $matches['msg'] . ' [' . $entry['program'] . ']';
                $entry['program'] = $matches['program'];
            }

            return $entry;
        }

        // Zwwall sends messages without all the fields, so the offset is wrong
        if ($device->os == 'zywall') {
            $msg = preg_replace('/" /', '";', stripslashes($entry['program'] . ':' . $entry['msg']));
            $msg = str_getcsv($msg, ';');
            $entry['program'] = null;
            foreach ($msg as $param) {
                [$var, $val] = explode('=', $param);
                if ($var == 'cat') {
                    $entry['program'] = str_replace('"', '', $val);
                }
            }
            $entry['msg'] = join(' ', $msg);

            return $entry;
        }

        return $entry;
    }

    public function getMap(): array{
        return $this->host_map;
    }

    public function reset()
    {
        Config::reload();
        DeviceCache::flush();
        $this->__construct();
    }
}
