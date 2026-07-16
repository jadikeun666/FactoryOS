<?php

namespace App\Policies;

use App\Models\ProductionLog;
use App\Models\User;

/**
 * ProductionLogPolicy — sesuai docs/engineering-rules.md § 2:
 * "update hanya jika belum validated + creator atau admin".
 *
 * Laravel 11 auto-discovery: ProductionLog model -> ProductionLogPolicy,
 * tidak perlu register manual selama nama file & namespace konsisten.
 */
class ProductionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionLog $productionLog): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Log yang sudah divalidasi tidak bisa diedit (immutability, FR-04).
     * Selain itu, hanya creator atau admin.
     */
    public function update(User $user, ProductionLog $productionLog): bool
    {
        if ($productionLog->is_validated) {
            return false;
        }

        return $user->id === $productionLog->created_by || $user->isAdmin();
    }

    /**
     * Guard delete sama seperti update: tidak boleh setelah validated.
     */
    public function delete(User $user, ProductionLog $productionLog): bool
    {
        if ($productionLog->is_validated) {
            return false;
        }

        return $user->id === $productionLog->created_by || $user->isAdmin();
    }

    /**
     * Aksi "validate": menandai is_validated = true. Sengaja dipisah dari
     * update() — supervisor/admin yang mem-validasi, bukan operator/creator
     * sendiri (pemisahan tugas). Log yang sudah validated tidak bisa
     * di-validate ulang.
     */
    public function validateLog(User $user, ProductionLog $productionLog): bool
    {
        if ($productionLog->is_validated) {
            return false;
        }

        return $user->id === $productionLog->created_by
            || $user->isAdmin()
            || $user->isProductionManager();
    }
}