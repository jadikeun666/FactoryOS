<?php

namespace App\Services\Scheduling;

use App\Exceptions\ScheduleApplyException;
use App\Exceptions\WorkOrderStatusException;
use App\Models\Schedule;
use App\Models\WoOperation;
use App\Services\WorkOrder\WorkOrderStatusService;
use Illuminate\Support\Facades\DB;

/**
 * ScheduleApplierService
 *
 * Menerapkan hasil satu Schedule (record immutable dari run algoritma) ke
 * wo_operations yang sesungguhnya dipakai lantai produksi, lalu men-transisi
 * status Work Order terkait dari 'draft' ke 'scheduled'.
 *
 * PENTING — batasan desain (lihat juga ringkasan di respons):
 * - Schedule & ScheduleAssignment TETAP immutable (docs/engineering-rules.md
 *   § 2). Service ini TIDAK PERNAH mengubah kedua tabel tersebut — hanya
 *   membaca assignments-nya lalu menulis ke wo_operations.
 * - wo_operations yang statusnya BUKAN 'pending' (sudah running/done/skipped)
 *   TIDAK ditimpa — operasi yang sudah berjalan di lantai produksi tidak
 *   boleh direncanakan ulang diam-diam. Operasi seperti ini dilewati dan
 *   dilaporkan lewat $skippedOperationIds pada hasil.
 * - Transisi status Work Order memakai App\Services\WorkOrder\
 *   WorkOrderStatusService::transition() yang sudah ada (validasi matrix
 *   draft→scheduled→in_progress→done/late), bukan update langsung, supaya
 *   aturan transisi tetap satu sumber kebenaran. WO yang sudah bukan
 *   'draft' (mis. sudah in_progress) dilewati juga, bukan dianggap error
 *   fatal — dikumpulkan sebagai $skippedWorkOrderIds.
 * - Tidak ada kolom "is_applied"/"applied_at" di tabel schedules (dan
 *   task ini secara eksplisit dilarang mengubah skema DB), jadi service
 *   ini TIDAK bisa menandai "schedule X adalah yang sedang aktif". Yang
 *   bisa diverifikasi hanyalah bahwa wo_operations sudah ter-update sesuai
 *   assignments dari schedule ini pada saat apply dijalankan.
 */
class ScheduleApplierService
{
    public function __construct(
        private readonly WorkOrderStatusService $workOrderStatus,
    ) {
    }

    /**
     * @return array{
     *     schedule_id: int,
     *     updated_operations: int,
     *     skipped_operation_ids: array<int>,
     *     transitioned_work_order_ids: array<int>,
     *     skipped_work_order_ids: array<int>,
     * }
     */
    public function apply(int $scheduleId): array
    {
        $schedule = Schedule::with('assignments.woOperation.workOrder')->find($scheduleId);

        if (! $schedule) {
            throw new ScheduleApplyException("Schedule #{$scheduleId} tidak ditemukan.");
        }

        if ($schedule->assignments->isEmpty()) {
            throw new ScheduleApplyException("Schedule #{$scheduleId} tidak memiliki assignments untuk diterapkan.");
        }

        return DB::transaction(function () use ($schedule) {
            $updatedCount = 0;
            $skippedOperationIds = [];
            $transitionedWorkOrderIds = [];
            $skippedWorkOrderIds = [];
            $seenWorkOrderIds = [];

            foreach ($schedule->assignments as $assignment) {
                /** @var WoOperation $operation */
                $operation = $assignment->woOperation;

                if ($operation->status !== 'pending') {
                    $skippedOperationIds[] = $operation->id;
                    continue;
                }

                $operation->forceFill([
                    'planned_start' => $assignment->start_at,
                    'planned_end' => $assignment->end_at,
                ])->save();

                $updatedCount++;

                $workOrder = $operation->workOrder;
                if (in_array($workOrder->id, $seenWorkOrderIds, true)) {
                    continue;
                }
                $seenWorkOrderIds[] = $workOrder->id;

                if ($workOrder->status === 'draft') {
                    try {
                        $this->workOrderStatus->transition($workOrder, 'scheduled');
                        $transitionedWorkOrderIds[] = $workOrder->id;
                    } catch (WorkOrderStatusException) {
                        // Transisi ditolak matrix ALLOWED_TRANSITIONS (seharusnya
                        // tidak terjadi karena sudah dicek status === 'draft' di
                        // atas, tapi dijaga defensif — jangan gagalkan seluruh
                        // apply hanya karena satu WO gagal transisi).
                        $skippedWorkOrderIds[] = $workOrder->id;
                    }
                } elseif ($workOrder->status !== 'scheduled') {
                    // WO sudah in_progress/done/late — jangan diregresi statusnya.
                    $skippedWorkOrderIds[] = $workOrder->id;
                }
            }

            if ($updatedCount === 0) {
                throw new ScheduleApplyException(
                    "Semua operasi pada Schedule #{$schedule->id} sudah berjalan/selesai — tidak ada yang bisa diterapkan."
                );
            }

            return [
                'schedule_id' => $schedule->id,
                'updated_operations' => $updatedCount,
                'skipped_operation_ids' => $skippedOperationIds,
                'transitioned_work_order_ids' => $transitionedWorkOrderIds,
                'skipped_work_order_ids' => $skippedWorkOrderIds,
            ];
        });
    }
}