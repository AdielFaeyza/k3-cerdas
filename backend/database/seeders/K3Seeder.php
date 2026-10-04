<?php

namespace Database\Seeders;

use App\Models\Camera;
use App\Models\ViolationType;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class K3Seeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['NO_GOGGLES', 'Tidak Pakai Kacamata Las', 5, 'low'],
            ['NO_VEST', 'Tidak Pakai Rompi', 5, 'low'],
            ['NO_HELMET', 'Tidak Pakai Helm', 10, 'medium'],
            ['DANGER_ZONE', 'Memasuki Zona Bahaya Alat Berat', 25, 'high'],
        ] as [$code, $name, $pts, $sev]) {
            ViolationType::updateOrCreate(['code' => $code], ['name' => $name, 'deduction_points' => $pts, 'severity' => $sev]);
        }

        Camera::updateOrCreate(['camera_code' => 'CAM-ZONE-A'], ['location_name' => 'Area Konstruksi A', 'ip_address' => '192.168.1.10']);
        Camera::updateOrCreate(['camera_code' => 'CAM-WELDING-B'], ['location_name' => 'Bengkel Las B', 'ip_address' => '192.168.1.11']);

        foreach ([
            ['ID-001', 'Budi Santoso', 'Konstruksi', 'tetap'],
            ['ID-002', 'Siti Aminah', 'Pengelasan', 'kontrak'],
            ['ID-003', 'Agus Prasetyo', 'Konstruksi', 'harian'],
            ['ID-004', 'Dewi Lestari', 'Logistik', 'magang'],
            ['ID-049', 'Rudi Hartono', 'Alat Berat', 'kontrak'],
        ] as [$code, $name, $dept, $type]) {
            Worker::updateOrCreate(['worker_code' => $code], ['name' => $name, 'department' => $dept, 'employment_type' => $type]);
        }
    }
}
