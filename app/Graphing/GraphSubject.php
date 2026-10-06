<?php

namespace App\Graphing;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Database\Eloquent\Model;

/**
 * The entities a graph is about, resolved once per graph
 */
class GraphSubject
{
    /**
     * @param  Model|null  $authorizable  the model access is checked against, defaults to the port or device
     */
    public function __construct(
        public readonly ?Device $device = null,
        public readonly ?Port $port = null,
        private readonly ?Model $authorizable = null,
    ) {
    }

    public function authorizable(): ?Model
    {
        return $this->authorizable ?? $this->port ?? $this->device;
    }
}
