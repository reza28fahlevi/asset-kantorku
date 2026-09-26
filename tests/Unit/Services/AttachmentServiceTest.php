<?php

namespace Tests\Unit\Services;

use App\Models\Attachment;
use App\Models\Setting;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AttachmentServiceTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private AttachmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::forget('app.settings');
        $this->service = app(AttachmentService::class);
    }

    private function passes(UploadedFile|string|null $file, bool $required = false): bool
    {
        return Validator::make(['file' => $file], ['file' => AttachmentService::rules($required)])->passes();
    }

    // ------------------------------------------------------------------ rules

    public function test_rules_opsional_dan_wajib(): void
    {
        $this->assertSame(['nullable', 'file', 'mimes:'.AttachmentService::ALLOWED_MIMES, 'extensions:'.AttachmentService::ALLOWED_MIMES, 'max:5120'], AttachmentService::rules());
        $this->assertSame('required', AttachmentService::rules(true)[0]);

        $this->assertTrue($this->passes(null));
        $this->assertFalse($this->passes(null, required: true));
        $this->assertFalse($this->passes('bukan-file'));
    }

    public function test_rules_menerima_tipe_yang_diizinkan(): void
    {
        $this->assertTrue($this->passes(UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf')));
        $this->assertTrue($this->passes(UploadedFile::fake()->image('foto.jpg')));
        $this->assertTrue($this->passes(UploadedFile::fake()->image('foto.png')));
    }

    public function test_rules_menolak_tipe_yang_tidak_diizinkan(): void
    {
        $this->assertFalse($this->passes(UploadedFile::fake()->create('skrip.php', 1, 'application/x-php')));
        $this->assertFalse($this->passes(UploadedFile::fake()->create('program.exe', 1, 'application/x-msdownload')));
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('catatan.txt', 'teks biasa')));
    }

    public function test_rules_menolak_ekstensi_nama_file_yang_tidak_cocok_dengan_daftar_izin(): void
    {
        // Isi terdeteksi PDF tetapi nama berekstensi .html: ekstensi inilah yang dipakai saat file disimpan.
        $this->assertFalse($this->passes(UploadedFile::fake()->create('laporan.html', 10, 'application/pdf')));
        $this->assertFalse($this->passes(UploadedFile::fake()->create('tanpa-ekstensi', 10, 'application/pdf')));
        $this->assertTrue($this->passes(UploadedFile::fake()->create('LAPORAN.PDF', 10, 'application/pdf')), 'huruf besar tetap diterima');
    }

    public function test_batas_ukuran_mengikuti_setting(): void
    {
        $this->assertTrue($this->passes(UploadedFile::fake()->create('pas.pdf', 5120, 'application/pdf')));
        $this->assertFalse($this->passes(UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')));

        Setting::put('attachment.max_size_kb', 100);

        $this->assertContains('max:100', AttachmentService::rules());
        $this->assertTrue($this->passes(UploadedFile::fake()->create('kecil.pdf', 100, 'application/pdf')));
        $this->assertFalse($this->passes(UploadedFile::fake()->create('lebih.pdf', 101, 'application/pdf')));
    }

    public function test_batas_ukuran_default_bila_setting_tidak_ada(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->where('key', 'attachment.max_size_kb')->delete();
        Cache::forget('app.settings');

        $this->assertContains('max:5120', AttachmentService::rules());
    }

    // ------------------------------------------------------------------ store

    public function test_store_menyimpan_file_ke_disk_privat_dengan_metadata(): void
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);
        $asset = $this->makeAsset();

        $this->service->store($asset, [UploadedFile::fake()->create('Bukti Terima.PDF', 20, 'application/pdf')], 'RECEIPT');

        $attachment = Attachment::where('attachable_type', 'asset')->where('attachable_id', $asset->id)->sole();
        $this->assertSame('RECEIPT', $attachment->category);
        $this->assertSame('Bukti Terima.PDF', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame(20 * 1024, $attachment->size_bytes);
        $this->assertSame($admin->id, $attachment->uploaded_by_user_id);
        $this->assertMatchesRegularExpression("#^attachments/asset/{$asset->id}/[0-9a-f-]{36}\\.PDF$#", $attachment->stored_path);
        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertTrue($asset->attachments()->first()->is($attachment));
    }

    public function test_store_banyak_file_dengan_nama_unik(): void
    {
        $request = $this->makeProcurement(5);

        $this->service->store($request, [
            UploadedFile::fake()->create('quotation.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('quotation.pdf', 10, 'application/pdf'),
        ], 'QUOTATION');

        $paths = Attachment::where('attachable_type', 'procurement_request')->where('attachable_id', $request->id)->pluck('stored_path');
        $this->assertCount(2, $paths);
        $this->assertCount(2, $paths->unique(), 'nama file tersimpan tidak boleh bertabrakan');
        foreach ($paths as $path) {
            $this->assertStringStartsWith("attachments/procurement_request/{$request->id}/", $path);
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_store_menerima_satu_file_tunggal(): void
    {
        $asset = $this->makeAsset();

        $this->service->store($asset, UploadedFile::fake()->create('tunggal.pdf', 5, 'application/pdf'));

        $attachment = Attachment::where('attachable_type', 'asset')->where('attachable_id', $asset->id)->sole();
        $this->assertSame('tunggal.pdf', $attachment->original_name);
        $this->assertSame('OTHER', $attachment->category, 'kategori default');
    }

    public function test_store_tanpa_file_tidak_melakukan_apa_apa(): void
    {
        $asset = $this->makeAsset();

        $this->service->store($asset, null);
        $this->service->store($asset, []);
        $this->service->store($asset, [null]);

        $this->assertSame(0, Attachment::where('attachable_type', 'asset')->where('attachable_id', $asset->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_store_memotong_nama_asli_yang_terlalu_panjang(): void
    {
        $asset = $this->makeAsset();
        $longName = str_repeat('a', 300).'.pdf';

        $this->service->store($asset, [UploadedFile::fake()->create($longName, 1, 'application/pdf')]);

        $attachment = Attachment::where('attachable_type', 'asset')->where('attachable_id', $asset->id)->sole();
        $this->assertSame(255, mb_strlen($attachment->original_name));
    }

    public function test_store_tanpa_login_mencatat_uploader_null(): void
    {
        $asset = $this->makeAsset();
        $this->service->store($asset, [UploadedFile::fake()->create('x.pdf', 1, 'application/pdf')]);

        $this->assertNull(Attachment::where('attachable_type', 'asset')->where('attachable_id', $asset->id)->value('uploaded_by_user_id'));
    }
}
