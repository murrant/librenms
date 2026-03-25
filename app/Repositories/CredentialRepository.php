<?php

/**
 * CredentialRepository.php
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
 * @copyright  2026 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace App\Repositories;

use App\Models\Credential;
use LibreNMS\Credentials\CredentialType;
use ReflectionClass;

class CredentialRepository
{
    /**
     * Get all available credential types.
     *
     * @return array<string, string> Class name => Human readable name
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        $files = glob(base_path('LibreNMS/Credentials/*CredentialType.php'));
        foreach ($files as $file) {
            $class = 'LibreNMS\\Credentials\\' . basename($file, '.php');
            if (class_exists($class)) {
                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }
                $instance = new $class;
                $types[$class] = $instance->name();
            }
        }

        return $types;
    }

    /**
     * Get the schema and UI for a specific credential type.
     *
     * @param  string  $typeClass
     * @return array|null
     */
    public function getTypeInfo(string $typeClass): ?array
    {
        if (! class_exists($typeClass) || ! is_subclass_of($typeClass, CredentialType::class)) {
            return null;
        }

        $reflection = new ReflectionClass($typeClass);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return null;
        }

        $instance = new $typeClass;

        return [
            'schema' => $instance->schema(),
            'ui' => $instance->renderUi(),
            'name' => $instance->name(),
        ];
    }

    /**
     * Parse and validate data for a credential type.
     *
     * @param  string  $typeClass
     * @param  array  $data
     * @return array
     */
    public function parseData(string $typeClass, array $data): array
    {
        if (! class_exists($typeClass) || ! is_subclass_of($typeClass, CredentialType::class)) {
            return $data;
        }

        $reflection = new ReflectionClass($typeClass);
        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return $data;
        }

        $instance = new $typeClass;

        return $instance->parse($data);
    }

    /**
     * Compare data against a credential type schema.
     *
     * @param  string  $typeClass
     * @param  array  $data1
     * @param  array  $data2
     * @return bool
     */
    public function dataMatches(string $typeClass, array $data1, array $data2): bool
    {
        $typeInfo = $this->getTypeInfo($typeClass);
        if (! $typeInfo) {
            return $data1 === $data2;
        }

        $schema = $typeInfo['schema'];
        foreach (array_keys($schema) as $key) {
            $val1 = $data1[$key] ?? null;
            $val2 = $data2[$key] ?? null;
            if ($val1 !== $val2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Format credential data, masking secret fields if requested.
     *
     * @param  Credential  $credential
     * @param  bool  $unmask
     * @return array
     */
    public function formatData(Credential $credential, bool $unmask = false): array
    {
        $data = $credential->data;

        if ($unmask) {
            return $data;
        }

        $typeInfo = $this->getTypeInfo($credential->type);
        $schema = $typeInfo['schema'] ?? [];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (! empty($schema[$key]['secret'])) {
                    $data[$key] = '********';
                }
            }
        }

        return $data;
    }

    /**
     * Prepare data for update, preserving secret fields if they are missing in the new data.
     *
     * @param  Credential  $credential
     * @param  array  $newData
     * @return array
     */
    public function prepareUpdateData(Credential $credential, array $newData): array
    {
        $currentData = $credential->data;
        $typeInfo = $this->getTypeInfo($credential->type);
        $schema = $typeInfo['schema'] ?? [];

        foreach ($schema as $key => $field) {
            if (! empty($field['secret']) && empty($newData[$key]) && isset($currentData[$key])) {
                $newData[$key] = $currentData[$key];
            }
        }

        return $this->parseData($credential->type, $newData);
    }
}
