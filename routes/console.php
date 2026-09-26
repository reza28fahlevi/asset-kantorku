<?php

use App\Services\ApprovalService;
use App\Services\LoanService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('loans:mark-overdue', function (LoanService $loans) {
    $count = $loans->markOverdue();
    $this->info("{$count} peminjaman ditandai terlambat.");
})->purpose('Tandai peminjaman yang melewati due date sebagai OVERDUE dan kirim notifikasi');

Artisan::command('approvals:reassign', function (ApprovalService $approvals) {
    $result = $approvals->reassignIneligibleSteps();
    $this->info("{$result['reassigned']} approval dialihkan, {$result['failed']} gagal (approver eskalasi tidak valid).");
})->purpose('Alihkan approval pending yang approver-nya sudah nonaktif/tidak berwenang');

// Jalankan scheduler: `php artisan schedule:work` (dev) atau cron `* * * * * php artisan schedule:run`
Schedule::command('loans:mark-overdue')->hourly()->withoutOverlapping();
Schedule::command('approvals:reassign')->hourly()->withoutOverlapping();
