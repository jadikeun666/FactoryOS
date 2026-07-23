<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

/**
 * Material adalah master data: semua user login boleh melihat, hanya
 * admin yang boleh mengubah/menghapus. Konvensi identik WorkCenterPolicy
 * (docs/architecture.md tidak mendefinisikan Policy khusus untuk Material,
 * jadi kita terapkan pola yang sama demi konsistensi lintas Master Data).
 */
class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Material $material): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Material $material): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->isAdmin();
    }
}