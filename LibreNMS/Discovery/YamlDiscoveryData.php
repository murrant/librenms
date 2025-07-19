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
        $this->pointer--;
    }

    /**
     * @param  array  $dataFields
     * @return void
     */
    private function collectOids(array $dataFields): void
    {
        foreach ($this->yaml['pre-fetch'] ?? [] as $item) {
            foreach ($item['oids'] as $oid) {
                $this->oids->put($oid, null, $item['snmp_flags'] ?? null);
            }
        }

        foreach ($this->yaml['data'] as $index => $item) {
            $flags = $item['snmp_flags'] ?? null;

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
