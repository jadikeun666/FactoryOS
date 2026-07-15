<?php

namespace App\Services\WorkOrder;

use App\Exceptions\WorkOrderStatusException;
use App\Models\WorkOrder;

/**
 * WorkOrderStatusService.
 *
 * Mengelola validasi transisi status Work Order dan guard penghapusan,
 * supaya logic percabangan status tidak bocor ke Controller (thin controller
 * principle, docs/architecture.md).
 *
 * Alur status (docs/database.md § work_orders):
 *   draft → scheduled → in_progress → done
 *                     ↘ in_progress → late
 * `late` bisa dicapai dari `scheduled` atau `in_progress` jika WO terlambat.
 */
class WorkOrderStatusService
{
    /**
     * Matrix transisi status yang diperbolehkan.
     * Key = status asal, Value = daftar status tujuan yang valid.
     */
    private const ALLOWED_TRANSITIONS = [
        'draft'       => ['scheduled'],
        'scheduled'   => ['in_progress', 'late', 'draft'],
        'in_progress' => ['done', 'late'],
        'late'        => ['in_progress', 'done'],
        'done'        => [],
    ];

    /**
     * Status yang tidak boleh dihapus sama sekali (FR-02: "Sistem mencegah
     * penghapusan WO yang sudah berstatus in_progress atau done").
     */
    private const NON_DELETABLE_STATUSES = ['in_progress', 'done'];

    /**
     * Ubah status WO ke status baru, dengan validasi transisi.
     *
     * @throws WorkOrderStatusException jika transisi tidak diperbolehkan.
     */
    public function transition(WorkOrder $workOrder, string $newStatus): WorkOrder
    {
        $current = $workOrder->status;

        $allowedTargets = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (! in_array($newStatus, $allowedTargets, true)) {
            throw new WorkOrderStatusException(
                "Transisi status dari '{$current}' ke '{$newStatus}' tidak diperbolehkan untuk Work Order #{$workOrder->id}."
            );
        }

        $workOrder->update(['status' => $newStatus]);

        return $workOrder->fresh();
    }

    /**
     * Pastikan WO boleh dihapus, lempar exception jika tidak.
     *
     * @throws WorkOrderStatusException jika WO berstatus in_progress atau done.
     */
    public function assertDeletable(WorkOrder $workOrder): void
    {
        if (in_array($workOrder->status, self::NON_DELETABLE_STATUSES, true)) {
            throw new WorkOrderStatusException(
                "Work Order #{$workOrder->id} berstatus '{$workOrder->status}' dan tidak bisa dihapus."
            );
        }
    }
}