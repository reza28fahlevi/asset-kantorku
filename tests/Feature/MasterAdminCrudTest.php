<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Concerns\ActsAsDemoUsers;
use Tests\TestCase;

/** CRUD master data & administrasi (user multi role, role, matriks otorisasi, setting, profil). */
class MasterAdminCrudTest extends TestCase
{
    use ActsAsDemoUsers, DatabaseTransactions;

    public function test_master_data_crud(): void
    {
        $admin = $this->user('sysadmin');
        $cases = [
            'departments' => [Department::class, ['code' => 'QA-01', 'name' => 'Quality Assurance', 'is_active' => 1]],
            'locations' => [Location::class, ['code' => 'LT-9', 'name' => 'Lantai 9', 'address' => 'Gedung A', 'is_active' => 1]],
            'categories' => [AssetCategory::class, ['code' => 'TST', 'name' => 'Kategori Test', 'useful_life_months' => 36, 'is_active' => 1]],
            'vendors' => [Vendor::class, ['code' => 'VND-T', 'name' => 'Vendor Test', 'email' => 'v@test.id', 'is_active' => 1]],
        ];

        foreach ($cases as $key => [$model, $payload]) {
            $this->assertSucceeded($this->actingAs($admin)->post(route("masters.{$key}.store"), $payload));
            $record = $model::where('code', $payload['code'])->firstOrFail();

            $this->assertPageOk($admin, route("masters.{$key}.index", ['q' => $payload['name']]));
            $this->assertPageOk($admin, route("masters.{$key}.edit", $record));

            $this->assertSucceeded($this->actingAs($admin)->put(route("masters.{$key}.update", $record), [...$payload, 'name' => $payload['name'].' Ubah']));
            $this->assertSame($payload['name'].' Ubah', $record->fresh()->name, $key);

            $this->assertSucceeded($this->actingAs($admin)->delete(route("masters.{$key}.destroy", $record)));
            $this->assertNull($model::find($record->id), "{$key} belum terhapus");
        }
    }

    public function test_employee_crud(): void
    {
        $admin = $this->user('sysadmin');
        $payload = [
            'employee_no' => 'EMP-T01', 'name' => 'Karyawan Test', 'email' => 'karyawan.test@kantorku.test',
            'department_id' => Department::first()->id, 'manager_employee_id' => 1, 'job_title' => 'Analis',
            'employment_status' => 'ACTIVE', 'start_date' => '2026-01-01',
        ];

        $this->assertSucceeded($this->actingAs($admin)->post(route('masters.employees.store'), $payload));
        $employee = Employee::where('employee_no', 'EMP-T01')->firstOrFail();
        $this->assertPageOk($admin, route('masters.employees.show', $employee));
        $this->assertPageOk($admin, route('masters.employees.edit', $employee));

        $this->assertSucceeded($this->actingAs($admin)->put(route('masters.employees.update', $employee), [...$payload, 'job_title' => 'Senior Analis']));
        $this->assertSame('Senior Analis', $employee->fresh()->job_title);
    }

