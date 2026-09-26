<?php

namespace Tests\Unit\Enums;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Enums\DisposalReason;
use App\Enums\DisposalStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ExtensionStatus;
use App\Enums\HasLabel;
use App\Enums\LoanStatus;
use App\Enums\ProcurementStatus;
use App\Enums\RequestStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Enum murni: label, warna badge, options()/values() dan kesesuaian nilai dengan dokumen desain & CHECK DB. */
class EnumsTest extends TestCase
{
    private const COLORS = ['available', 'assigned', 'loan', 'repair', 'disposal', 'pending', 'neutral'];

    /** Nilai resmi per enum (BUSINESS_PROCESS_AND_DESIGN.md §7 & CHECK constraint database). */
    private const EXPECTED_VALUES = [
        ApprovalStatus::class => ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
        ApprovalType::class => ['PROCUREMENT', 'ASSIGNMENT', 'LOAN', 'LOAN_EXTENSION', 'DISPOSAL'],
        AssetCondition::class => ['GOOD', 'FAIR', 'POOR', 'DAMAGED'],
        AssetEventType::class => [
            'REGISTERED', 'RECEIVED', 'UPDATED', 'STATUS_CHANGED', 'LOCATION_CHANGED', 'ASSIGNED', 'ASSIGNMENT_RETURNED',
            'LOANED_OUT', 'LOAN_RETURNED', 'LOAN_EXTENDED', 'LOAN_OVERDUE', 'REPAIR_STARTED', 'REPAIR_FINISHED',
            'MARKED_LOST', 'DISPOSAL_REQUESTED', 'DISPOSAL_REJECTED', 'DISPOSAL_CANCELLED', 'DISPOSED',
        ],
        AssetStatus::class => ['AVAILABLE', 'ASSIGNED', 'ON_LOAN', 'IN_REPAIR', 'PENDING_DISPOSAL', 'DISPOSED', 'LOST'],
        DisposalMethod::class => ['SALE', 'DONATION', 'SCRAP', 'RECYCLE', 'WRITE_OFF', 'OTHER'],
        DisposalReason::class => ['DAMAGED', 'OBSOLETE', 'UNECONOMICAL', 'LOST', 'OTHER'],
        DisposalStatus::class => ['DRAFT', 'PENDING_APPROVAL', 'REJECTED', 'APPROVED', 'COMPLETED', 'CANCELLED'],
        EmploymentStatus::class => ['ACTIVE', 'ON_LEAVE', 'INACTIVE'],
        ExtensionStatus::class => ['PENDING_APPROVAL', 'REJECTED', 'APPROVED', 'CANCELLED'],
        LoanStatus::class => ['CHECKED_OUT', 'OVERDUE', 'RETURNED', 'CANCELLED'],
        ProcurementStatus::class => ['DRAFT', 'PENDING_APPROVAL', 'REJECTED', 'APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CANCELLED'],
        RequestStatus::class => ['DRAFT', 'PENDING_APPROVAL', 'REJECTED', 'APPROVED', 'FULFILLED', 'CANCELLED'],
    ];

    public static function enumClasses(): array
    {
        return array_combine(
            array_map(fn ($c) => class_basename($c), array_keys(self::EXPECTED_VALUES)),
            array_map(fn ($c) => [$c], array_keys(self::EXPECTED_VALUES)),
        );
    }

    public function test_semua_enum_di_folder_enums_terdaftar_di_test_dan_memakai_has_label(): void
    {
        $found = [];
        foreach (glob(__DIR__.'/../../../app/Enums/*.php') as $file) {
            $class = 'App\\Enums\\'.basename($file, '.php');
            if (! enum_exists($class)) {
                continue;
            }
            $found[] = $class;
            $this->assertContains(HasLabel::class, class_uses($class), "{$class} harus memakai HasLabel");
        }
        sort($found);
        $expected = array_keys(self::EXPECTED_VALUES);
        sort($expected);
        $this->assertSame($expected, $found, 'Enum baru harus ditambahkan ke EnumsTest');
    }

    #[DataProvider('enumClasses')]
    public function test_nilai_enum_sesuai_desain_dan_constraint_database(string $enum): void
    {
        $this->assertSame(self::EXPECTED_VALUES[$enum], $enum::values());
    }

    #[DataProvider('enumClasses')]
    public function test_setiap_case_memiliki_label_tidak_kosong_dan_unik(string $enum): void
    {
        $labels = [];
        foreach ($enum::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotSame('', trim($label), "{$enum}::{$case->name} label kosong");
            $labels[] = $label;
        }
        $this->assertSame(count($labels), count(array_unique($labels)), "{$enum}: label ganda membingungkan pengguna");
    }

    #[DataProvider('enumClasses')]
    public function test_setiap_case_memiliki_warna_badge_yang_valid(string $enum): void
    {
        foreach ($enum::cases() as $case) {
            $this->assertContains($case->color(), self::COLORS, "{$enum}::{$case->name} warna tidak valid");
        }
    }

    #[DataProvider('enumClasses')]
    public function test_options_memetakan_value_ke_label_berurutan_sesuai_cases(string $enum): void
    {
        $options = $enum::options();
        $this->assertSame($enum::values(), array_keys($options));
        foreach ($enum::cases() as $case) {
            $this->assertSame($case->label(), $options[$case->value]);
        }
    }

