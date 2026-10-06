<?php

namespace App\Graphing\Rrd;

use App\Graphing\Contracts\RenderPlan;
use LibreNMS\RRD\RrdPath;

final readonly class CompiledGraph implements RenderPlan
{
    /**
     * @param  list<string|int|float>  $options
     * @param  array<string, array{path: RrdPath, keys: list<string>}>  $files  keyed by relative path
     */
    public function __construct(
        public array $options,
        public array $files,
        public string $title,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    /**
     * @return list<RrdPath>
     */
    public function paths(): array
    {
        return array_values(array_map(fn (array $file) => $file['path'], $this->files));
    }

    /**
     * Find the file named in an rrdtool error message.
     * Matches on the relative path, the absolute path differs between local and rrdcached.
     */
    public function fileInMessage(string $message): ?RrdPath
    {
        $relativePaths = array_keys($this->files);
        usort($relativePaths, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($relativePaths as $relativePath) {
            if (str_contains($message, (string) $relativePath)) {
                return $this->files[$relativePath]['path'];
            }
        }

        return null;
    }

    /**
     * @param  list<RrdPath>  $paths
     * @return list<string> series keys drawn from the given files
     */
    public function keysFor(array $paths): array
    {
        $keys = [];
        foreach ($paths as $path) {
            array_push($keys, ...$this->files[$path->relativePath()]['keys'] ?? []);
        }

        return array_values(array_unique($keys));
    }
}
