<?php

namespace LibreNMS\Discovery;

use Illuminate\Support\Str;
use LibreNMS\Util\Oid;
use SnmpQuery;

class OidCollection
{
    private ?array $data = null;
    private array $flags = [];

    private array $flagged = [];
    private array $numeric = [];
    private array $scalar = [];
    private array $walkable = [];
    private array $items = [];

    public function put(string $oid, ?int $item, ?array $flags): void
    {
        if ($item !== null) {
            $this->items[$item][] = $oid;
        }

        if ($flags) {
            $this->flags[$oid] = $flags;
        }

        $oidObject = Oid::of($oid);
        $isNumeric = $oidObject->isNumeric();
        $isScalar = $oidObject->isScalar();
        if ($flags && ($isNumeric || $isScalar)) {
            $this->flagged[] = $oid;
        } elseif ($isNumeric) {
            $this->numeric[] = $oid;
        } elseif ($isScalar) {
            $this->scalar[] = $oid;
        } else {
            $this->walkable[] = $oid;
        }
    }

    public function fetch(): void
    {
        if ($this->data !== null) {
            return;
        }

        $this->data = $this->numeric ? SnmpQuery::numeric()->get($this->numeric)->values() : [];
        if ($this->scalar) {
            SnmpQuery::enumStrings()->get($this->scalar)->valuesByIndex($this->data);
        }
        foreach ($this->flagged as $oid) {
            $this->data[$oid] = SnmpQuery::options($this->flags[$oid])->get($oid)->value();
        }

        foreach ($this->walkable as $oid) {
            $snmpQuery = SnmpQuery::enumStrings()->numericIndex();
            if (isset($this->flags[$oid])) {
                $snmpQuery->options($this->flags[$oid]);
            }

            $this->data[$oid] = $snmpQuery->walk($oid)->valuesByIndex();
        }
    }

    public function getDataFor(int $item): array
    {
        $this->fetch();

        $data = [];

        foreach ($this->getOidsFor($item) as $oid) {
            if (! isset($this->data[$oid])) {
                $oidName = Str::beforeLast($oid, '.0');
                if (isset($this->data[0][$oidName])) {
                    $data[0][$oidName] = $this->data[0][$oidName];
                }

                continue;
            }

            $value = $this->data[$oid];

            // scalar or numeric
            if (! is_array($value)) {
                $data[$oid] = $value;
                continue;
            }

            foreach ($value as $index => $item) {
                if (! is_array($item)) {
                    $data[$oid] = $value;
                    break;
                }
                $data[$index] = array_merge($data[$index] ?? [], $item);
            }
        }

        return $data;
    }

    public function getOidsFor(int $item): array
    {
        return $this->items[$item] ?? [];
    }

    public function getData(): array
    {
        $this->fetch();

        return $this->data;
    }
}
