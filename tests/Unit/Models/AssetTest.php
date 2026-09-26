<?php

namespace Tests\Unit\Models;

use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\Asset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    /** @return list<string> tag aset hasil pencarian yang dibatasi ke aset uji ini */
    private function search(?string $term, array $ids): array
    {
        return Asset::query()->whereIn('id', $ids)->search($term)->orderBy('id')->pluck('asset_tag')->all();
    }

    public function test_search_kosong_atau_null_tidak_memfilter(): void
    {
        $a = $this->makeAsset();
        $b = $this->makeAsset();
        $ids = [$a->id, $b->id];

        $this->assertCount(2, $this->search(null, $ids));
        $this->assertCount(2, $this->search('', $ids));
        $this->assertCount(2, $this->search('   ', $ids));
    }

    public function test_search_mencocokkan_tag_nama_serial_merek_dan_model_tanpa_peduli_huruf(): void
    {
        $asset = $this->makeAsset([
            'name' => 'Proyektor Ruang Rapat', 'serial_number' => 'SNUJI-ABC-123', 'brand' => 'Epsonik', 'model' => 'EB-X500Q',
        ]);
        $other = $this->makeAsset(['name' => 'Meja Kerja']);
        $ids = [$asset->id, $other->id];

        foreach ([strtolower($asset->asset_tag), 'ruang RAPAT', 'snuji-abc', 'EPSONIK', 'x500q'] as $term) {
            $this->assertSame([$asset->asset_tag], $this->search($term, $ids), $term);
        }
        $this->assertSame([], $this->search('tidak-ada-yang-cocok', $ids));
    }

    public function test_search_memperlakukan_persen_dan_underscore_sebagai_karakter_biasa(): void
    {
        $percent = $this->makeAsset(['name' => 'Diskon 100% Kursi']);
        $underscore = $this->makeAsset(['name' => 'kabel_lan']);
        $plain = $this->makeAsset(['name' => 'Diskon 1000 Kursi kabelXlan']);
        $ids = [$percent->id, $underscore->id, $plain->id];

        $this->assertSame([$percent->asset_tag], $this->search('100%', $ids));
        $this->assertSame([$underscore->asset_tag], $this->search('kabel_lan', $ids));
        $this->assertSame([], $this->search('%%%', [$plain->id]));
        $this->assertSame([], $this->search('_', [$plain->id]));
    }

    public function test_search_memperlakukan_backslash_sebagai_karakter_biasa(): void
    {
        $withSlash = $this->makeAsset(['name' => 'Share \\\\server\\data']);
        $plain = $this->makeAsset(['name' => 'Share server data 100%']);
        $ids = [$withSlash->id, $plain->id];

        $this->assertSame([$withSlash->asset_tag], $this->search('\\server', $ids));
        $this->assertSame([$withSlash->asset_tag], $this->search('\\', $ids));
        // "0\%" tidak boleh berubah makna menjadi "0%" (backslash milik pengguna ikut di-escape)
        $this->assertSame([], $this->search('0\\%', $ids));
    }

    public function test_scope_available_hanya_aset_tersedia(): void
    {
        $available = $this->makeAsset();
        $repair = $this->makeAsset(['status' => AssetStatus::InRepair]);
        $ids = Asset::query()->whereIn('id', [$available->id, $repair->id])->available()->pluck('id')->all();

        $this->assertSame([$available->id], $ids);
    }

    public function test_is_disposed_hanya_untuk_status_disposed(): void
    {
        foreach (AssetStatus::cases() as $status) {
            $asset = new Asset(['status' => $status]);
            $this->assertSame($status === AssetStatus::Disposed, $asset->isDisposed(), $status->value);
        }
    }

    public function test_current_holder_kosong_bila_tidak_ada_transaksi_aktif(): void
    {
        $this->assertNull($this->makeAsset()->currentHolder());
    }

    public function test_current_holder_adalah_penerima_assignment_aktif(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::Assigned]);
        $this->makeAssignment(5, asset: $asset);

        $this->assertSame(5, $asset->fresh()->currentHolder()?->id);
    }

    public function test_current_holder_adalah_peminjam_loan_aktif_termasuk_terlambat(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::OnLoan]);
        $this->makeLoan(7, LoanStatus::Overdue, dueAt: now()->subDay()->startOfMinute(), asset: $asset);

        $this->assertSame(7, $asset->fresh()->currentHolder()?->id);
    }

    public function test_current_holder_kosong_setelah_assignment_dan_loan_selesai(): void
    {
        $asset = $this->makeAsset();
        $this->makeAssignment(5, returned: true, asset: $asset);
        $this->makeLoan(7, LoanStatus::Returned, asset: $asset);

        $this->assertNull($asset->fresh()->currentHolder());
        $this->assertNull($asset->fresh()->activeLoan);
        $this->assertNull($asset->fresh()->activeAssignment);
    }

    public function test_garansi_segera_berakhir(): void
    {
        $this->assertFalse((new Asset)->isWarrantyExpiringSoon(), 'tanpa garansi');
        $this->assertFalse(new Asset(['warranty_end_date' => now()->subDay()])->isWarrantyExpiringSoon(), 'sudah lewat');
        $this->assertTrue(new Asset(['warranty_end_date' => now()->addDays(10)])->isWarrantyExpiringSoon(), '10 hari');
        $this->assertTrue(new Asset(['warranty_end_date' => now()->addDays(30)])->isWarrantyExpiringSoon(), 'batas 30 hari');
        $this->assertFalse(new Asset(['warranty_end_date' => now()->addDays(32)])->isWarrantyExpiringSoon(), '32 hari');
        $this->assertTrue(new Asset(['warranty_end_date' => now()->addDays(50)])->isWarrantyExpiringSoon(60), 'ambang kustom');
    }

    public function test_aset_disposed_tidak_bisa_diaktifkan_kembali_oleh_database(): void
    {
        $asset = $this->makeAsset(['status' => AssetStatus::Disposed]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $asset->update(['status' => AssetStatus::Available]);
    }
}
