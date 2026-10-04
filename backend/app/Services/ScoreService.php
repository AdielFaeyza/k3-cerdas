<?php

namespace App\Services;

use App\Models\MonthlyScore;
use App\Models\ViolationLog;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScoreService
{
    /** Potong skor dengan lock baris (aman dari race condition). Mengembalikan poin yang benar-benar dipotong (skor min. 0). */
    public function deduct(Worker $worker, int $points): int
    {
        $locked = Worker::whereKey($worker->id)->lockForUpdate()->firstOrFail();
        $actual = min($points, $locked->current_score);
        $locked->current_score -= $actual;
        $locked->save();
        $worker->current_score = $locked->current_score;

        return $actual;
    }

    public function restore(Worker $worker, int $points): void
    {
        $locked = Worker::whereKey($worker->id)->lockForUpdate()->firstOrFail();
        $locked->current_score = min(config('k3.start_score'), $locked->current_score + $points);
        $locked->save();
        $worker->current_score = $locked->current_score;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function bounds(string $period): array
    {
        $start = Carbon::createFromFormat('Y-m-d', "$period-01", config('app.timezone'))->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /** Skor dihitung ulang dari log (sumber kebenaran), bukan dari cache current_score. */
    private function pointsPerWorker(Carbon $from, ?Carbon $to = null): Collection
    {
        return ViolationLog::query()
            ->selectRaw('worker_id, SUM(points_deducted) AS pts, COUNT(*) AS cnt')
            ->whereNotNull('worker_id')
            ->where('status', '!=', 'rejected')
            ->where('detected_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('detected_at', '<=', $to))
            ->groupBy('worker_id')
            ->get()
            ->keyBy('worker_id');
    }

    public function closePeriod(string $period): Collection
    {
        [$start, $end] = $this->bounds($period);
        $agg = $this->pointsPerWorker($start, $end);
        $start100 = config('k3.start_score');

        return Worker::withTrashed()
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>=', $start))
            ->where('created_at', '<=', $end)
            ->get()
            ->map(function (Worker $w) use ($agg, $period, $start100) {
                $pts = (int) ($agg[$w->id]->pts ?? 0);
                $cnt = (int) ($agg[$w->id]->cnt ?? 0);
                $score = max(0, $start100 - $pts);

                return MonthlyScore::updateOrCreate(
                    ['worker_id' => $w->id, 'period' => $period],
                    ['final_score' => $score, 'violation_count' => $cnt, 'recommendation' => $this->recommend($w, $score, $cnt)],
                )->setRelation('worker', $w);
            });
    }

    /** Reset awal bulan: 100 dikurangi pelanggaran yang sudah terjadi di bulan berjalan. */
    public function recalcCurrentScores(): void
    {
        [$start] = $this->bounds(now()->format('Y-m'));
        $agg = $this->pointsPerWorker($start);
        Worker::query()->get()->each(fn (Worker $w) => $w->update([
            'current_score' => max(0, config('k3.start_score') - (int) ($agg[$w->id]->pts ?? 0)),
        ]));
    }

    public function recommend(Worker $w, int $score, int $count): string
    {
        // Rekomendasi untuk ditinjau HR, BUKAN keputusan otomatis.
        if ($score < config('k3.threshold_contract') && $w->employment_type !== 'tetap') {
            return 'tidak_diperpanjang';
        }
        if ($score < config('k3.threshold_training')) {
            return 'training';
        }

        return $count === 0 ? 'apresiasi' : 'normal';
    }
}
