<?php

namespace Tests\Unit\Policies;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetAssignmentPolicyTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_pengembalian_assignment_aktif_oleh_admin(): void
    {
        $assignment = $this->makeAssignment(5);
        $this->assertTrue(Gate::forUser($this->user('admin.aset'))->allows('return', $assignment));
    }

    public function test_pengembalian_ditolak_untuk_pemegang_dan_role_tanpa_permission(): void
    {
        $assignment = $this->makeAssignment(5);
        foreach (['staff', 'manager.it', 'direktur', 'auditor', 'sysadmin'] as $name) {
            $this->assertFalse(Gate::forUser($this->user($name))->allows('return', $assignment), $name);
        }
    }

    public function test_assignment_yang_sudah_dikembalikan_tidak_bisa_dikembalikan_lagi(): void
    {
        $assignment = $this->makeAssignment(5, returned: true);
        $this->assertFalse(Gate::forUser($this->user('admin.aset'))->allows('return', $assignment));
    }
}
