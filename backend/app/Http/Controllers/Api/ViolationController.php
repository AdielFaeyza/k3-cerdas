<?php

namespace App\Http\Controllers\Api;

use App\Events\ScoreChanged;
use App\Events\ViolationDetected;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreViolationRequest;
use App\Http\Resources\ViolationResource;
use App\Models\Camera;
use App\Models\ViolationLog;
use App\Models\ViolationType;
use App\Models\Worker;
use App\Services\ScoreService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ViolationController extends Controller
{
    public function __construct(private ScoreService $scores) {}

    /** POST /api/v1/violations  (dipanggil oleh edge Python) */
    public function store(StoreViolationRequest $request)
    {
        $data = $request->validated();

        if ($existing = ViolationLog::where('event_uuid', $data['event_uuid'])->first()) {
            return response()->json(['id' => $existing->id, 'duplicate' => true]);
        }

        $camera = Camera::where('camera_code', $data['camera_code'])->firstOrFail();
        $type = ViolationType::where('code', $data['type_code'])->firstOrFail();
        $detectedAt = Carbon::parse($data['detected_at'])->setTimezone(config('app.timezone'));
        $worker = empty($data['worker_code']) ? null
            : Worker::where('worker_code', $data['worker_code'])->where('status', 'active')->first();

        // Deduplikasi lintas kamera: pekerja sama, pelanggaran sama, dalam jendela waktu singkat
        if ($worker && ViolationLog::where('worker_id', $worker->id)->where('type_id', $type->id)
            ->where('status', '!=', 'rejected')
            ->whereBetween('detected_at', [$detectedAt->copy()->subSeconds(config('k3.dedupe_seconds')), $detectedAt])
            ->exists()) {
            return response()->json(['duplicate' => true]);
        }

        // File disimpan dulu (storage tidak ikut transaksi DB); dihapus jika transaksi gagal
        $path = $request->file('snapshot')->store('violations/'.$detectedAt->format('Y/m'), 'local');

        try {
            $log = DB::transaction(function () use ($data, $camera, $type, $worker, $path, $detectedAt) {
                $deducted = $worker && $detectedAt->isSameMonth(now()) ? $this->scores->deduct($worker, $type->deduction_points) : 0;

                return ViolationLog::create([
                    'event_uuid' => $data['event_uuid'],
                    'worker_id' => $worker?->id,
                    'camera_id' => $camera->id,
                    'type_id' => $type->id,
                    'points_deducted' => $deducted,
                    'snapshot_path' => $path,
                    'pos_x' => $data['pos_x'] ?? null,
                    'pos_y' => $data['pos_y'] ?? null,
                    'status' => $worker ? 'pending' : 'unidentified',
                    'detected_at' => $detectedAt,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($path);

            return response()->json(['duplicate' => true]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $this->broadcast(new ViolationDetected($log));

        return response()->json(['id' => $log->id, 'status' => $log->status], 201);
    }

    /** GET /api/v1/violations?status=pending */
    public function index(Request $request)
    {
        $logs = ViolationLog::with(['worker', 'camera', 'type'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('detected_at')
            ->limit(100)
            ->get();

        return ViolationResource::collection($logs);
    }

    /** POST /api/v1/violations/{log}/review  body: {action: confirm|reject|assign, worker_code?, note?} */
    public function review(Request $request, ViolationLog $log)
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject,assign'],
            'worker_code' => ['required_if:action,assign', 'nullable', 'exists:workers,worker_code'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        abort_if($log->status === 'rejected', 422, 'Pelanggaran sudah ditolak.');

        $sameMonth = $log->detected_at->isSameMonth(now());
        $changedWorker = null;

        DB::transaction(function () use ($request, $data, $log, $sameMonth, &$changedWorker) {
            switch ($data['action']) {
                case 'confirm':
                    abort_unless($log->status === 'pending', 422, 'Hanya status pending yang bisa dikonfirmasi.');
                    $log->status = 'confirmed';
                    break;

                case 'reject': // false positive / banding diterima -> poin dikembalikan
                    if ($log->worker && $log->points_deducted > 0 && $sameMonth) {
                        $this->scores->restore($log->worker, $log->points_deducted);
                        $changedWorker = $log->worker;
                    }
                    $log->status = 'rejected';
                    break;

                case 'assign': // pelanggaran tanpa ID ditetapkan manual oleh supervisor
                    abort_unless($log->status === 'unidentified', 422, 'Hanya pelanggaran tanpa ID yang bisa ditetapkan.');
                    $worker = Worker::where('worker_code', $data['worker_code'])->firstOrFail();
                    $log->worker_id = $worker->id;
                    $log->points_deducted = $sameMonth
                        ? $this->scores->deduct($worker, $log->type->deduction_points)
                        : $log->type->deduction_points;
                    $log->status = 'confirmed';
                    $changedWorker = $worker;
                    break;
            }
            $log->reviewed_by = $request->user()->id;
            $log->reviewed_at = now();
            $log->review_note = $data['note'] ?? null;
            $log->save();
        });

        if (! $sameMonth && $log->worker_id) {
            $this->scores->closePeriod($log->detected_at->format('Y-m')); // koreksi laporan bulan lalu
        }

        $log->load(['worker', 'camera', 'type']);
        $this->broadcast(new ViolationDetected($log, updated: true));
        if ($changedWorker) {
            $this->broadcast(new ScoreChanged($changedWorker->fresh()));
        }

        return new ViolationResource($log);
    }

    /** Gagal broadcast (Reverb mati) tidak boleh membatalkan data yang sudah tersimpan. */
    private function broadcast(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