    public function test_user_multi_role_crud(): void
    {
        $admin = $this->user('sysadmin');
        $roles = Role::whereIn('name', ['staff', 'auditor'])->pluck('id')->all();

        $this->assertSucceeded($this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'User Test', 'email' => 'user.test@kantorku.test', 'employee_id' => 7,
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123', 'roles' => $roles, 'is_active' => 1,
        ]));
        $user = User::where('email', 'user.test@kantorku.test')->firstOrFail();
        $this->assertEqualsCanonicalizing($roles, $user->roles()->pluck('roles.id')->all());

        // Form edit menampilkan role yang sudah dimiliki
        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()->assertSee('rolePicker(', false);

        $newRoles = Role::whereIn('name', ['staff', 'manager', 'auditor'])->pluck('id')->all();
        $this->assertSucceeded($this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'User Test Ubah', 'email' => 'user.test@kantorku.test', 'employee_id' => 7,
            'password' => '', 'password_confirmation' => '', 'roles' => $newRoles, 'is_active' => 1,
        ]));
        $user->refresh();
        $this->assertSame('User Test Ubah', $user->name);
        $this->assertEqualsCanonicalizing($newRoles, $user->roles()->pluck('roles.id')->all());
        $this->assertPageOk($admin, route('admin.users.index', ['role' => 'manager']));
    }

    public function test_role_crud_and_access_matrix(): void
    {
        $admin = $this->user('sysadmin');
        $perms = Permission::whereIn('name', ['asset.view', 'report.view'])->pluck('id')->all();

        $this->assertSucceeded($this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'viewer_test', 'display_name' => 'Viewer Test', 'description' => 'Uji', 'permissions' => $perms,
        ]));
        $role = Role::where('name', 'viewer_test')->firstOrFail();
        $this->assertEqualsCanonicalizing($perms, $role->permissions()->pluck('permissions.id')->all());
        $this->assertPageOk($admin, route('admin.roles.edit', $role));

        $this->assertSucceeded($this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => 'viewer_test', 'display_name' => 'Viewer Test 2', 'permissions' => [$perms[0]],
        ]));
        $this->assertSame('Viewer Test 2', $role->fresh()->display_name);
        $this->assertSame(1, $role->permissions()->count());

        // Matriks: simpan ulang kondisi saat ini + tambah permission ke role baru
        $matrix = Role::with('permissions')->get()->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('id')->all()])->all();
        $matrix[$role->id] = $perms;
        $this->assertSucceeded($this->actingAs($admin)->put(route('admin.access.update'), ['matrix' => $matrix]));
        $this->assertSame(2, $role->permissions()->count());

        // Proteksi lockout: tidak boleh mencabut role.manage dari semua role milik sendiri
        $manage = Permission::where('name', 'role.manage')->value('id');
        $locked = collect($matrix)->map(fn ($ids) => array_values(array_diff($ids, [$manage])))->all();
        $this->actingAs($admin)->put(route('admin.access.update'), ['matrix' => $locked])->assertSessionHas('error');

        $this->assertSucceeded($this->actingAs($admin)->delete(route('admin.roles.destroy', $role)));
        $this->assertNull(Role::find($role->id));
    }

    public function test_role_crud_via_ajax_json_untuk_sweetalert(): void
    {
        $admin = $this->user('sysadmin');
        $perm = Permission::where('name', 'asset.view')->value('id');

        // Validasi gagal → 422 + errors per field
        $this->actingAs($admin)->postJson(route('admin.roles.store'), ['name' => 'Bukan Slug!', 'display_name' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'display_name']);

        // Simpan → JSON sukses + redirect
        $this->actingAs($admin)->postJson(route('admin.roles.store'), [
            'name' => 'ajax_role', 'display_name' => 'Ajax Role', 'permissions' => [$perm],
        ])->assertOk()->assertJson(['status' => 'success', 'message' => 'Role Ajax Role berhasil dibuat.', 'redirect' => route('admin.roles.index')]);
        $role = Role::where('name', 'ajax_role')->firstOrFail();

        $this->actingAs($admin)->putJson(route('admin.roles.update', $role), [
            'name' => 'ajax_role', 'display_name' => 'Ajax Role 2', 'permissions' => [],
        ])->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('message', 'Role Ajax Role 2 berhasil diperbarui.');
        $this->assertSame(0, $role->permissions()->count());

        // Hapus role bawaan ditolak → 422 status error
        $system = Role::where('name', 'staff')->firstOrFail();
        $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $system))
            ->assertStatus(422)->assertJson(['status' => 'error', 'message' => 'Role bawaan sistem tidak dapat dihapus.']);

        $this->actingAs($admin)->deleteJson(route('admin.roles.destroy', $role))->assertOk()->assertJsonPath('status', 'success');
        $this->assertNull(Role::find($role->id));

        // Tanpa hak akses → 403
        $this->actingAs($this->user('staff'))->postJson(route('admin.roles.store'), ['name' => 'x', 'display_name' => 'x'])->assertForbidden();

        // Halaman memuat form AJAX & konfirmasi hapus
        $this->actingAs($admin)->get(route('admin.roles.create'))->assertOk()->assertSee('data-ajax-form', false);
    }

    public function test_settings_and_profile_password(): void
    {
        $admin = $this->user('sysadmin');
        $this->assertSucceeded($this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_company_name' => 'PT Uji Coba', 'loan_max_duration_days' => 30, 'attachment_max_size_kb' => 5120,
        ]));
        $this->assertPageOk($admin, route('admin.settings.edit'));

        $staff = $this->user('staff');
        $this->assertSucceeded($this->actingAs($staff)->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'BaruSekali123', 'password_confirmation' => 'BaruSekali123',
        ]));
    }

    public function test_simple_forms_open_in_modal(): void
    {
        $admin = $this->user('sysadmin');

        // Halaman daftar: tombol tambah/ubah membuka modal; halaman form menandai isi modal
        foreach (['masters.departments', 'masters.locations', 'masters.categories', 'masters.vendors', 'masters.employees', 'admin.users', 'admin.roles'] as $name) {
            $this->actingAs($admin)->get(route("{$name}.index"))->assertOk()->assertSee('data-modal', false);
            $this->actingAs($admin)->get(route("{$name}.create"))->assertOk()->assertSee('data-modal-content', false);
        }

        // Profil & pengaturan: form di <template> yang dibuka sebagai modal
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertSee('data-modal="#password-modal"', false);
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('data-modal="#settings-modal"', false);
    }
}
