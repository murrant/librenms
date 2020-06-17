<?php
/**
 * Snmpsim.php
 *
 * Light wrapper around Snmpsim
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
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Util;

use LibreNMS\Config;
use Symfony\Component\Process\Process;

class Snmpsim
{
    private $snmprec_dir;
    private $ip;
    private $port;
    private $log;
    /** @var Process $proc */
    private $proc;

    public function __construct($ip = '127.1.6.1', $port = 1161, $log = '/tmp/snmpsimd.log')
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->log = $log;
        $this->snmprec_dir = Config::get('install_dir') . "/tests/snmpsim/";
    }

    /**
     * Run snmpsimd and fork it into the background
     * Captures all output to the log
     *
     * @param int $wait Wait for x seconds after starting before returning
     */
    public function fork($wait = 2)
    {
        if ($this->isRunning()) {
            echo "Snmpsim is already running!\n";
            return;
        }

        $this->proc = $this->makeProcess();

        if (isCli()) {
            echo "Starting snmpsim listening on {$this->ip}:{$this->port}... \n";
            d_echo($this->proc->getCommandLine());
        }

        $this->proc->start();

        if ($wait) {
            sleep($wait);
        }

        if (isCli() && !$this->proc->isRunning()) {
            // if starting failed, run snmpsim again and output to the console and validate the data
            $this->makeProcess(false, ['--validate-data'])
                ->setTty(true)
                ->setTimeout(30)
                ->run();

            if (!is_executable($this->findSnmpsimd())) {
                echo "\nCould not find snmpsim, you can install it with 'pip install snmpsim'.  If it is already installed, make sure snmpsimd or snmpsimd.py is in PATH\n";
            } else {
                echo "\nFailed to start Snmpsim. Scroll up for error.\n";
            }
            exit;
        }
    }

    public function stop()
    {
        if (isset($this->proc)) {
            if ($this->proc->isRunning()) {
                $this->proc->stop();
            }
            unset($this->proc);
        }
    }

    /**
     * Run snmpsimd but keep it in the foreground
     * Outputs to stdout
     *
     */
    public function run()
    {
        $this->proc = $this->makeProcess(false)
            ->setTty(true);
        echo "Starting snmpsim listening on {$this->ip}:{$this->port}... \n";
        d_echo($this->proc->getCommandLine());
        $this->proc->run();
    }

    public function isRunning()
    {
        if (isset($this->proc)) {
            return $this->proc->isRunning();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->snmprec_dir;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Generate the command for snmpsimd
     *
     * @param bool $with_log
     * @return array
     */
    private function getCmd($with_log = true)
    {
        $cmd = [
            $this->findSnmpsimd(),
            "--data-dir={$this->snmprec_dir}",
            "--agent-udpv4-endpoint={$this->ip}:{$this->port}"
        ];

        if (is_null($this->log)) {
            $cmd[] = "--logging-method=null";
        } elseif ($with_log) {
            $cmd[] = "--logging-method=file:{$this->log}";
        }

        return $cmd;
    }

    private function findSnmpsimd()
    {
        $cmd = Config::locateBinary('snmpsimd');
        if (!is_executable($cmd)) {
            $cmd = Config::locateBinary('snmpsimd.py');
        }
        return $cmd;
    }

    private function makeProcess($log = true, $options = [])
    {
        $snmpsim = new Process(array_merge($this->getCmd($log), $options));
        $snmpsim->setTimeout(0);
        return $snmpsim;
    }
}
