<?php

namespace LibreNMS\Discovery;

class YamlDiscoveryData implements \Iterator
{
    private int $pointer = 0;
    private OidCollection $oids;

    public function __construct(
        public readonly array $yaml,
        array $dataFields = [],
    ) {
        $this->oids = new OidCollection;
        $this->collectOids($dataFields);
    }

    public function getItemData(string $index): array
    {
        return $this->oids->getDataFor($index);
    }

    public function getAllData(): array
    {
        return $this->oids->getData();
    }

    public function current(): mixed
    {
        return $this->yaml['data'][$this->pointer] ?? null;
    }

    public function next(): void
    {
        $this->pointer++;
    }

    public function key(): mixed
    {
        return $this->pointer;
    }

    public function valid(): bool
    {
        return isset($this->yaml['data'][$this->pointer]);
    }

    public function rewind(): void
    {
        $this->pointer = 0;
    }

    /**
     * @param  array  $dataFields
     * @return void
     */
    private function collectOids(array $dataFields): void
    {
        $pre_cache_flags = isset($this->yaml['pre-cache']['snmp_flags']) ? (array) $this->yaml['pre-cache']['snmp_flags'] : null;
        foreach ($this->yaml['pre-cache']['oids'] ?? [] as $oid) {
            $this->oids->put($oid, null, $pre_cache_flags);
        }

        foreach ($this->yaml['data'] as $index => $item) {
            $flags = isset($item['snmp_flags']) ? (array) $item['snmp_flags'] : null;

            if (isset($item['oid'])) {
                $this->oids->put($item['oid'], $index, $flags);
            }

            foreach ($dataFields as $field) {
                if (isset($item[$field])) {
                    $this->oids->put($item[$field], $index, $flags);
                }
            }
        }
    }
}
