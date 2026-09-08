<?php

/**
 * NetSnmp.php
 *
 * Executes SNMP commands using the Net-SNMP CLI utilities.
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Source\Snmp;

use App\Facades\LibrenmsConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LibreNMS\Enum\SnmpError;
use LibreNMS\Enum\SnmpOidOutput;
use LibreNMS\Enum\SnmpStringOutput;
use LibreNMS\Exceptions\SnmpException;
use LibreNMS\Exceptions\SnmpVersionUnsupportedException;
use LibreNMS\Polling\Method\Config\SnmpConfig;
use LibreNMS\Util\Oid;
use LibreNMS\Util\Rewrite;
use LibreNMS\Util\StringHelpers;
use Symfony\Component\Process\Process;

class NetSnmp implements SnmpBackendInterface, SnmpTranslatorInterface
{
    public const KEY_VALUE_DELIMITER = ' = ';

    /**
     * @param  string[]  $oids
     */
    public function get(SnmpConfig $config, array $oids, SnmpQueryOptions $options): SnmpResponse
    {
        return $this->runCommand($this->buildCli('snmpget', $config, $oids, $options));
    }

    public function walk(SnmpConfig $config, string $oid, SnmpQueryOptions $options): SnmpResponse
    {
        return $this->runCommand($this->buildCli('snmpwalk', $config, [$oid], $options));
    }

    /**
     * @param  string[]  $oids
     */
    public function next(SnmpConfig $config, array $oids, SnmpQueryOptions $options): SnmpResponse
    {
        return $this->runCommand($this->buildCli('snmpgetnext', $config, $oids, $options));
    }

    public function translate(string $oid, SnmpQueryOptions $options): string
    {
        $oidObj = new Oid($oid);

        if ($options->oidFormat == SnmpOidOutput::Numeric && $oidObj->isNumeric()) {
            return Str::start($oid, '.');
        }

        $cmd = [
            LibrenmsConfig::get('snmptranslate', 'snmptranslate'),
            '-M', implode(':', $options->mibDirs ?: [LibrenmsConfig::get('mib_dir')]),
            '-m', implode(':', $options->mibs),
            $options->oidFormat == SnmpOidOutput::Numeric ? '-On' : ($options->oidFormat == SnmpOidOutput::Module ? '-OS' : '-Os'),
        ];

        if (! $oidObj->hasMib() && ! $oidObj->hasNumericRoot()) {
            $cmd[] = '-IR';
        }

        $cmd[] = $oid;

        return $this->runCommand($cmd)->value();
    }

    /**
     * Generate a net-snmp command line
     *
     * @param  string[]  $oids
     * @return string[]
     */
    public function buildCli(string $command, SnmpConfig $config, array $oids, SnmpQueryOptions $options): array
    {
        if ($command === 'snmpwalk' && $config->bulk && $options->allowBulk && $config->version !== 'v1') {
            $command = 'snmpbulkwalk';
        }

        $cmd = [
            LibrenmsConfig::get($command, $command),
            '-M', implode(':', $options->mibDirs ?: [LibrenmsConfig::get('mib_dir')]),
            '-m', implode(':', $options->mibs),
            ...$this->buildAuth($config, $options),
            ...$this->buildOutputFlags($options),
        ];

        if ($command === 'snmpbulkwalk' && $config->maxRepeaters > 0) {
            $cmd[] = "-Cr$config->maxRepeaters";
        }

        if ($options->tolerateUnorderedIndexes) {
            $cmd[] = '-Cc';
        }

        if ($config->timeout > 0 && $config->timeout != 1) {
            array_push($cmd, '-t', (string) $config->timeout);
        }

        if ($config->retries !== 5) {
            array_push($cmd, '-r', (string) $config->retries);
        }

        $hostname = Rewrite::addIpv6Brackets($config->target);
        $cmd[] = "$config->transport:$hostname:$config->port";

        return [...$cmd, ...$oids];
    }

    /**
     * @return string[]
     */
    private function buildOutputFlags(SnmpQueryOptions $options): array
    {
        $opts = '';

        if ($options->quickPrint) {
            $opts .= 'Q';
        }

        if ($options->extendedIndex) {
            $opts .= 'X';
        }

        if (! $options->printUnits) {
            $opts .= 'U';
        }

        if ($options->numericTimeticks) {
            $opts .= 't';
        }

        if ($options->numericEnums) {
            $opts .= 'e';
        }

        if ($options->numericIndexes) {
            $opts .= 'b';
        }

        if ($options->escapeQuotes) {
            $opts .= 'E';
        }

        if ($options->printHexText) {
            $opts .= 'T';
        }

        $opts .= match ($options->stringFormat) {
            SnmpStringOutput::Ascii => 'a',
            SnmpStringOutput::Hex => 'x',
            default => '',
        };

        $opts .= match ($options->oidFormat) {
            SnmpOidOutput::Full => 'f',
            SnmpOidOutput::Suffix => 's',
            SnmpOidOutput::Ucd => 'u',
            SnmpOidOutput::Numeric => 'n',
            default => '',
        };

        $flags = [];

        if ($opts !== '') {
            $flags[] = "-O$opts";
        }

        if ($options->allowUnderscores) {
            $flags[] = '-Pu';
        }

        if (! $options->applyDisplayHints) {
            $flags[] = '-Ih';
        }

        return $flags;
    }

    /**
     * @return string[]
     *
     * @throws SnmpException
     */
    private function buildAuth(SnmpConfig $config, SnmpQueryOptions $options): array
    {
        if ($config->version === 'v2c' || $config->version === 'v1') {
            return [
                "-$config->version",
                '-c',
                $options->context ? "$config->community@$options->context" : (string) $config->community,
            ];
        }

        if ($config->version === 'v3') {
            $auth = match (strtolower((string) $config->authlevel)) {
                'authpriv' => [
                    '-v3',
                    '-l', (string) $config->authlevel,
                    '-x', (string) $config->cryptoalgo,
                    '-X', (string) $config->cryptopass,
                    '-a', (string) $config->authalgo,
                    '-A', (string) $config->authpass,
                    '-u', $config->authname ?: 'root',
                ],
                'authnopriv' => [
                    '-v3',
                    '-l', (string) $config->authlevel,
                    '-a', (string) $config->authalgo,
                    '-A', (string) $config->authpass,
                    '-u', $config->authname ?: 'root',
                ],
                'noauthnopriv' => [
                    '-v3',
                    '-l', (string) $config->authlevel,
                    '-u', $config->authname ?: 'root',
                ],
                default => throw new SnmpException("Unsupported SNMPv3 AuthLevel: $config->authlevel"),
            };

            if ($options->context) {
                array_push($auth, '-n', $options->context);
            }

            return $auth;
        }

        throw new SnmpVersionUnsupportedException($config->version);
    }

    /**
     * @param  string[]  $cliCommand
     */
    private function runCommand(array $cliCommand): SnmpResponse
    {
        $proc = new Process($cliCommand);
        $proc->setTimeout((int) LibrenmsConfig::get('snmp.exec_timeout', 1200));
        $proc->run();

        return $this->parseResponse(
            $proc->getOutput(),
            $proc->getErrorOutput(),
            $proc->getExitCode(),
            $cliCommand,
        );
    }

    /**
     * Parse Net-SNMP CLI output and process metadata into an SnmpResponse.
     *
     * @param  array<int, string>  $command
     */
    public function parseResponse(string $output, string $stderr = '', int $exitCode = 0, array $command = []): SnmpResponse
    {
        $raw = (string) preg_replace('/Wrong Type \(should be .*\): /', '', $output);
        $values = $this->parseOutput($raw);
        $errorData = $this->detectErrors($raw, $stderr, $exitCode, $values);

        if (! empty($errorData['errors'])) {
            Log::debug(sprintf(
                'SNMP query failed. Exit Code: %s Empty: %s Bad String: %s',
                $exitCode,
                var_export(empty($raw), true),
                $errorData['messages'][0] ?? 'not found'
            ));
        }

        $debugInfo = new SnmpDebugInfo(
            command: $command,
            exitCode: $exitCode,
            stderr: $stderr,
            output: $output,
            errors: $errorData['errors'],
            errorMessages: $errorData['messages'],
        );

        return new SnmpResponse($values, $debugInfo, $raw);
    }

    /**
     * Parse raw Net-SNMP CLI text into key-value pairs.
     *
     * @return array<string, string>
     */
    public function parseOutput(string $raw): array
    {
        $inferValueEncoding = ! StringHelpers::isValidUtf8($raw);
        $values = [];
        $line = strtok($raw, PHP_EOL);
        while ($line !== false) {
            if (Str::contains($line, ['at this OID', 'this MIB View', 'End of MIB']) || str_ends_with($line, ' = NULL')) {
                // these occur when we seek past the end of data, usually the end of the response, but grab the next line and continue
                $line = strtok(PHP_EOL);
                continue;
            }

            $parts = explode(self::KEY_VALUE_DELIMITER, $line, 2);
            if (count($parts) == 1) {
                array_unshift($parts, '');
            }
            [$oid, $value] = $parts;

            $line = strtok(PHP_EOL); // get the next line and concatenate multi-line values
            while ($line !== false && ! Str::contains($line, self::KEY_VALUE_DELIMITER)) {
                $value .= PHP_EOL . $line;
                $line = strtok(PHP_EOL);
            }

            // remove extra escapes
            if (LibrenmsConfig::get('snmp.unescape')) {
                $value = stripslashes($value);
            }

            if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
                // unformatted string from net-snmp, remove extra escapes
                $value = trim(stripslashes($value), "\" \n\r");
            } else {
                $value = trim($value);
            }

            $values[$oid] = $inferValueEncoding
                ? StringHelpers::inferEncoding($value)
                : $value;
        }

        return $values;
    }

    /**
     * Detect and classify all error conditions from CLI output, stderr, and exit code.
     *
     * @param  array<string, string>  $values
     * @return array{errors: array<int, SnmpError>, messages: array<int, string>}
     */
    public function detectErrors(string $raw, string $stderr, int $exitCode, array $values = []): array
    {
        $errors = [];
        $messages = [];

        // Check stderr patterns
        if (preg_match('/Timeout: No Response from [^\r\n]*/', $stderr, $m)) {
            $errors[] = SnmpError::Timeout;
            $messages[] = trim($m[0]);
        }

        if (preg_match('/Unknown user name/', $stderr, $m)) {
            $errors[] = SnmpError::AuthenticationFailure;
            $messages[] = 'Unknown user name';
        }

        if (preg_match('/Authentication failure/', $stderr, $m)) {
            $errors[] = SnmpError::AuthenticationFailure;
            $messages[] = 'Authentication failure';
        }

        if (preg_match('/Invalid authentication protocol specified/', $stderr, $m)) {
            $errors[] = SnmpError::UnsupportedProtocol;
            $messages[] = 'Invalid authentication protocol specified';
        }

        if (preg_match('/Invalid privacy protocol specified/', $stderr, $m)) {
            $errors[] = SnmpError::UnsupportedProtocol;
            $messages[] = 'Invalid privacy protocol specified';
        }

        if (preg_match('/Error: OID not increasing: [^\r\n]*/', $stderr, $m)) {
            $errors[] = SnmpError::OidNotIncreasing;
            $messages[] = trim($m[0]);
        }

        // Check raw output patterns
        if (preg_match('/No Such Instance[^\r\n]*/', $raw, $m)) {
            $errors[] = SnmpError::NoSuchInstance;
            $messages[] = trim($m[0]);
        }

        if (preg_match('/No Such Object[^\r\n]*/', $raw, $m)) {
            $errors[] = SnmpError::NoSuchObject;
            $messages[] = trim($m[0]);
        }

        if (preg_match('/No more variables left[^\r\n]*/', $raw, $m)) {
            if (empty($values)) {
                $errors[] = SnmpError::EndOfMib;
                $messages[] = trim($m[0]);
            }
        }

        if (preg_match('/Reason: \(noSuchName\)/', $raw, $m)) {
            $errors[] = SnmpError::NoSuchName;
            $messages[] = 'Reason: (noSuchName)';
        }

        // Empty output or exit code failures
        if (empty($raw) && empty($errors)) {
            if ($exitCode !== 0) {
                $errors[] = SnmpError::GeneralError;
                $messages[] = ! empty(trim($stderr)) ? trim($stderr) : "Command exited with code $exitCode";
            } else {
                $errors[] = SnmpError::EmptyResponse;
                $messages[] = 'Empty Output';
            }
        } elseif ($exitCode !== 0 && empty($errors)) {
            $errors[] = SnmpError::GeneralError;
            $messages[] = ! empty(trim($stderr)) ? trim($stderr) : "Command exited with code $exitCode";
        }

        return [
            'errors' => array_values(array_unique($errors, SORT_REGULAR)),
            'messages' => array_values(array_unique($messages)),
        ];
    }

    /**
     * Filter bad lines from the raw output, examples:
     * "No Such Instance currently exists at this OID"
     * "No more variables left in this MIB View (It is past the end of the MIB tree)"
     * oidName = NULL
     */
    public static function stripBadLines(string $raw): string
    {
        return (string) preg_replace([
            '/^.*No Such (Instance currently exists|Object available on this agent at this OID).*$/m',
            '/(\n[^\r\n]+No more variables left[^\r\n]+)+$/m',
            '/^.* = NULL[\r\n]*$/m',
        ], '', $raw);
    }

    /**
     * @param  array<int, string>  $command
     */
    public static function parseResponseStatic(string $output, string $stderr = '', int $exitCode = 0, array $command = []): SnmpResponse
    {
        return (new static)->parseResponse($output, $stderr, $exitCode, $command);
    }
}
