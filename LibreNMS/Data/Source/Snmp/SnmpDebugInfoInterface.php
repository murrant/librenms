<?php

/**
 * SnmpDebugInfoInterface.php
 *
 * Interface for SNMP debug and diagnostics info.
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

interface SnmpDebugInfoInterface
{
    /**
     * The executed command line, if applicable (e.g. CLI backends).
     *
     * @return array<int, string>
     */
    public function getCommand(): array;

    /**
     * Process exit code or backend return code, if applicable.
     */
    public function getExitCode(): ?int;

    /**
     * Classified error conditions.
     *
     * @return array<int, SnmpError>
     */
    public function getErrors(): array;

    /**
     * The primary/first classified error condition, if any.
     */
    public function getError(): ?SnmpError;

    /**
     * Check if any error occurred.
     */
    public function hasErrors(): bool;

    /**
     * Check if a specific error type occurred.
     */
    public function hasError(SnmpError $error): bool;

    /**
     * Human-readable diagnostic error messages.
     *
     * @return array<int, string>
     */
    public function getErrorMessages(): array;

    /**
     * The primary/first diagnostic error message, if any.
     */
    public function getErrorMessage(): ?string;

    /**
     * Stderr output from the command or process.
     */
    public function getStderr(): string;

    /**
     * Stdout or raw response output.
     */
    public function getOutput(): string;

    /**
     * Merge with another debug info instance (e.g. across chunked requests).
     */
    public function append(SnmpDebugInfoInterface $other): SnmpDebugInfoInterface;
}
