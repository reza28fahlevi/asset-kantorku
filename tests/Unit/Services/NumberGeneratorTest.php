<?php

namespace Tests\Unit\Services;

use App\Services\NumberGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit test NumberGenerator: urutan per key, format nomor dokumen & asset tag, reset per tahun.
 * Setiap test memakai prefix unik agar tidak bergantung/berbenturan dengan data lain.
 */
class NumberGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    private NumberGenerator $numbers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbers = app(NumberGenerator::class);
        Carbon::setTestNow('2026-06-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prefix(): string
    {
        return 'T'.strtoupper(substr(md5(uniqid('', true)), 0, 7));
    }

    private function lastValue(string $key): ?int
    {
        $value = DB::table('number_sequences')->where('key', $key)->value('last_value');

        return $value === null ? null : (int) $value;
    }

    public function test_key_baru_dimulai_dari_satu_dan_bertambah_berurutan(): void
    {
        $key = $this->prefix().'-SEQ';
        $this->assertNull($this->lastValue($key));

        $this->assertSame(1, $this->numbers->next($key));
        $this->assertSame(2, $this->numbers->next($key));
        $this->assertSame(3, $this->numbers->next($key));
        $this->assertSame(3, $this->lastValue($key));
    }

    public function test_melanjutkan_dari_nilai_terakhir_yang_tersimpan(): void
    {
        $key = $this->prefix().'-EXIST';
        DB::table('number_sequences')->insert(['key' => $key, 'last_value' => 41, 'updated_at' => now()]);

        $this->assertSame(42, $this->numbers->next($key));
        $this->assertSame(42, $this->lastValue($key));
    }

    public function test_setiap_key_memiliki_urutan_sendiri(): void
    {
        $a = $this->prefix().'-A';
        $b = $this->prefix().'-B';

        $this->assertSame(1, $this->numbers->next($a));
        $this->assertSame(2, $this->numbers->next($a));
        $this->assertSame(1, $this->numbers->next($b));
        $this->assertSame(3, $this->numbers->next($a));
        $this->assertSame(2, $this->numbers->next($b));
    }

    public function test_request_number_berformat_prefix_tahun_lima_digit(): void
    {
        $prefix = $this->prefix();

        $this->assertSame("{$prefix}-2026-00001", $this->numbers->requestNumber($prefix));
        $this->assertSame("{$prefix}-2026-00002", $this->numbers->requestNumber($prefix));
        $this->assertSame(2, $this->lastValue("{$prefix}-2026"));
    }

    public function test_request_number_dimulai_ulang_setiap_tahun(): void
    {
        $prefix = $this->prefix();
        $this->numbers->requestNumber($prefix);
        $this->numbers->requestNumber($prefix);

        Carbon::setTestNow('2027-01-01 00:00:01');
        $this->assertSame("{$prefix}-2027-00001", $this->numbers->requestNumber($prefix));

        // Tahun sebelumnya tetap melanjutkan urutannya sendiri
        Carbon::setTestNow('2026-12-31 23:59:59');
        $this->assertSame("{$prefix}-2026-00003", $this->numbers->requestNumber($prefix));
    }

    public function test_request_number_dengan_prefix_berbeda_tidak_saling_mempengaruhi(): void
    {
        $pr = $this->prefix();
        $asg = $this->prefix();

        $this->assertSame("{$pr}-2026-00001", $this->numbers->requestNumber($pr));
        $this->assertSame("{$asg}-2026-00001", $this->numbers->requestNumber($asg));
        $this->assertSame("{$pr}-2026-00002", $this->numbers->requestNumber($pr));
    }

    public function test_request_number_melebihi_lima_digit_tetap_unik(): void
    {
        $prefix = $this->prefix();
        DB::table('number_sequences')->insert(['key' => "{$prefix}-2026", 'last_value' => 99999, 'updated_at' => now()]);

        $this->assertSame("{$prefix}-2026-100000", $this->numbers->requestNumber($prefix));
    }

    public function test_nomor_yang_dihasilkan_selalu_unik(): void
    {
        $prefix = $this->prefix();
        $generated = [];
        for ($i = 0; $i < 50; $i++) {
            $generated[] = $this->numbers->requestNumber($prefix);
        }

        $this->assertCount(50, array_unique($generated));
        $this->assertSame("{$prefix}-2026-00050", end($generated));
    }

    public function test_asset_tag_berformat_kode_kategori_uppercase(): void
    {
        $code = strtolower($this->prefix());
        $upper = strtoupper($code);

        $this->assertSame("{$upper}-2026-00001", $this->numbers->assetTag($code));
        $this->assertSame("{$upper}-2026-00002", $this->numbers->assetTag($upper));
        $this->assertSame(2, $this->lastValue("ASSET-{$upper}-2026"));
    }

    public function test_urutan_asset_tag_terpisah_dari_nomor_permintaan_dengan_kode_sama(): void
    {
        $code = $this->prefix();

        $this->assertSame("{$code}-2026-00001", $this->numbers->requestNumber($code));
        $this->assertSame("{$code}-2026-00001", $this->numbers->assetTag($code));
        $this->assertSame(1, $this->lastValue("{$code}-2026"));
        $this->assertSame(1, $this->lastValue("ASSET-{$code}-2026"));
    }

    public function test_asset_tag_melanjutkan_urutan_kategori_demo_dan_tidak_bentrok_dengan_aset_yang_ada(): void
    {
        $current = (int) DB::table('number_sequences')->where('key', 'ASSET-LPT-2026')->value('last_value');

        $tag = $this->numbers->assetTag('LPT');

        $this->assertSame(sprintf('LPT-2026-%05d', $current + 1), $tag);
        $this->assertFalse(DB::table('assets')->where('asset_tag', $tag)->exists());
    }

    public function test_nomor_yang_dipakai_dalam_transaksi_yang_dibatalkan_ikut_dikembalikan(): void
    {
        $prefix = $this->prefix();
        $this->numbers->requestNumber($prefix);

        try {
            DB::transaction(function () use ($prefix) {
                $this->numbers->requestNumber($prefix);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        // Tidak ada lompatan nomor karena counter berada di transaksi yang sama
        $this->assertSame("{$prefix}-2026-00002", $this->numbers->requestNumber($prefix));
    }
}
