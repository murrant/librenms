<?php

/**
 * SnmpDebugInfo.php
 *
 * Concrete implementation of SnmpDebugInfoInterface.
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

use LibreNMS\Enum\SnmpError;

class SnmpDebugInfo implements SnmpDebugInfoInterface
{
    /**
     * @param  array<int, string>  $command
     * @param  array<int, SnmpError>  $errors
     * @param  array<int, string>  $errorMessages
     */
    public function __construct(
        public readonly array $command = [],
        public readonly ?int $exitCode = 0,
        public readonly string $stderr = '',
        public readonly string $output = '',
        public readonly array $errors = [],
        public readonly array $errorMessages = [],
    ) {
    }

    public function getCommand(): array
    {
        return $this->command;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getError(): ?SnmpError
    {
        return $this->errors[0] ?? null;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasError(SnmpError $error): bool
    {
        return in_array($error, $this->errors, true);
    }

    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessages[0] ?? $this->getError()?->value;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function append(SnmpDebugInfoInterface $other): SnmpDebugInfoInterface
    {
        return new static(
            command: $other->getCommand() ?: $this->command,
            exitCode: $this->exitCode ?: $other->getExitCode(),
            stderr: $this->stderr . $other->getStderr(),
            output: $this->output . $other->getOutput(),
            errors: array_values(array_unique(array_merge($this->errors, $other->getErrors()), SORT_REGULAR)),
            errorMessages: array_values(array_unique(array_filter(array_merge($this->errorMessages, $other->getErrorMessages())))),
        );
    }
}
