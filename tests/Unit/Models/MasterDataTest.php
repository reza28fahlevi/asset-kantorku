<?php

namespace Tests\Unit\Models;

use App\Enums\ProcurementStatus;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\ProcurementRequestItem;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

/** Scope active & isInUse pada master (department, lokasi, kategori, vendor). */
class MasterDataTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_scope_active_pada_semua_master(): void
    {
        foreach ([
            Department::class => ['code' => 'UT3D', 'name' => 'Dept Uji'],
            Location::class => ['code' => 'UT3L', 'name' => 'Lokasi Uji'],
            AssetCategory::class => ['code' => 'UT3', 'name' => 'Kategori Uji'],
            Vendor::class => ['code' => 'UT3V', 'name' => 'Vendor Uji'],
        ] as $class => $attrs) {
            $active = $class::create($attrs + ['is_active' => true]);
            $inactive = $class::create(['code' => $attrs['code'].'X'] + $attrs + ['is_active' => false]);

            $ids = $class::query()->whereIn('id', [$active->id, $inactive->id])->active()->pluck('id')->all();
            $this->assertSame([$active->id], $ids, $class);
        }
    }

    public function test_master_baru_tidak_sedang_dipakai(): void
    {
        $this->assertFalse(Department::create(['code' => 'UT3D', 'name' => 'Dept'])->isInUse());
        $this->assertFalse(Location::create(['code' => 'UT3L', 'name' => 'Lokasi'])->isInUse());
        $this->assertFalse(AssetCategory::create(['code' => 'UT3', 'name' => 'Kategori'])->isInUse());
        $this->assertFalse(Vendor::create(['code' => 'UT3V', 'name' => 'Vendor'])->isInUse());
    }

    public function test_master_demo_yang_dipakai_transaksi_terdeteksi(): void
    {
        $this->assertTrue(Department::find(2)->isInUse(), 'departemen punya karyawan');
        $this->assertTrue(Location::find(1)->isInUse(), 'lokasi punya sub-lokasi/aset');
        $this->assertTrue(AssetCategory::find(1)->isInUse(), 'kategori punya aset');
    }

    public function test_is_in_use_mendeteksi_setiap_jenis_referensi(): void
    {
        $location = Location::create(['code' => 'UT3L', 'name' => 'Lokasi']);
        $this->makeAsset(['location_id' => $location->id]);
        $this->assertTrue($location->isInUse(), 'aset');

        $child = Location::create(['code' => 'UT3P', 'name' => 'Induk']);
        Location::create(['code' => 'UT3C', 'name' => 'Anak', 'parent_id' => $child->id]);
        $this->assertTrue($child->isInUse(), 'sub-lokasi');

        $loanLocation = Location::create(['code' => 'UT3U', 'name' => 'Pemakaian']);
        $this->makeLoanRequest(5, 5)->update(['usage_location_id' => $loanLocation->id]);
        $this->assertTrue($loanLocation->isInUse(), 'lokasi pemakaian loan');

        $category = AssetCategory::create(['code' => 'UT3', 'name' => 'Kategori']);
        ProcurementRequestItem::create([
            'procurement_request_id' => $this->makeProcurement(5, ProcurementStatus::Draft)->id,
            'asset_category_id' => $category->id, 'item_name' => 'Barang', 'quantity' => 1,
        ]);
        $this->assertTrue($category->isInUse(), 'item procurement');

        $vendor = Vendor::create(['code' => 'UT3V', 'name' => 'Vendor']);
        $this->makeAsset(['vendor_id' => $vendor->id]);
        $this->assertTrue($vendor->isInUse(), 'aset dari vendor');

        $department = Department::create(['code' => 'UT3D', 'name' => 'Dept']);
        $this->makeProcurement(5)->update(['department_id' => $department->id]);
        $this->assertTrue($department->isInUse(), 'procurement departemen');
    }

    public function test_relasi_aset_tetap_memuat_master_yang_dihapus_lunak(): void
    {
        $location = Location::create(['code' => 'UT3L', 'name' => 'Lokasi Lama']);
        $asset = $this->makeAsset(['location_id' => $location->id]);
        $location->delete();

        $this->assertSame('Lokasi Lama', $asset->fresh()->location?->name);
    }
}
