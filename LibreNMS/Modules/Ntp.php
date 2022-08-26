<?php
/*
 * Ntp.php
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2022 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Modules;

use App\Models\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LibreNMS\Interfaces\Polling\NtpPolling;
use LibreNMS\OS;
use LibreNMS\RRD\RrdDefinition;

class Ntp implements \LibreNMS\Interfaces\Module
{

    /**
     * @inheritDoc
     */
    public function discover(OS $os)
    {
        if (! $os instanceof NtpPolling) {
            return;
        }

        $stratum = $os->fetchNtpStratum();
        $peers = $os->fetchNtpPeers();

        if ($stratum >= 0 && $peers->isNotEmpty()) {
            $app = Application::firstOrCreate([
                'device_id' => $os->getDeviceId(),
                'app_type' => 'ntp',
            ]);

            $this->updateApp($app, $stratum, $peers);

            // print changes
            $changed = count($app->getOriginal('data')) - $peers->count();
            $same = $peers->count() - $changed;
            if ($same) {
                echo str_repeat('.', $same);
            }
            echo str_repeat($changed < 0 ? '-' : '+', abs($changed));
            echo PHP_EOL;

            $app->save();

            $peers->each(function ($peer) {
                $this->printPeer($peer);
            });
        } else {
            // remove ntp application
            $os->getDevice()->applications()->where('app_type', 'ntp')->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function poll(OS $os)
    {
        if (! $os instanceof NtpPolling) {
            return;
        }

        $app = Application::firstWhere(['device_id' => $os->getDeviceId(), 'app_type' => 'ntp']);

        if ($app === null) {
            return;
        }

        $peers = $os->fetchNtpPeers();
        $this->updateApp($app, $os->fetchNtpStratum(), $peers);
        $app->save();

        $peers->each(function ($peer) use ($os) {
            $this->printPeer($peer);

            $data = Arr::only($peer, ['stratum', 'offset', 'delay', 'dispersion']);

            app('Datastore')->put($os->getDeviceArray(), 'ntp', [
                'rrd_name' => ['ntp', $peer['peer']],
                'rrd_def' => RrdDefinition::make()
                    ->addDataset('stratum', 'GAUGE', 0)
                    ->addDataset('offset', 'GAUGE', 0)
                    ->addDataset('delay', 'GAUGE', 0)
                    ->addDataset('dispersion', 'GAUGE', 0),
                'peer' => $peer['peer'],
            ], $data);
        });
    }

    /**
     * @inheritDoc
     */
    public function cleanup(OS $os)
    {
        $os->getDevice()->applications()->where('app_type', 'ntp')->delete();
    }

    private function printPeer(array $peer)
    {
        echo ' Peer                     Stratum    Offset     Delay      Dispersion' . PHP_EOL;
        printf(' %-24s %-10d %-10s %-10s %-10s' . PHP_EOL, $peer['peer'], $peer['stratum'], $peer['offset'] . 's', $peer['delay'] . 's', $peer['dispersion'] . 's');
    }

    private function updateApp(Application $app, int $stratum, Collection $peers): void
    {
        // fill data
        $app->fill([
            'app_state' => $stratum < 16 ? 'OK' : 'ERROR',
            'data' => $this->populateFields($peers)->values()->all(),
            'app_status' => "Stratum $stratum" . ($stratum >= 16 ? ' unsynchronized' : ''),
        ]);

        // handle app_state_prev
        if($app->isDirty('app_state')) {
            $app->app_state_prev = $app->getOriginal('app_state');
        }
    }

    private function populateFields(Collection $peer): Collection
    {
        return $peer->map(function ($peer) {
            $peer['label'] = $peer['label'] ?? $peer['peer'] . ':' . $peer['port'];

            if ($peer['stratum'] == 16) {
                $peer['status'] = 2;
                $peer['error'] = 'NTP is not in sync';
            } else {
                $peer['status'] = 0;
                $peer['error'] = '';
            }

            // don't store graphing fields in db
            unset($peer['offset'], $peer['delay'], $peer['dispersion']);

            return $peer;
        });
    }
}
