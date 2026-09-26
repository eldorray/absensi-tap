<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\IzinOrangTua;
use App\Models\User;

class IzinOrangTuaPolicy
{
    public function view(User $user, IzinOrangTua $izinOrangTua): bool
    {
        return $user->role === Role::Admin
            || ($user->role === Role::OrangTua && $izinOrangTua->user_id === $user->id);
    }
}
