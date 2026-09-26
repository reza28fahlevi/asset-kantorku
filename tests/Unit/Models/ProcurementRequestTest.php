<?php

namespace Tests\Unit\Models;

use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Unit\Concerns\CreatesDomainFixtures;

class ProcurementRequestTest extends TestCase
{
    use CreatesDomainFixtures, DatabaseTransactions;

    private function item(ProcurementRequest $request, int $qty, int $received = 0, float $price = 0): ProcurementRequestItem
    {
        return ProcurementRequestItem::create([
            'procurement_request_id' => $request->id, 'asset_category_id' => 8, 'item_name' => 'Item',
            'quantity' => $qty, 'quantity_received' => $received, 'estimated_unit_price' => $price,
        ]);
    }

    public function test_sisa_kuantitas_dan_subtotal_item(): void
    {
        $request = $this->makeProcurement(5);

        $item = $this->item($request, 3, 1, 1250000.50);
        $this->assertSame(2, $item->remainingQuantity());
        $this->assertEqualsWithDelta(3750001.5, $item->subtotal(), 0.001);

        $this->assertSame(0, new ProcurementRequestItem(['quantity' => 2, 'quantity_received' => 2])->remainingQuantity());
        $this->assertSame(0, new ProcurementRequestItem(['quantity' => 2, 'quantity_received' => 5])->remainingQuantity(), 'tidak negatif');
    }

    public function test_is_fully_received(): void
    {
        $request = $this->makeProcurement(5);
        $this->item($request, 2, 2);
        $partial = $this->item($request, 3, 1);

        $this->assertFalse($request->fresh()->isFullyReceived());

        $partial->update(['quantity_received' => 3]);
        $this->assertTrue($request->fresh()->isFullyReceived());
    }

    public function test_items_diurutkan_menurut_id(): void
    {
        $request = $this->makeProcurement(5);
        $a = $this->item($request, 1);
        $b = $this->item($request, 1);

        $this->assertSame([$a->id, $b->id], $request->fresh()->items->pluck('id')->all());
    }
}
