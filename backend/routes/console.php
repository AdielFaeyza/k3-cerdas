<?php

use Illuminate\Support\Facades\Schedule;

// Tutup periode tiap tanggal 1 pukul 00:05 WIB (butuh cron: * * * * * php artisan schedule:run)
Schedule::command('k3:close-month')->monthlyOn(1, '00:05')->timezone('Asia/Jakarta');
