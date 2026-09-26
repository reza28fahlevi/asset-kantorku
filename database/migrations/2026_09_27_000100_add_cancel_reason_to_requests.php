<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabel permintaan yang dapat dibatalkan (termasuk setelah disetujui, dengan alasan wajib). */
    private const TABLES = ['procurement_requests', 'assignment_requests', 'asset_loan_requests', 'disposal_requests'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->text('cancel_reason')->nullable();
                $t->foreignId('cancelled_by_user_id')->nullable()->constrained('users');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('cancelled_by_user_id');
                $t->dropColumn('cancel_reason');
            });
        }
    }
};
