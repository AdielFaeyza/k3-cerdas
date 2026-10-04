<?php

namespace App\Console\Commands;

use App\Models\MonthlyScore;
use App\Services\ScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CloseMonth extends Command
{
    protected $signature = 'k3:close-month {--period= : Format YYYY-MM, default bulan lalu} {--no-mail}';

    protected $description = 'Tutup periode KPI K3: simpan skor bulanan, buat laporan CSV, kirim panggilan training, reset skor';

    public function handle(ScoreService $scores): int
    {
        $previous = now()->subMonthNoOverflow()->format('Y-m');
        $period = $this->option('period') ?: $previous;

        $results = $scores->closePeriod($period)->sortBy('final_score');

        $csv = "worker_code,nama,departemen,status_kerja,skor_akhir,jumlah_pelanggaran,rekomendasi\n";
        foreach ($results as $r) {
            $w = $r->worker;
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                [$w->worker_code, $w->name, $w->department, $w->employment_type, $r->final_score, $r->violation_count, $r->recommendation]))."\n";
        }
        $file = "reports/k3-$period.csv";
        Storage::disk('local')->put($file, $csv);
        $this->info("Laporan: storage/app/private/$file");

        if (! $this->option('no-mail')) {
            $results->whereIn('recommendation', ['training', 'tidak_diperpanjang'])
                ->each(fn (MonthlyScore $r) => $this->sendSummons($r, $period));
        }

        if ($period === $previous) {
            $scores->recalcCurrentScores();
            $this->info('Skor bulan berjalan di-reset ke '.config('k3.start_score').'.');
        }

        $this->table(['Kode', 'Nama', 'Skor', 'Rekomendasi'],
            $results->map(fn ($r) => [$r->worker->worker_code, $r->worker->name, $r->final_score, $r->recommendation]));

        return self::SUCCESS;
    }

    private function sendSummons(MonthlyScore $r, string $period): void
    {
        $w = $r->worker;
        $body = "Yth. {$w->name} ({$w->worker_code}),\n\n"
            ."Skor Keselamatan K3 Anda periode $period adalah {$r->final_score} dari ".config('k3.start_score')
            ." dengan {$r->violation_count} pelanggaran.\nAnda dijadwalkan mengikuti training ulang K3. "
            ."Detail dan bukti pelanggaran dapat ditanyakan ke tim HSE; Anda berhak mengajukan banding.\n\nTim HSE";

        Mail::raw($body, function ($m) use ($w, $period) {
            $m->to($w->email ?: config('k3.hse_email'))
                ->cc(config('k3.hse_email'))
                ->subject("Panggilan Training Ulang K3 — Periode $period");
        });
    }
}
