<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

/** Trait Auditable mencatat created/updated/deleted ke audit_logs. */
class AuditableTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private function logsFor(string $type, int $id)
    {
        return AuditLog::where('auditable_type', $type)->where('auditable_id', $id)->orderBy('id')->get();
    }

    public function test_create_dicatat_dengan_aktor_dan_nilai_enum_sebagai_string(): void
    {
        $admin = $this->user('admin.aset');
        $this->actingAs($admin);

        $asset = $this->makeAsset(['name' => 'Aset Audit']);
        $log = $this->logsFor('asset', $asset->id)->first();

        $this->assertSame('created', $log->action);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Aset Audit', $log->new_values['name']);
        $this->assertSame('AVAILABLE', $log->new_values['status']);
        $this->assertArrayNotHasKey('created_at', $log->new_values);
    }

    public function test_update_hanya_mencatat_kolom_yang_berubah(): void
    {
        $asset = $this->makeAsset(['name' => 'Lama']);
        $asset->update(['name' => 'Baru']);

        $log = $this->logsFor('asset', $asset->id)->last();
        $this->assertSame('updated', $log->action);
        $this->assertSame(['name' => 'Lama'], $log->old_values);
        $this->assertSame(['name' => 'Baru'], $log->new_values);
    }

    public function test_update_tanpa_perubahan_berarti_tidak_dicatat(): void
    {
        $asset = $this->makeAsset();
        $before = $this->logsFor('asset', $asset->id)->count();

        $asset->touch(); // hanya updated_at
        $this->assertSame($before, $this->logsFor('asset', $asset->id)->count());
    }

    public function test_password_dan_token_tidak_pernah_masuk_audit_log(): void
    {
        $user = $this->makeUser(null);
        $user->update(['password' => 'rahasia-baru']);
        $user->forceFill(['remember_token' => 'abc'])->save();

        foreach ($this->logsFor('user', $user->id) as $log) {
            $this->assertArrayNotHasKey('password', $log->new_values ?? []);
            $this->assertArrayNotHasKey('password', $log->old_values ?? []);
            $this->assertArrayNotHasKey('remember_token', $log->new_values ?? []);
        }
    }

    public function test_delete_dicatat(): void
    {
        $location = \App\Models\Location::create(['code' => 'UT3A', 'name' => 'Audit']);
        $location->forceDelete();

        $log = $this->logsFor($location->getMorphClass(), $location->id)->last();
        $this->assertSame('deleted', $log->action);
        $this->assertSame('Audit', $log->old_values['name']);
    }
}
