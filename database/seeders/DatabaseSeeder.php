<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Data RBAC (role, permission, pemetaan) + data awal demo dari database/sql/asset_kantorku.sql.
     * Jalankan pada database yang baru dimigrasi (mis. `php artisan migrate:fresh --seed`).
     */
    public function run(): void
    {
        if (DB::table('roles')->exists()) {
            $this->command->warn('Data awal sudah ada — seeder dilewati. Gunakan `php artisan migrate:fresh --seed` untuk mengulang.');

            return;
        }

        DB::transaction(fn () => DB::unprepared(SqlScript::seed()));
        Cache::forget('app.settings');

        $this->command->info('Data RBAC & demo berhasil dimuat. Login: admin.aset@kantorku.test / password');
    }
}
