<?php

namespace Tests\Unit\Models;

use App\Models\AssetAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class AssetAssignmentTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    public function test_is_active_selama_belum_dikembalikan(): void
    {
        $this->assertTrue((new AssetAssignment)->isActive());
        $this->assertFalse(new AssetAssignment(['returned_at' => now()])->isActive());
    }

    public function test_scope_active_di_database(): void
    {
        $active = $this->makeAssignment(5);
        $returned = $this->makeAssignment(5, returned: true);

        $ids = AssetAssignment::query()->whereIn('id', [$active->id, $returned->id])->active()->pluck('id')->all();
        $this->assertSame([$active->id], $ids);
    }

    public function test_aset_yang_sedang_dipinjam_tidak_bisa_ditugaskan(): void
    {
        $loan = $this->makeLoan(7);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeAssignment(5, asset: $loan->asset);
    }

    public function test_satu_aset_hanya_satu_assignment_aktif_tetapi_histori_boleh_banyak(): void
    {
        $first = $this->makeAssignment(5, returned: true);
        $second = $this->makeAssignment(7, asset: $first->asset);

        $this->assertSame(2, $first->asset->assignments()->count());
        $this->assertSame($second->id, $first->asset->fresh()->activeAssignment->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeAssignment(5, asset: $first->asset);
    }
}
