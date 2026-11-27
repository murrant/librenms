<?php

namespace LibreNMS\Ports;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\Port;
use Closure;

final class PortDescription
{
    private readonly ?string $file;
    private readonly bool $builtin;
    private ?Closure $callback = null;

    public function __construct()
    {
        $setting = LibrenmsConfig::get('port_descr_parser');
        $this->builtin = $setting === 'includes/port-descr-parser.inc.php';
        $root = LibrenmsConfig::get('install_dir');
        $directory = realpath("$root/includes");
        $file = ! $this->builtin && $setting ? realpath("$root/$setting") : false;
        $this->file = $directory && $file && str_starts_with($file, $directory . DIRECTORY_SEPARATOR)
            && str_ends_with($file, '.php') && is_file($file) ? $file : null;
    }

    public function apply(Port $port, Device $device, array $row, ?string $override): void
    {
        if ($this->builtin || $this->file !== null) {
            $parsed = $this->builtin ? self::parse($port->ifAlias ?? '') : $this->custom($port, $device, $row, $override);
            foreach (['type', 'descr', 'circuit', 'speed', 'notes'] as $key) {
                $port->{'port_descr_' . $key} = $parsed[$key] ?? null;
            }
        }
        $port->port_descr_speed = $device->getAttrib('port_descr_speed:' . $port->ifName, $port->port_descr_speed);
    }

    private function custom(Port $model, Device $deviceModel, array $row, ?string $override): array
    {
        if (! $this->callback) {
            // Variables exposed to procedural parsers. A callable parser is loaded once per discovery.
            $port = $model->getAttributes();
            $port_id = $model->port_id;
            $device = $deviceModel->getAttributes();
            $this_port = $port + $row;
            $ifAlias_override = $override;
            $port_ifAlias = [];
            $parser = include $this->file;
            if (! is_callable($parser)) {
                return get_defined_vars()['port_ifAlias'] ?? [];
            }
            $this->callback = Closure::fromCallable($parser);
        }

        return app()->call($this->callback, [
            'ifAlias' => $model->ifAlias ?? '', 'ifAlias_override' => $override,
            'ifIndex' => $model->ifIndex, 'ifName' => $model->ifName,
            'port_id' => $model->port_id, 'device' => $deviceModel,
        ]);
    }

    private static function parse(string $ifAlias): array
    {
        $parts = preg_split('/[:\[\]{}()]/', $ifAlias);
        $type = trim($parts[0] ?? '');
        $description = trim($parts[1] ?? '');
        if (! $type || ! $description) {
            return [];
        }

        return [
            'type' => strtolower($type),
            'descr' => $description,
            'circuit' => trim(preg_split('/[{}]/', $ifAlias)[1] ?? ''),
            'speed' => trim(preg_split('/[\[\]]/', $ifAlias)[1] ?? ''),
            'notes' => trim(preg_split('/[()]/', $ifAlias)[1] ?? ''),
        ];
    }
}
