<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sudah dijaga middleware VerifyEdgeToken
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid'],
            'camera_code' => ['required', 'string', 'exists:cameras,camera_code'],
            'type_code' => ['required', 'string', 'exists:violation_types,code'],
            'worker_code' => ['nullable', 'string', 'max:20'],
            'pos_x' => ['nullable', 'numeric'],
            'pos_y' => ['nullable', 'numeric'],
            'detected_at' => ['required', 'date'],
            'snapshot' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
