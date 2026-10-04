<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateDashboardUser extends Command
{
    protected $signature = 'k3:user {email} {name} {--role=hse : admin|hr|hse}';

    protected $description = 'Buat akun dashboard HR/HSE dan cetak API token';

    public function handle(): int
    {
        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->argument('name'), 'role' => $this->option('role'), 'password' => Str::random(32)],
        );
        $token = $user->createToken('dashboard')->plainTextToken;
        $this->info('Token (isi ke frontend/.env sebagai VITE_API_TOKEN):');
        $this->line($token);

        return self::SUCCESS;
    }
}
