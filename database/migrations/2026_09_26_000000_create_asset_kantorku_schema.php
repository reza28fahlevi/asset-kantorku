<?php

use Database\Seeders\SqlScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Skema lengkap (tabel, constraint, trigger) dari database/sql/asset_kantorku.sql.
     */
    public function up(): void
    {
        DB::unprepared(SqlScript::schema());
    }

    public function down(): void
    {
        $tables = [
            'audit_logs', 'attachments', 'disposal_requests', 'loan_extension_requests', 'asset_loans',
            'asset_loan_request_items', 'asset_loan_requests', 'asset_assignments', 'assignment_request_items',
            'assignment_requests', 'asset_events', 'assets', 'procurement_receipt_items', 'procurement_receipts',
            'procurement_request_items', 'procurement_requests', 'approval_steps', 'approval_requests', 'vendors',
            'asset_categories', 'number_sequences', 'settings', 'role_user', 'permission_role', 'permissions', 'roles',
            'users', 'employees', 'locations', 'departments', 'notifications', 'failed_jobs', 'job_batches', 'jobs',
            'cache_locks', 'cache', 'password_reset_tokens', 'sessions',
        ];
        DB::unprepared('DROP TABLE IF EXISTS '.implode(', ', $tables).' CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_prevent_modify_append_only, fn_assets_guard, fn_approval_steps_guard, '
            .'fn_approval_requests_guard, fn_asset_assignment_loan_exclusive CASCADE');
    }
};
