<?php

namespace App\Events;

use App\Models\Worker;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ScoreChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Worker $worker) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('k3.dashboard')];
    }

    public function broadcastAs(): string
    {
        return 'score.changed';
    }

    public function broadcastWith(): array
    {
        return ['worker_id' => $this->worker->id, 'current_score' => $this->worker->current_score];
    }
}
