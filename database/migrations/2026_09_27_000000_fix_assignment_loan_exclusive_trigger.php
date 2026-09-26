<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Perbaiki trigger eksklusivitas assignment/peminjaman: versi awal mengakses NEW.status
     * pada tabel asset_assignments (tidak punya kolom status) sehingga pengembalian aset
     * yang ditugaskan selalu gagal ("record new has no field status").
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fn_asset_assignment_loan_exclusive() RETURNS trigger AS $$
BEGIN
    IF TG_TABLE_NAME = 'asset_assignments' THEN
        IF NEW.returned_at IS NULL
            AND EXISTS (SELECT 1 FROM asset_loans WHERE asset_id = NEW.asset_id AND status IN ('CHECKED_OUT', 'OVERDUE')) THEN
            RAISE EXCEPTION 'Aset % sedang dipinjam; tidak dapat di-assign', NEW.asset_id
                USING ERRCODE = 'check_violation';
        END IF;
    ELSIF TG_TABLE_NAME = 'asset_loans' THEN
        IF NEW.status IN ('CHECKED_OUT', 'OVERDUE')
            AND EXISTS (SELECT 1 FROM asset_assignments WHERE asset_id = NEW.asset_id AND returned_at IS NULL) THEN
            RAISE EXCEPTION 'Aset % sedang ditugaskan; tidak dapat dipinjam', NEW.asset_id
                USING ERRCODE = 'check_violation';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        // Tidak dikembalikan ke versi yang rusak.
    }
};
