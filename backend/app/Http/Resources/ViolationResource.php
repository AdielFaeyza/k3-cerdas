<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin \App\Models\ViolationLog */
class ViolationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'points_deducted' => $this->points_deducted,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'severity' => $this->type->severity,
            'type' => ['code' => $this->type->code, 'name' => $this->type->name],
            'camera' => ['code' => $this->camera->camera_code, 'location' => $this->camera->location_name],
            'worker' => $this->worker ? [
                'id' => $this->worker->id,
                'worker_code' => $this->worker->worker_code,
                'name' => $this->worker->name,
                'current_score' => $this->worker->current_score,
            ] : null,
            'pos' => $this->pos_x !== null ? ['x' => (float) $this->pos_x, 'y' => (float) $this->pos_y] : null,
            // Foto bukti disimpan private; hanya bisa dibuka lewat URL bertanda tangan yang kedaluwarsa
            'snapshot_url' => URL::temporarySignedRoute('snapshots.show', now()->addMinutes(30), ['log' => $this->id]),
        ];
    }
}
