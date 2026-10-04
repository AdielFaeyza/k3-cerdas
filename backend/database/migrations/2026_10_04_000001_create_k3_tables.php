<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin', 'hr', 'hse'])->default('hse')->after('email');
        });

        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->string('worker_code', 20)->unique();          // isi QR Code, mis. ID-049
            $t->string('name');
            $t->string('department')->nullable();
            $t->string('email')->nullable();
            $t->enum('employment_type', ['tetap', 'kontrak', 'harian', 'magang'])->default('kontrak');
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->unsignedTinyInteger('current_score')->default(100); // cache skor bulan berjalan
            $t->timestamps();
            $t->softDeletes();                                  // riwayat bukti tidak ikut terhapus
        });

        Schema::create('cameras', function (Blueprint $t) {
            $t->id();
            $t->string('camera_code', 30)->unique();
            $t->string('location_name');
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('violation_types', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();                  // NO_HELMET, DANGER_ZONE, ...
            $t->string('name');
            $t->unsignedSmallInteger('deduction_points');
            $t->enum('severity', ['low', 'medium', 'high']);
            $t->timestamps();
        });

        Schema::create('violation_logs', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_uuid')->unique();                  // idempotensi retry dari edge
            $t->foreignId('worker_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('camera_id')->constrained()->restrictOnDelete();
            $t->foreignId('type_id')->constrained('violation_types')->restrictOnDelete();
            $t->unsignedSmallInteger('points_deducted')->default(0);
            $t->string('snapshot_path');                       // disk private, diakses via signed URL
            $t->decimal('pos_x', 8, 2)->nullable();            // koordinat lantai (meter) untuk heatmap
            $t->decimal('pos_y', 8, 2)->nullable();
            $t->enum('status', ['pending', 'confirmed', 'rejected', 'unidentified'])->default('pending');
            $t->timestamp('detected_at');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note')->nullable();
            $t->timestamps();
            $t->index(['worker_id', 'detected_at']);
            $t->index(['status', 'detected_at']);
        });

        Schema::create('monthly_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('worker_id')->constrained()->restrictOnDelete();
            $t->char('period', 7);                             // YYYY-MM
            $t->unsignedTinyInteger('final_score');
            $t->unsignedInteger('violation_count');
            $t->enum('recommendation', ['apresiasi', 'normal', 'training', 'tidak_diperpanjang']);
            $t->timestamps();
            $t->unique(['worker_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_scores');
        Schema::dropIfExists('violation_logs');
        Schema::dropIfExists('violation_types');
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('workers');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
