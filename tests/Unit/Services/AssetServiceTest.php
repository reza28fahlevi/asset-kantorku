<?php

namespace Tests\Unit\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetEventType;
use App\Enums\AssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit test AssetService: registrasi manual, ubah data, perubahan status operasional,
 * serta pencatatan event timeline aset.
 */
class AssetServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AssetService $service;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AssetService::class);
        $this->admin = User::where('email', 'admin.aset@kantorku.test')->firstOrFail();
        $this->actingAs($this->admin);
    }

    // ---------------------------------------------------------------- helpers

    private function makeAsset(AssetStatus $status = AssetStatus::Available, array $attributes = []): Asset
    {
        return Asset::create(array_merge([
            'asset_tag' => 'UT1-'.uniqid(),
            'name' => 'Aset Uji',
            'asset_category_id' => 8,
            'location_id' => 1,
            'status' => $status,
            'condition' => AssetCondition::Good,
        ], $attributes));
    }

    /** @return \Illuminate\Support\Collection<int, AssetEvent> */
    private function events(Asset $asset)
    {
        return AssetEvent::where('asset_id', $asset->id)->orderBy('id')->get();
    }

    private function lastSequence(string $code): int
    {
        return (int) DB::table('number_sequences')->where('key', "ASSET-{$code}-".now()->format('Y'))->value('last_value');
    }

    // --------------------------------------------------------------- register

    public function test_register_membuat_aset_tersedia_dengan_tag_berurutan_dan_event_registrasi(): void
    {
        $before = $this->lastSequence('FUR');

        $asset = $this->service->register([
            'name' => 'Kursi Uji', 'asset_category_id' => 8, 'location_id' => 2,
            'brand' => 'Informa', 'purchase_cost' => 1500000,
        ]);

        $asset->refresh();
        $this->assertSame(sprintf('FUR-%s-%05d', now()->format('Y'), $before + 1), $asset->asset_tag);
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertSame(AssetCondition::Good, $asset->condition);
        $this->assertSame(2, $asset->location_id);
        $this->assertSame('Informa', $asset->brand);

        $events = $this->events($asset);
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(AssetEventType::Registered, $event->event_type);
        $this->assertNull($event->from_status);
        $this->assertSame(AssetStatus::Available, $event->to_status);
        $this->assertNull($event->from_location_id);
        $this->assertSame(2, $event->to_location_id);
        $this->assertSame('Registrasi manual', $event->notes);
        $this->assertSame($this->admin->id, $event->performed_by_user_id);
        $this->assertSame($this->admin->employee_id, $event->performed_by_employee_id);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_register_dua_kali_menghasilkan_tag_berbeda_berurutan(): void
    {
        $a = $this->service->register(['name' => 'Meja A', 'asset_category_id' => 8, 'location_id' => 1]);
        $b = $this->service->register(['name' => 'Meja B', 'asset_category_id' => 8, 'location_id' => 1]);

        $this->assertNotSame($a->asset_tag, $b->asset_tag);
        $this->assertSame((int) substr($a->asset_tag, -5) + 1, (int) substr($b->asset_tag, -5));
    }

    public function test_register_menyimpan_kondisi_yang_diberikan(): void
    {
        $asset = $this->service->register(['name' => 'Kursi Bekas', 'asset_category_id' => 8, 'location_id' => 1, 'condition' => 'FAIR']);

        $this->assertSame(AssetCondition::Fair, $asset->fresh()->condition);
    }

    public function test_register_mengabaikan_status_dan_tag_dari_input(): void
    {
        $asset = $this->service->register([
            'name' => 'Kursi Nakal', 'asset_category_id' => 8, 'location_id' => 1,
            'status' => 'DISPOSED', 'asset_tag' => 'HACK-0001',
        ]);

        $asset->refresh();
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertStringStartsWith('FUR-', $asset->asset_tag);
    }

    public function test_register_kategori_wajib_serial_tanpa_nomor_seri_ditolak(): void
    {
        $before = Asset::count();
        $sequence = $this->lastSequence('LPT');

        try {
            $this->service->register(['name' => 'Laptop Tanpa Seri', 'asset_category_id' => 1, 'location_id' => 1, 'serial_number' => '  ']);
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('Nomor seri wajib', $e->getMessage());
        }

        $this->assertSame($before, Asset::count());
        $this->assertSame($sequence, $this->lastSequence('LPT'));
    }

    public function test_register_kategori_wajib_serial_dengan_nomor_seri_berhasil(): void
    {
        $asset = $this->service->register(['name' => 'Laptop', 'asset_category_id' => 1, 'location_id' => 1, 'serial_number' => 'SN-UT1-'.uniqid()]);

        $this->assertStringStartsWith('LPT-', $asset->asset_tag);
    }

    public function test_register_kategori_tidak_ada_gagal_tanpa_memakai_nomor(): void
    {
        $before = Asset::count();

        $this->expectException(ModelNotFoundException::class);
        try {
            $this->service->register(['name' => 'X', 'asset_category_id' => 999999, 'location_id' => 1]);
        } finally {
            $this->assertSame($before, Asset::count());
        }
    }

    public function test_register_tanpa_user_login_mencatat_pelaku_null(): void
    {
        auth()->logout();

        $asset = $this->service->register(['name' => 'Migrasi', 'asset_category_id' => 8, 'location_id' => 1]);

        $event = $this->events($asset)->first();
        $this->assertNull($event->performed_by_user_id);
        $this->assertNull($event->performed_by_employee_id);
    }

    // ----------------------------------------------------------------- update

    public function test_update_data_deskriptif_mencatat_event_updated_dengan_daftar_kolom(): void
    {
        $asset = $this->makeAsset();

        $this->service->update($asset, ['name' => 'Aset Uji Baru', 'brand' => 'Dell', 'location_id' => 1]);

        $this->assertSame('Aset Uji Baru', $asset->fresh()->name);
        $events = $this->events($asset);
        $this->assertCount(1, $events);
        $this->assertSame(AssetEventType::Updated, $events[0]->event_type);
        $this->assertEqualsCanonicalizing(['name', 'brand'], $events[0]->metadata['fields']);
        $this->assertSame($this->admin->id, $events[0]->performed_by_user_id);
    }

    public function test_update_lokasi_mencatat_event_lokasi_saja(): void
    {
        $asset = $this->makeAsset();

        $this->service->update($asset, ['location_id' => 3, 'name' => 'Aset Uji']);

        $this->assertSame(3, $asset->fresh()->location_id);
        $events = $this->events($asset);
        $this->assertCount(1, $events);
        $this->assertSame(AssetEventType::LocationChanged, $events[0]->event_type);
        $this->assertSame(1, $events[0]->from_location_id);
        $this->assertSame(3, $events[0]->to_location_id);
    }

    public function test_update_lokasi_dan_data_mencatat_dua_event(): void
    {
        $asset = $this->makeAsset();

        $this->service->update($asset, ['location_id' => 4, 'notes' => 'Pindah gedung']);

        $types = $this->events($asset)->pluck('event_type')->all();
        $this->assertSame([AssetEventType::LocationChanged, AssetEventType::Updated], $types);
        $this->assertSame(['notes'], $this->events($asset)[1]->metadata['fields']);
    }

    public function test_update_tanpa_perubahan_tidak_mencatat_event(): void
    {
        $asset = $this->makeAsset(attributes: ['purchase_cost' => 14000000, 'purchase_date' => '2026-01-10']);

        $this->service->update(Asset::find($asset->id), [
            'name' => 'Aset Uji', 'location_id' => '1', 'purchase_cost' => '14000000', 'purchase_date' => '2026-01-10',
        ]);

        $this->assertCount(0, $this->events($asset));
    }

    public function test_update_aset_disposed_ditolak(): void
    {
        $asset = $this->makeAsset(AssetStatus::Disposed);

        try {
            $this->service->update($asset, ['name' => 'Ubah']);
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('sudah dihapus', $e->getMessage());
        }

        $this->assertSame('Aset Uji', $asset->fresh()->name);
        $this->assertCount(0, $this->events($asset));
    }

    public static function editableStatuses(): array
    {
        return array_map(fn (AssetStatus $s) => [$s], array_filter(AssetStatus::cases(), fn ($s) => $s !== AssetStatus::Disposed));
    }

    #[DataProvider('editableStatuses')]
    public function test_update_diizinkan_untuk_status_selain_disposed(AssetStatus $status): void
    {
        $asset = $this->makeAsset($status);

        $this->service->update($asset, ['notes' => 'Catatan']);

        $this->assertSame('Catatan', $asset->fresh()->notes);
        $this->assertSame($status, $asset->fresh()->status);
    }

    // ------------------------------------------------- changeOperationalStatus

    public static function validTransitions(): array
    {
        return [
            'repair dari tersedia' => [AssetStatus::Available, 'repair', AssetStatus::InRepair, AssetEventType::RepairStarted],
            'repaired dari perbaikan' => [AssetStatus::InRepair, 'repaired', AssetStatus::Available, AssetEventType::RepairFinished],
            'lost dari tersedia' => [AssetStatus::Available, 'lost', AssetStatus::Lost, AssetEventType::MarkedLost],
            'lost dari perbaikan' => [AssetStatus::InRepair, 'lost', AssetStatus::Lost, AssetEventType::MarkedLost],
            'found dari hilang' => [AssetStatus::Lost, 'found', AssetStatus::Available, AssetEventType::StatusChanged],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_perubahan_status_operasional_yang_valid(AssetStatus $from, string $action, AssetStatus $to, AssetEventType $type): void
    {
        $asset = $this->makeAsset($from);

        $this->service->changeOperationalStatus($asset, $action, "Catatan {$action}");

        $this->assertSame($to, $asset->fresh()->status);
        $this->assertSame(AssetCondition::Good, $asset->fresh()->condition);
        $events = $this->events($asset);
        $this->assertCount(1, $events);
        $this->assertSame($type, $events[0]->event_type);
        $this->assertSame($from, $events[0]->from_status);
        $this->assertSame($to, $events[0]->to_status);
        $this->assertSame(1, $events[0]->from_location_id);
        $this->assertSame(1, $events[0]->to_location_id);
        $this->assertSame("Catatan {$action}", $events[0]->notes);
        $this->assertSame($this->admin->id, $events[0]->performed_by_user_id);
    }

    public function test_perubahan_status_dapat_mengubah_kondisi(): void
    {
        $asset = $this->makeAsset();

        $this->service->changeOperationalStatus($asset, 'repair', null, 'DAMAGED');
        $this->assertSame(AssetCondition::Damaged, $asset->fresh()->condition);
        $this->assertNull($this->events($asset)->last()->notes);

        $this->service->changeOperationalStatus($asset, 'repaired', 'Sudah diganti part', 'GOOD');
        $this->assertSame(AssetCondition::Good, $asset->fresh()->condition);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_siklus_lengkap_status_operasional_mencatat_setiap_event(): void
    {
        $asset = $this->makeAsset();

        foreach (['repair', 'repaired', 'lost', 'found'] as $action) {
            $this->service->changeOperationalStatus($asset, $action, null);
        }

        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertSame(
            [AssetEventType::RepairStarted, AssetEventType::RepairFinished, AssetEventType::MarkedLost, AssetEventType::StatusChanged],
            $this->events($asset)->pluck('event_type')->all()
        );
    }

    public static function invalidTransitions(): array
    {
        $cases = [];
        $allowed = [
            'repair' => [AssetStatus::Available],
            'repaired' => [AssetStatus::InRepair],
            'lost' => [AssetStatus::Available, AssetStatus::InRepair],
            'found' => [AssetStatus::Lost],
        ];
        foreach ($allowed as $action => $from) {
            foreach (AssetStatus::cases() as $status) {
                if (! in_array($status, $from, true)) {
                    $cases["{$action} dari {$status->value}"] = [$status, $action];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('invalidTransitions')]
    public function test_perubahan_status_operasional_yang_tidak_valid_ditolak(AssetStatus $from, string $action): void
    {
        $asset = $this->makeAsset($from);

        try {
            $this->service->changeOperationalStatus($asset, $action, 'x', 'POOR');
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString($asset->asset_tag, $e->getMessage());
            $this->assertStringContainsString($from->label(), $e->getMessage());
        }

        $this->assertSame($from, $asset->fresh()->status);
        $this->assertSame(AssetCondition::Good, $asset->fresh()->condition);
        $this->assertCount(0, $this->events($asset));
    }

    public function test_aksi_status_tidak_dikenal_ditolak(): void
    {
        $asset = $this->makeAsset();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Aksi status tidak dikenal');
        try {
            $this->service->changeOperationalStatus($asset, 'dispose', null);
        } finally {
            $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        }
    }

    public function test_perubahan_status_memakai_status_terkini_dari_database_bukan_objek_basi(): void
    {
        $stale = $this->makeAsset();
        Asset::whereKey($stale->id)->update(['status' => AssetStatus::OnLoan->value]);

        $this->expectException(BusinessRuleException::class);
        try {
            $this->service->changeOperationalStatus($stale, 'repair', null);
        } finally {
            $this->assertSame(AssetStatus::OnLoan, $stale->fresh()->status);
        }
    }

    // ------------------------------------------------------------ lockAvailable

    public function test_lock_available_mengembalikan_aset_tersedia(): void
    {
        $a = $this->makeAsset();
        $b = $this->makeAsset();

        $locked = $this->service->lockAvailable([$b->id, $a->id, $a->id]);

        $this->assertSame([$a->id, $b->id], $locked->pluck('id')->all());
    }

    public function test_lock_available_gagal_bila_aset_tidak_ditemukan(): void
    {
        $a = $this->makeAsset();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak ditemukan');
        $this->service->lockAvailable([$a->id, 999999]);
    }

    public function test_lock_available_gagal_bila_ada_aset_tidak_tersedia(): void
    {
        $a = $this->makeAsset();
        $b = $this->makeAsset(AssetStatus::InRepair);

        try {
            $this->service->lockAvailable([$a->id, $b->id]);
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString($b->asset_tag, $e->getMessage());
            $this->assertStringContainsString('Dalam Perbaikan', $e->getMessage());
            $this->assertStringNotContainsString($a->asset_tag, $e->getMessage());
        }
    }

    // -------------------------------------------------------------- recordEvent

    public function test_event_aset_bersifat_append_only(): void
    {
        $asset = $this->makeAsset();
        $event = $this->service->recordEvent($asset, AssetEventType::StatusChanged, ['notes' => 'awal']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(fn () => AssetEvent::whereKey($event->id)->update(['notes' => 'ubah']));
    }
}
