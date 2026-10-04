<?php

return [
    'edge_token' => env('K3_EDGE_TOKEN', ''),
    'start_score' => 100,
    // Laporan pekerja + jenis pelanggaran yang sama dalam jendela ini (lintas kamera) dianggap duplikat
    'dedupe_seconds' => (int) env('K3_DEDUPE_SECONDS', 60),
    'threshold_training' => 70,
    'threshold_contract' => 50,
    'hse_email' => env('K3_HSE_EMAIL', 'hse@perusahaan.co.id'),
];
