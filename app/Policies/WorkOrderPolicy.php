<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

/**
 * WorkOrderPolicy — sesuai docs/architecture.md § Policies:
 * "update/delete hanya creator atau admin".
 *
 * Laravel 11 melakukan auto-discovery Policy berdasarkan naming convention
 * (WorkOrder model → WorkOrderPolicy), jadi TIDAK perlu didaftarkan manual
 * di AuthServiceProvider selama nama file & namespace ini konsisten.
 */
class WorkOrderPolicy
{
    /**
     * Semua user yang sudah login boleh melihat daftar & detail Work Order.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return true;
    }

    /**
     * Semua user yang sudah login boleh membuat Work Order.
     * (Otorisasi lebih ketat, jika diperlukan di masa depan, bisa dibatasi
     * berdasarkan role PPIC di sini.)
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Hanya creator WO atau admin yang boleh update.
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->id === $workOrder->created_by || $user->isAdmin();
    }

    /**
     * Hanya creator WO atau admin yang boleh menghapus.
     * Guard status (in_progress/done tidak boleh dihapus) TIDAK dicek di sini —
     * itu tanggung jawab WorkOrderStatusService::assertDeletable(), dipanggil
     * terpisah di WorkOrderController@destroy setelah authorize() ini lolos.
     */
    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->id === $workOrder->created_by || $user->isAdmin();
    }
}