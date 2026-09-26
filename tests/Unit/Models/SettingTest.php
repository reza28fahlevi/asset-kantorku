<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('app.settings');
    }

    public function test_get_membaca_nilai_seed(): void
    {
        $this->assertSame('30', Setting::get('loan.max_duration_days'));
        $this->assertSame('5120', Setting::get('attachment.max_size_kb'));
        $this->assertSame('1', Setting::get('approval.escalation_employee_id'));
    }

    public function test_get_mengembalikan_default_bila_kunci_tidak_ada(): void
    {
        $this->assertNull(Setting::get('tidak.ada'));
        $this->assertSame('x', Setting::get('tidak.ada', 'x'));
        $this->assertSame(10, Setting::get('tidak.ada', 10));
    }

    public function test_get_menggunakan_cache(): void
    {
        $this->assertSame('30', Setting::get('loan.max_duration_days'));
        DB::table('settings')->where('key', 'loan.max_duration_days')->update(['value' => '99']);

        $this->assertSame('30', Setting::get('loan.max_duration_days'), 'nilai dari cache');
        $this->assertTrue(Cache::has('app.settings'));
    }

    public function test_put_memperbarui_nilai_dan_menghapus_cache(): void
    {
        $this->assertSame('30', Setting::get('loan.max_duration_days'));

        Setting::put('loan.max_duration_days', 14);

        $this->assertFalse(Cache::has('app.settings'));
        $this->assertSame('14', Setting::get('loan.max_duration_days'));
        $this->assertSame(1, Setting::where('key', 'loan.max_duration_days')->count());
        $this->assertNotNull(Setting::find('loan.max_duration_days')->updated_at);
    }

    public function test_put_membuat_kunci_baru(): void
    {
        $this->assertNull(Setting::get('ut3.kunci_baru'));

        Setting::put('ut3.kunci_baru', 'nilai');

        $this->assertSame('nilai', Setting::get('ut3.kunci_baru'));
        $this->assertSame('nilai', Setting::find('ut3.kunci_baru')->value);
    }

    public function test_nilai_kosong_tetap_dikembalikan_bukan_default(): void
    {
        Setting::put('ut3.kosong', '');
        $this->assertSame('', Setting::get('ut3.kosong', 'default'));
    }
}
