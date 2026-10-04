<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyScore extends Model
{
    protected $fillable = ['worker_id', 'period', 'final_score', 'violation_count', 'recommendation'];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
    }
}
