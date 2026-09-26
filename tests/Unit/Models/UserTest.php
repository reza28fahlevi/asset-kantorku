<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class UserTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_has_permission_berdasarkan_role(): void
    {
        $staff = $this->user('staff');

        $this->assertTrue($staff->hasPermission('procurement.create'));
        $this->assertTrue($staff->hasPermission('asset.view'));
        $this->assertFalse($staff->hasPermission('approval.decide'));
        $this->assertFalse($staff->hasPermission('procurement.view_all'));
        $this->assertFalse($staff->hasPermission('tidak.ada'));
    }

    public function test_permission_gabungan_multi_role_tanpa_duplikat(): void
    {
        $admin = $this->user('admin.aset'); // staff + asset_admin
        $names = $admin->permissionNames();

        $this->assertTrue($admin->hasPermission('procurement.order'), 'dari asset_admin');
        $this->assertTrue($admin->hasPermission('loan.extend'), 'dari keduanya');
        $this->assertSame($names->count(), $names->unique()->count());
        $this->assertFalse($admin->hasPermission('approval.decide'), 'admin aset bukan approver');
    }

    public function test_has_any_permission(): void
    {
        $auditor = $this->user('auditor');

        $this->assertTrue($auditor->hasAnyPermission(['procurement.create', 'audit.view']));
        $this->assertFalse($auditor->hasAnyPermission(['procurement.create', 'loan.create']));
        $this->assertFalse($auditor->hasAnyPermission([]));
    }

    public function test_user_tanpa_role_tidak_punya_permission(): void
    {
        $user = $this->makeUser(null);

        $this->assertTrue($user->permissionNames()->isEmpty());
        $this->assertFalse($user->hasPermission('asset.view'));
        $this->assertFalse($user->hasRole(Role::STAFF));
    }

    public function test_has_role_menerima_beberapa_nama(): void
    {
        $admin = $this->user('admin.aset');

        $this->assertTrue($admin->hasRole(Role::ASSET_ADMIN));
        $this->assertTrue($admin->hasRole(Role::SYS_ADMIN, Role::STAFF));
        $this->assertFalse($admin->hasRole(Role::MANAGER, Role::AUDITOR));
        $this->assertFalse($admin->hasRole());
    }

    public function test_is_approver_membutuhkan_akun_aktif_karyawan_dan_permission(): void
    {
        $this->assertTrue($this->user('manager.it')->isApprover());
        $this->assertTrue($this->user('direktur')->isApprover());
        $this->assertFalse($this->user('staff')->isApprover(), 'tanpa approval.decide');
        $this->assertFalse($this->user('sysadmin')->isApprover(), 'sysadmin tidak otomatis approver');

        $inactive = $this->makeUser($this->makeEmployee()->id, [Role::MANAGER], active: false);
        $this->assertFalse($inactive->isApprover(), 'akun nonaktif');

        $orphan = $this->makeUser(null, [Role::MANAGER]);
        $this->assertFalse($orphan->isApprover(), 'tanpa karyawan');
    }

    public function test_cache_permission_bertahan_sampai_di_flush(): void
    {
        $user = $this->makeUser($this->makeEmployee()->id, [Role::STAFF]);
        $this->assertFalse($user->hasPermission('audit.view'));
        $this->assertFalse($user->hasRole(Role::AUDITOR));

        $user->roles()->attach(Role::where('name', Role::AUDITOR)->value('id'));

        // Masih memakai cache dalam request yang sama
        $this->assertFalse($user->hasPermission('audit.view'));
        $this->assertFalse($user->hasRole(Role::AUDITOR));

        $user->flushPermissionCache();

        $this->assertTrue($user->hasPermission('audit.view'));
        $this->assertTrue($user->hasRole(Role::AUDITOR));
        $this->assertTrue($user->hasPermission('procurement.create'), 'permission role lama tetap ada');
    }

    public function test_password_di_hash_dan_disembunyikan_dari_serialisasi(): void
    {
        $user = $this->makeUser(null);

        $this->assertNotSame('password', $user->getAttributes()['password']);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }
}
