<?php

/**
 * SaveTestData.php
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

namespace App\Console\Commands;

use App\Console\LnmsCommand;
use App\Facades\LibrenmsConfig;
use LibreNMS\Exceptions\InvalidModuleException;
use LibreNMS\Util\ModuleTestHelper;
use LibreNMS\Util\Snmpsim;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class DevSaveCommand extends LnmsCommand
{
    /**
     * Developer only command (hidden on production)
     */
    protected $developer = true;

    /**
     * The name and signature of the console command.
     */
    protected $name = 'dev:save';

    public function __construct()
    {
        parent::__construct();

        $this->addArgument('os', InputArgument::OPTIONAL);
        $this->addOption('modules', 'm', InputOption::VALUE_REQUIRED, default: 'all');
        $this->addOption('no-save', null, InputOption::VALUE_NONE);
        $this->addOption('file', 'f', InputOption::VALUE_REQUIRED);
    }

    public function handle(): int
    {
        $this->configureOutputOptions();

        $osArg = $this->argument('os');
        $osName = null;
        $variant = null;
        if (! empty($osArg)) {
            if (str_contains($osArg, '_')) {
                [$osName, $variant] = explode('_', $osArg, 2);
            } else {
                $osName = $osArg;
            }
        }
        $modulesInput = (string) ($this->option('modules') ?? 'all');
        $noSave = (bool) $this->option('no-save');
        $outputFile = $this->option('file');

        // Build modules array
        if ($modulesInput === 'all' || $modulesInput === '') {
            $modules = [];
        } else {
            $modules = array_filter(array_map('trim', explode(',', $modulesInput)));
        }

        // Build list of OS/variants to process
        if (! empty($osName) && ! empty($variant)) {
            $osList = [$osName . '_' . $variant => [$osName, $variant]];
        } else {
            $osList = ModuleTestHelper::findOsWithData($modules, $osName);
        }

        if (empty($osList)) {
            $this->error(__('commands.dev:save.no_snmprec'));

            return 1;
        }

        if (! empty($outputFile)) {
            if (count($osList) !== 1) {
                $this->error(__('commands.dev:save.file_single_only'));
                $this->line(__('commands.dev:save.multiple_found', ['count' => count($osList)]));

                return 1;
            }
        }

        // Start snmpsim
        $snmpsim = new Snmpsim();
        $snmpsim->setupVenv(true);
        $snmpsim->start();
        $this->line(__('commands.dev:save.waiting_snmpsim'));
        $snmpsim->waitForStartup();

        if (! $snmpsim->isRunning()) {
            $this->error(__('commands.dev:save.failed_start_snmpsim'));
            $this->line($snmpsim->getErrorOutput());

            return 1;
        }

        $this->newLine();

        $result = 0;

        try {
            foreach ($osList as $parts) {
                [$targetOs, $targetVariant] = $parts;
                $this->line(__('commands.dev:save.os') . ': ' . $targetOs);
                $this->line(__('commands.dev:save.module') . ': ' . ($modulesInput ?: 'all'));
                if (! empty($targetVariant)) {
                    $this->line(__('commands.dev:save.variant') . ': ' . $targetVariant);
                }
                $this->newLine();

                // refresh config
                LibrenmsConfig::invalidateAndReload();

                $tester = new ModuleTestHelper($modules, $targetOs, $targetVariant);
                if (! $noSave && ! empty($outputFile)) {
                    $tester->setJsonSavePath($outputFile);
                }

                $testData = $tester->generateTestData($snmpsim->ip, $snmpsim->port, $noSave);

                if ($noSave) {
                    // print_r style output
                    $this->line(json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
        } catch (InvalidModuleException $e) {
            $this->error($e->getMessage());
            $result = 1;
        } finally {
            $snmpsim->stop();
        }

        return $result;
    }
}
