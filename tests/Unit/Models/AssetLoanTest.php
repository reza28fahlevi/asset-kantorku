<?php

namespace Tests\Unit\Models;

use App\Enums\LoanStatus;
use App\Models\AssetLoan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetLoanTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_is_active_untuk_checked_out_dan_overdue_saja(): void
    {
        foreach (LoanStatus::cases() as $status) {
            $loan = new AssetLoan(['status' => $status, 'due_at' => now()->addDay()]);
            $expected = in_array($status, [LoanStatus::CheckedOut, LoanStatus::Overdue], true);
            $this->assertSame($expected, $loan->isActive(), $status->value);
        }
    }

    public function test_is_overdue_bila_aktif_dan_melewati_due_date(): void
    {
        $this->assertTrue(new AssetLoan(['status' => LoanStatus::CheckedOut, 'due_at' => now()->subMinute()])->isOverdue());
        $this->assertTrue(new AssetLoan(['status' => LoanStatus::Overdue, 'due_at' => now()->subDays(3)])->isOverdue());
    }

    public function test_is_overdue_false_bila_belum_jatuh_tempo(): void
    {
        $this->assertFalse(new AssetLoan(['status' => LoanStatus::CheckedOut, 'due_at' => now()->addMinute()])->isOverdue());
        // Status OVERDUE lama tetapi due date sudah diperpanjang ke masa depan: tidak lagi terlambat.
        $this->assertFalse(new AssetLoan(['status' => LoanStatus::Overdue, 'due_at' => now()->addDay()])->isOverdue());
    }

    public function test_is_overdue_false_untuk_loan_yang_sudah_selesai(): void
    {
        foreach ([LoanStatus::Returned, LoanStatus::Cancelled] as $status) {
            $this->assertFalse(new AssetLoan(['status' => $status, 'due_at' => now()->subDays(10)])->isOverdue(), $status->value);
        }
    }

    public function test_scope_active_di_database(): void
    {
        $checkedOut = $this->makeLoan(5);
        $overdue = $this->makeLoan(5, LoanStatus::Overdue, dueAt: now()->subDay()->startOfMinute());
        $returned = $this->makeLoan(5, LoanStatus::Returned);
        $cancelled = $this->makeLoan(5, LoanStatus::Cancelled);

        $ids = AssetLoan::query()->whereIn('id', [$checkedOut->id, $overdue->id, $returned->id, $cancelled->id])
            ->active()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$checkedOut->id, $overdue->id], $ids);
    }

    public function test_relasi_borrower_dan_cast_tanggal(): void
    {
        $loan = $this->makeLoan(7)->fresh();

        $this->assertSame('Dimas Satrio', $loan->borrower->name);
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $loan->due_at);
        $this->assertSame(LoanStatus::CheckedOut, $loan->status);
        $this->assertFalse($loan->is_late);
    }

    public function test_satu_aset_tidak_boleh_punya_dua_loan_aktif(): void
    {
        $loan = $this->makeLoan(5);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeLoan(7, asset: $loan->asset);
    }

    public function test_aset_yang_sedang_ditugaskan_tidak_bisa_dipinjam(): void
    {
        $assignment = $this->makeAssignment(5);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeLoan(7, asset: $assignment->asset);
    }
}
