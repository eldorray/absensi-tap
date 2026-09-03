<?php

// tests/Feature/Admin/IzinReviewTest.php

use App\Enums\StatusIzin;
use App\Models\Izin;
use App\Models\User;

test('guru tidak boleh membuka daftar izin admin', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.izin.index'))
        ->assertForbidden();
});

test('admin melihat semua izin', function () {
    Izin::factory()->for(User::factory())->count(3)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.izin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Izin')->has('izins', 3));
});

test('admin bisa menyetujui izin', function () {
    $admin = User::factory()->admin()->create();
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs($admin)
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui'])
        ->assertRedirect(route('admin.izin.index'));

    $izin->refresh();

    expect($izin->status)->toBe(StatusIzin::Disetujui)
        ->and($izin->reviewed_by)->toBe($admin->id)
        ->and($izin->reviewed_at)->not->toBeNull();
});

test('admin bisa menolak izin dengan catatan', function () {
    $admin = User::factory()->admin()->create();
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs($admin)->patch(route('admin.izin.update', $izin), [
        'status' => 'ditolak',
        'catatan_review' => 'Surat dokter tidak terlampir.',
    ]);

    $izin->refresh();

    expect($izin->status)->toBe(StatusIzin::Ditolak)
        ->and($izin->catatan_review)->toBe('Surat dokter tidak terlampir.');
});

test('status pending tidak bisa dikirim sebagai hasil review', function () {
    $izin = Izin::factory()->for(User::factory())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.izin.update', $izin), ['status' => 'pending'])
        ->assertSessionHasErrors('status');
});

test('guru tidak bisa menyetujui izinnya sendiri', function () {
    $guru = User::factory()->create();
    $izin = Izin::factory()->for($guru)->create();

    $this->actingAs($guru)
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui'])
        ->assertForbidden();

    expect($izin->refresh()->status)->toBe(StatusIzin::Pending);
});