    #[DataProvider('enumClasses')]
    public function test_values_adalah_list_string_dan_bisa_dibalik_dengan_from(string $enum): void
    {
        $values = $enum::values();
        $this->assertTrue(array_is_list($values));
        $this->assertCount(count($enum::cases()), $values);
        foreach ($values as $value) {
            $this->assertIsString($value);
            $this->assertSame($value, $enum::from($value)->value);
        }
        $this->assertNull($enum::tryFrom('TIDAK_ADA'));
    }

    public function test_label_status_aset_dalam_bahasa_indonesia(): void
    {
        $this->assertSame([
            'AVAILABLE' => 'Tersedia',
            'ASSIGNED' => 'Ditugaskan',
            'ON_LOAN' => 'Dipinjam',
            'IN_REPAIR' => 'Dalam Perbaikan',
            'PENDING_DISPOSAL' => 'Menunggu Disposal',
            'DISPOSED' => 'Dihapus',
            'LOST' => 'Hilang',
        ], AssetStatus::options());
    }

    public function test_warna_status_aset_sesuai_kategori_badge(): void
    {
        $this->assertSame('available', AssetStatus::Available->color());
        $this->assertSame('assigned', AssetStatus::Assigned->color());
        $this->assertSame('loan', AssetStatus::OnLoan->color());
        $this->assertSame('repair', AssetStatus::InRepair->color());
        $this->assertSame('disposal', AssetStatus::PendingDisposal->color());
        $this->assertSame('disposal', AssetStatus::Lost->color());
        $this->assertSame('neutral', AssetStatus::Disposed->color());
    }

    public function test_warna_status_permintaan_konsisten_antar_modul(): void
    {
        // Status yang sama di modul berbeda harus tampil dengan warna yang sama.
        foreach ([RequestStatus::class, ProcurementStatus::class, DisposalStatus::class, ExtensionStatus::class] as $enum) {
            $this->assertSame('pending', $enum::PendingApproval->color(), $enum);
            $this->assertSame('disposal', $enum::Rejected->color(), $enum);
            $this->assertSame('available', $enum::Approved->color(), $enum);
            $this->assertSame('neutral', $enum::Cancelled->color(), $enum);
            $this->assertSame('Menunggu Approval', $enum::PendingApproval->label(), $enum);
            $this->assertSame('Ditolak', $enum::Rejected->label(), $enum);
            $this->assertSame('Disetujui', $enum::Approved->label(), $enum);
            $this->assertSame('Dibatalkan', $enum::Cancelled->label(), $enum);
        }
        $this->assertSame('pending', ApprovalStatus::Pending->color());
        $this->assertSame('disposal', ApprovalStatus::Rejected->color());
        $this->assertSame('available', ApprovalStatus::Approved->color());
    }

    public function test_status_pinjaman_terlambat_berwarna_peringatan(): void
    {
        $this->assertSame('Terlambat', LoanStatus::Overdue->label());
        $this->assertSame('disposal', LoanStatus::Overdue->color());
        $this->assertSame('loan', LoanStatus::CheckedOut->color());
        $this->assertSame('available', LoanStatus::Returned->color());
    }

    public function test_setiap_jenis_event_aset_memiliki_ikon_bootstrap(): void
    {
        foreach (AssetEventType::cases() as $case) {
            $this->assertMatchesRegularExpression('/^bi-[a-z0-9-]+$/', $case->icon(), $case->name);
        }
        $this->assertSame('bi-archive', AssetEventType::Disposed->icon());
        $this->assertSame('bi-alarm', AssetEventType::LoanOverdue->icon());
    }

    public function test_warna_event_aset_default_neutral_untuk_event_administratif(): void
    {
        foreach ([AssetEventType::Updated, AssetEventType::StatusChanged, AssetEventType::LocationChanged,
            AssetEventType::DisposalRejected, AssetEventType::DisposalCancelled] as $case) {
            $this->assertSame('neutral', $case->color(), $case->name);
        }
        $this->assertSame('assigned', AssetEventType::Assigned->color());
        $this->assertSame('loan', AssetEventType::LoanedOut->color());
        $this->assertSame('loan', AssetEventType::LoanExtended->color());
        $this->assertSame('repair', AssetEventType::RepairStarted->color());
        $this->assertSame('disposal', AssetEventType::Disposed->color());
        $this->assertSame('available', AssetEventType::LoanReturned->color());
    }

    public function test_label_tipe_approval_dan_kondisi_aset(): void
    {
        $this->assertSame('Peminjaman', ApprovalType::Loan->label());
        $this->assertSame('Perpanjangan Pinjaman', ApprovalType::LoanExtension->label());
        $this->assertSame(['GOOD' => 'Baik', 'FAIR' => 'Cukup', 'POOR' => 'Kurang', 'DAMAGED' => 'Rusak'], AssetCondition::options());
        $this->assertSame('disposal', AssetCondition::Damaged->color());
        $this->assertSame('Aktif', EmploymentStatus::Active->label());
        $this->assertSame('Nonaktif', EmploymentStatus::Inactive->label());
    }
}
