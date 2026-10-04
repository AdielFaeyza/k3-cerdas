<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ViolationLog;
use App\Models\Worker;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function leaderboard()
    {
        return ['data' => Worker::where('status', 'active')
            ->orderByDesc('current_score')->orderBy('name')
            ->get(['id', 'worker_code', 'name', 'department', 'current_score'])];
    }

    public function heatmap()
    {
        return ['data' => ViolationLog::with('type:id,code,severity')
            ->whereNotNull('pos_x')
            ->where('status', '!=', 'rejected')
            ->where('detected_at', '>=', now()->startOfMonth())
            ->get(['id', 'type_id', 'pos_x', 'pos_y'])
            ->map(fn ($l) => ['x' => (float) $l->pos_x, 'y' => (float) $l->pos_y, 'severity' => $l->type->severity, 'type' => $l->type->code])];
    }

    /** GET /api/v1/snapshots/{log}  (signed URL) */
    public function snapshot(ViolationLog $log)
    {
        abort_unless(Storage::disk('local')->exists($log->snapshot_path), 404);

        return Storage::disk('local')->response($log->snapshot_path);
    }
}
