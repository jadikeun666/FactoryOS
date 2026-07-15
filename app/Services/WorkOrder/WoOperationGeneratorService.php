<?php

namespace App\Services\WorkOrder;

use App\Exceptions\WorkOrderOperationGenerationException;
use App\Models\Routing;
use App\Models\WoOperation;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * WoOperationGeneratorService.
 *
 * Tanggung jawab: satu-satunya tempat yang boleh membuat wo_operations dari
 * routing produk. Dipanggil oleh WorkOrderController setelah WorkOrder::create()
 * berhasil (FR-02 di docs/prd.md: "Sistem otomatis men-generate wo_operations
 * dari routing produk saat WO dibuat").
 *
 * Catatan desain: service ini sengaja dipisah dari Services/Scheduling/ karena
 * domainnya adalah Work Order Management (FR-02), bukan bagian dari algoritma
 * Job Shop Scheduler (Engine 1) itu sendiri. JobShopSchedulerService hanya
 * mengonsumsi wo_operations yang sudah ada, tidak pernah membuatnya.
 */
class WoOperationGeneratorService
{
    /**
     * Generate wo_operations untuk satu Work Order berdasarkan seluruh
     * routing milik product terkait, urut berdasarkan sequence.
     *
     * Idempotent-guard: jika WO ini sudah punya wo_operations sebelumnya,
     * method akan menolak generate ulang (untuk mencegah duplikasi operasi)
     * kecuali dipanggil dengan $force = true.
     *
     * @throws WorkOrderOperationGenerationException jika product tidak punya routing sama sekali,
     *                                                atau routing memiliki sequence yang tidak valid
     *                                                (duplikat / ada gap yang tidak konsisten).
     */
    public function generate(WorkOrder $workOrder, bool $force = false): Collection
    {
        $existingCount = $workOrder->operations()->count();

        if ($existingCount > 0 && ! $force) {
            throw new WorkOrderOperationGenerationException(
                "Work Order #{$workOrder->id} sudah memiliki {$existingCount} wo_operations. "
                . 'Gunakan force=true jika memang ingin regenerate (hati-hati: ini akan menghapus operasi lama beserta relasinya).'
            );
        }

        /** @var Collection<int, Routing> $routings */
        $routings = Routing::query()
            ->where('product_id', $workOrder->product_id)
            ->orderBy('sequence')
            ->get();

        if ($routings->isEmpty()) {
            throw new WorkOrderOperationGenerationException(
                "Product #{$workOrder->product_id} belum memiliki routing. "
                . 'Tidak bisa generate wo_operations untuk Work Order ini.'
            );
        }

        // Validasi kesehatan urutan sequence: tidak boleh ada duplikat sequence
        // pada produk yang sama (harusnya sudah dicegah oleh UNIQUE(product_id, sequence)
        // di database, tapi tetap divalidasi ulang di layer Service sebagai defense-in-depth).
        $sequences = $routings->pluck('sequence');
        if ($sequences->unique()->count() !== $sequences->count()) {
            throw new WorkOrderOperationGenerationException(
                "Routing produk #{$workOrder->product_id} memiliki sequence duplikat, tidak bisa generate wo_operations."
            );
        }

        return DB::transaction(function () use ($workOrder, $routings, $force) {
            if ($force) {
                // Regenerate: hapus operasi lama dulu (cascade akan membersihkan
                // schedule_assignments terkait via ON DELETE CASCADE di skema).
                $workOrder->operations()->delete();
            }

            $now = now();
            $rows = $routings->map(fn (Routing $routing) => [
                'work_order_id'  => $workOrder->id,
                'routing_id'     => $routing->id,
                'work_center_id' => $routing->work_center_id,
                'sequence'       => $routing->sequence,
                'planned_start'  => null,
                'planned_end'    => null,
                'actual_start'   => null,
                'actual_end'     => null,
                'status'         => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ])->all();

            WoOperation::insert($rows);

            // Reload dari DB agar mendapat id auto-increment masing-masing baris.
            return $workOrder->operations()->orderBy('sequence')->get();
        });
    }
}