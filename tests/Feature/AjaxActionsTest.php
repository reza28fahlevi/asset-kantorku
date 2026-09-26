<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetLoanRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\ActsAsDemoUsers;
use Tests\TestCase;

/**
 * Semua tombol aksi dikirim via AJAX (data-ajax-form, Accept: application/json) dan menerima
 * JSON {status, message, redirect} untuk ditampilkan SweetAlert; error validasi 422 per field,
 * pelanggaran aturan bisnis 422 {message}, tanpa hak akses 403.
 */
class AjaxActionsTest extends TestCase
{
    use ActsAsDemoUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function ok(TestResponse $response, ?string $redirect = null): TestResponse
    {
        $response->assertOk()->assertJsonPath('status', 'success')->assertJsonStructure(['status', 'message', 'redirect']);
        $this->assertNotEmpty($response->json('message'));
        if ($redirect !== null) {
            $response->assertJsonPath('redirect', $redirect);
        }

        return $response;
    }

    private function ajax(User $user, string $method, string $url, array $data = []): TestResponse
    {
        return $this->actingAs($user)->json($method, $url, $data);
    }

    public function test_semua_form_aksi_ditandai_ajax(): void
    {
        $admin = $this->user('admin.aset');
        $sysadmin = $this->user('sysadmin');
        $pages = [
            [$admin, route('assets.create')], [$admin, route('procurements.create')], [$admin, route('assignments.create')],
            [$admin, route('loans.create')], [$admin, route('disposals.create')], [$admin, route('profile.edit')],
            [$sysadmin, route('masters.departments.create')], [$sysadmin, route('masters.employees.create')],
            [$sysadmin, route('admin.users.create')], [$sysadmin, route('admin.settings.edit')], [$sysadmin, route('admin.access.index')],
        ];
        foreach ($pages as [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            preg_match_all('/<form method="POST"[^>]*>/', $html, $forms);
            foreach ($forms[0] as $tag) {
                if (str_contains($tag, 'logout')) {
                    continue;
                }
                $this->assertStringContainsString('data-ajax-form', $tag, "{$url}: {$tag}");
            }
            $this->assertStringNotContainsString('return confirm(', $html, $url);
        }
    }

    public function test_master_karyawan_user_setting_via_ajax(): void
    {
        $sysadmin = $this->user('sysadmin');

        $this->ajax($sysadmin, 'POST', route('masters.departments.store'), ['code' => ''])->assertStatus(422)->assertJsonValidationErrors(['code', 'name']);
        $this->ok($this->ajax($sysadmin, 'POST', route('masters.departments.store'), ['code' => 'AJX', 'name' => 'Ajax Dept', 'is_active' => 1]), route('masters.departments.index'));
        $dept = Department::where('code', 'AJX')->firstOrFail();
        $this->ok($this->ajax($sysadmin, 'PUT', route('masters.departments.update', $dept), ['code' => 'AJX', 'name' => 'Ajax Dept 2', 'is_active' => 1]));
        $this->ok($this->ajax($sysadmin, 'DELETE', route('masters.departments.destroy', $dept)));

        // Hapus master yang dipakai → error bisnis
        $used = Department::whereHas('employees')->firstOrFail();
        $this->ajax($sysadmin, 'DELETE', route('masters.departments.destroy', $used))->assertStatus(422)->assertJsonPath('status', 'error');

        $this->ok($this->ajax($sysadmin, 'POST', route('masters.employees.store'), [
            'employee_no' => 'EMP-AJX', 'name' => 'Karyawan Ajax', 'department_id' => 2, 'employment_status' => 'ACTIVE',
        ]));
        $this->assertNotNull(Employee::where('employee_no', 'EMP-AJX')->first());

        $this->ok($this->ajax($sysadmin, 'POST', route('admin.users.store'), [
            'name' => 'Ajax User', 'email' => 'ajax.user@kantorku.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'roles' => [1, 5], 'is_active' => 1,
        ]), route('admin.users.index'));

        // Menonaktifkan akun sendiri → error
        $this->ajax($sysadmin, 'PUT', route('admin.users.update', $sysadmin), [
            'name' => $sysadmin->name, 'email' => $sysadmin->email, 'employee_id' => $sysadmin->employee_id, 'roles' => [4], 'is_active' => 0,
        ])->assertStatus(422)->assertJsonPath('status', 'error');

        $this->ok($this->ajax($sysadmin, 'PUT', route('admin.settings.update'), [
            'app_company_name' => 'PT Ajax', 'loan_max_duration_days' => 30, 'attachment_max_size_kb' => 5120,
        ]));

        $this->ajax($this->user('staff'), 'PUT', route('profile.password'), ['current_password' => 'salah', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_aset_dan_status_via_ajax(): void
    {
        $admin = $this->user('admin.aset');
        $res = $this->ok($this->ajax($admin, 'POST', route('assets.store'), [
            'name' => 'Kursi Ajax', 'asset_category_id' => 8, 'location_id' => 2, 'condition' => 'GOOD',
        ]));
        $asset = Asset::where('name', 'Kursi Ajax')->firstOrFail();
        $res->assertJsonPath('redirect', route('assets.show', $asset));

        $this->ok($this->ajax($admin, 'POST', route('assets.status', $asset), ['action' => 'repair']), null)->assertJsonPath('redirect', null);
        // Transisi tidak valid → aturan bisnis 422
        $this->ajax($admin, 'POST', route('assets.status', $asset), ['action' => 'found'])->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_alur_pengadaan_approval_via_ajax(): void
    {
        $staff = $this->user('staff');
        $res = $this->ok($this->ajax($staff, 'POST', route('procurements.store'), [
            'title' => 'Ajax PR', 'justification' => 'Uji ajax', 'department_id' => 2, 'action' => 'submit',
            'items' => [['asset_category_id' => 8, 'item_name' => 'Meja', 'quantity' => 1, 'estimated_unit_price' => 500000]],
            'attachments' => [UploadedFile::fake()->create('q.pdf', 10, 'application/pdf')],
        ]));
        $pr = ProcurementRequest::where('title', 'Ajax PR')->firstOrFail();
        $res->assertJsonPath('redirect', route('procurements.show', $pr));

        $step = $pr->approvalRequest->steps()->firstOrFail();
        $manager = $this->user('manager.it');
        $this->ajax($manager, 'POST', route('approvals.reject', $step), ['comment' => 'no'])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->ajax($this->user('direktur'), 'POST', route('approvals.approve', $step), ['comment' => 'ok'])->assertForbidden();
        $this->ok($this->ajax($manager, 'POST', route('approvals.approve', $step), ['comment' => 'OK']))->assertJsonPath('redirect', null);

        $admin = $this->user('admin.aset');
        $this->ok($this->ajax($admin, 'POST', route('procurements.order', $pr), ['vendor_id' => 1, 'po_number' => 'PO-AJX', 'ordered_at' => now()->subHour()->format('Y-m-d H:i')]));
        $item = $pr->items()->firstOrFail();
        $this->ok($this->ajax($admin, 'POST', route('procurements.receive', $pr), [
            'received_at' => now()->subMinute()->format('Y-m-d H:i'), 'items' => [$item->id => ['accepted' => 1, 'location_id' => 2, 'condition' => 'GOOD']],
        ]), route('procurements.show', $pr));
    }

    public function test_peminjaman_batal_setelah_disetujui_via_ajax(): void
    {
        $staff = $this->user('staff');
        $asset = Asset::where('asset_tag', 'PRJ-2026-00001')->firstOrFail();
        $this->ok($this->ajax($staff, 'POST', route('loans.store'), [
            'purpose' => 'Ajax', 'start_date' => today()->toDateString(), 'due_date' => today()->addDay()->toDateString(),
            'asset_ids' => [$asset->id], 'action' => 'submit',
        ]));
        $loan = AssetLoanRequest::latest('id')->firstOrFail();
        $this->approveAll($loan->approvalRequest);

        $this->ajax($staff, 'POST', route('loans.cancel', $loan), ['cancel_reason' => ''])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Alasan pembatalan'));
        $this->ok($this->ajax($staff, 'POST', route('loans.cancel', $loan), ['cancel_reason' => 'Tidak jadi dipakai']));
        $this->assertSame('CANCELLED', $loan->fresh()->status->value);

        $this->ok($this->ajax($staff, 'POST', route('notifications.read-all')));
    }
}
