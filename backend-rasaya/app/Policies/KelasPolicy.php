<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\User;
use App\Models\Kelas;

class KelasPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function view(User $user, Kelas $kelas): bool
    {
        if ($user->role === 'admin')
            return true;
        if ($user->role === 'guru' && $kelas->wali_guru_id === $user->id)
            return true;
        return false;
    }
    public function create(User $u): bool
    {
        return $u->role === 'admin';
    }
    public function update(User $u, Kelas $k): bool
    {
        return $u->role === 'admin';
    }
    public function delete(User $u, Kelas $k): bool
    {
        return $u->role === 'admin';
    }
}
