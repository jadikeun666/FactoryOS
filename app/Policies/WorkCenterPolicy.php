<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkCenter;

/**
 * @see docs/architecture.md § Policies
 *
 * WorkCenter adalah master data: semua user login boleh melihat (dipakai
 * juga untuk otorisasi channel broadcast work-center.{id} di routes/channels.php),
 * tapi hanya admin yang boleh mengubah/menghapus.
 */
class WorkCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkCenter $workCenter): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WorkCenter $workCenter): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WorkCenter $workCenter): bool
    {
        return $user->isAdmin();
    }
}