<?php

namespace App\Policies;

use App\Models\Izin;
use App\Models\User;

class IzinPolicy
{
    /**
     * Guru hanya boleh melihat izinnya sendiri; admin boleh semua.
     */
    public function view(User $user, Izin $izin): bool
    {
        return $izin->user_id === $user->id || $user->can('admin');
    }
}
