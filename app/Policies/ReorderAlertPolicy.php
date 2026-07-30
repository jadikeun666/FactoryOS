<?php

namespace App\Policies;

use App\Models\ReorderAlert;
use App\Models\User;

/**
 * @see docs/architecture.md § Policies
 *
 * Keputusan otorisasi (didiskusikan & dikonfirmasi eksplisit dengan owner
 * project, sesi Soketi item kecil #1): hanya admin dan PPIC yang boleh
 * mengubah status reorder alert (acknowledge/order). PPIC adalah pemilik
 * proses ini sesuai docs/prd.md US-12; production_manager dan operator
 * read-only untuk alert.
 */
class ReorderAlertPolicy
{
    public function updateStatus(User $user, ReorderAlert $reorderAlert): bool
    {
        return $user->isAdmin() || $user->isPpic();
    }
}
