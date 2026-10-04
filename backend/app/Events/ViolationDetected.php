<?php

namespace App\Events;

use App\Http\Resources\ViolationResource;
use App\Models\ViolationLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ViolationDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public ViolationLog $log, public bool $updated = false) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('k3.dashboard')];
    }

    public function broadcastAs(): string
    {
        return $this->updated ? 'violation.updated' : 'violation.detected';
    }

    public function broadcastWith(): array
    {
        return (new ViolationResource($this->log->loadMissing(['worker', 'camera', 'type'])))->resolve();
    }
}
