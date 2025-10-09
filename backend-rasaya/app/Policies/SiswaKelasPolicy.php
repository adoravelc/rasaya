<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Kelas;

class SiswaKelasPolicy
{
    /**
     * Create a new policy instance.
     */
    public function viewRoster(User $u, Kelas $k): bool
    {
        if ($u->role === 'admin') return true;
        if ($u->role === 'guru' && $k->wali_guru_id === $u->id) return true;
        return false;
    }
    public function modify(User $u, Kelas $k): bool
    {
        return $u->role === 'admin' || ($u->role === 'guru' && $k->wali_guru_id === $u->id);
    }
}
