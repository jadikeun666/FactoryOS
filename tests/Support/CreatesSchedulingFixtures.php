<?php

namespace Tests\Support;

use App\Models\Product;
use App\Models\Routing;
use App\Models\WoOperation;
use App\Models\WorkCenter;
use App\Models\WorkOrder;
use Carbon\Carbon;

/**
 * Helper untuk membuat fixture data master (WorkCenter, Product, Routing) dan
 * transactional data (WorkOrder, WoOperation) langsung via Eloquent::create(),
 * TANPA bergantung pada definisi factory (supaya test tetap eksplisit dan
 * mudah dibaca sesuai skema di docs/database.md).
 */
trait CreatesSchedulingFixtures
{
    protected function makeWorkCenter(string $code = 'M01'): WorkCenter
    {
        return WorkCenter::create([
            'name'                       => "Mesin {$code}",
            'code'                       => $code,
            'capacity_per_shift_minutes' => 480,
            'setup_time_minutes'         => 0,
            'is_active'                  => true,
        ]);
    }

    protected function makeProduct(string $sku = 'PRD-TEST-01'): Product
    {
        return Product::create([
            'name' => "Produk {$sku}",
            'sku'  => $sku,
            'unit' => 'pcs',
        ]);
    }

    protected function makeRouting(Product $product, WorkCenter $workCenter, int $sequence, float $processMinutes, float $setupMinutes = 0): Routing
    {
        return Routing::create([
            'product_id'                => $product->id,
            'sequence'                  => $sequence,
            'work_center_id'            => $workCenter->id,
            'std_process_time_minutes'  => $processMinutes,
            'setup_time_minutes'        => $setupMinutes,
        ]);
    }

    protected function makeWorkOrder(Product $product, Carbon $dueDate, ?Carbon $releaseDate = null, ?Carbon $createdAt = null): WorkOrder
    {
        $workOrder = WorkOrder::create([
            'product_id'   => $product->id,
            'qty'          => 10,
            'due_date'     => $dueDate->toDateString(),
            'priority'     => 5,
            'release_date' => ($releaseDate ?? Carbon::now())->toDateString(),
            'status'       => 'draft',
        ]);

        // created_at dipakai FifoAlgorithm; override manual jika test butuh
        // urutan pembuatan yang presisi (default timestamp saat create() saja
        // sering tidak cukup berbeda antar WO dalam satu test).
        if ($createdAt !== null) {
            $workOrder->forceFill(['created_at' => $createdAt])->save();
        }

        return $workOrder->fresh();
    }

    protected function makeWoOperation(WorkOrder $workOrder, Routing $routing): WoOperation
    {
        return WoOperation::create([
            'work_order_id'  => $workOrder->id,
            'routing_id'     => $routing->id,
            'work_center_id' => $routing->work_center_id,
            'sequence'       => $routing->sequence,
            'status'         => 'pending',
        ]);
    }
}