<?php

use App\Services\LoanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('loans:mark-overdue', function (LoanService $loans) {
    $count = $loans->markOverdue();
    $this->info("{$count} peminjaman ditandai terlambat.");
})->purpose('Tandai peminjaman yang melewati due date sebagai OVERDUE dan kirim notifikasi');

// Jalankan scheduler: `php artisan schedule:work` (dev) atau cron `* * * * * php artisan schedule:run`
Schedule::command('loans:mark-overdue')->hourly()->withoutOverlapping();
