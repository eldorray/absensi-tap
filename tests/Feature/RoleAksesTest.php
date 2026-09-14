<?php

use App\Enums\Role;
use App\Models\User;

test('enum role mengenal tiga role', function () {
    expect(array_map(fn (Role $r): string => $r->value, Role::cases()))
        ->toBe(['guru', 'admin', 'orang_tua']);
});

test('halaman kelola role menampilkan orang tua beserta aksesnya', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.role.index'))
        ->assertInertia(fn ($p) => $p->has('roles', 3)
            ->where('roles.2.value', 'orang_tua')
            ->where('roles.2.label', 'Orang Tua')
            ->where('roles.2.jumlah', 0));
});
