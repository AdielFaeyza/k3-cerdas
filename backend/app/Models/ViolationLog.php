<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViolationLog extends Model
{
    protected $fillable = [
        'event_uuid', 'worker_id', 'camera_id', 'type_id', 'points_deducted', 'snapshot_path',
        'pos_x', 'pos_y', 'status', 'detected_at', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['detected_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class, 'type_id');
    }
}
