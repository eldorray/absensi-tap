<?php

test('halaman absen mengirim koordinat lokasi aktif untuk hitung jarak', function () {
    [$guru] = guruSiapAbsen();

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('lokasis', 1)
            ->has('lokasis.0.longitude')
            ->has('lokasis.0.radius_meter')
        );
});

test('lokasi nonaktif tidak dikirim ke halaman absen', function () {
    [$guru, , $lokasi] = guruSiapAbsen();
    $lokasi->update(['is_active' => false]);

    $this->actingAs($guru)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('lokasis', 0));
});

test('panel jarak memakai rumus dan batas yang sama dengan server', function () {
    $jarak = file_get_contents(resource_path('js/lib/jarak.ts'));
    $panel = file_get_contents(resource_path('js/components/JarakLokasi.svelte'));
    $dashboard = file_get_contents(resource_path('js/pages/Dashboard.svelte'));

    expect($jarak)->toContain('6_371_000')
        ->and($panel)
        ->toContain('AKURASI_MAKSIMAL_METER = 75')
        ->toContain('jarakMeter')
        ->and($dashboard)->toContain('<JarakLokasi {lokasis} />')
        // Jarak melebur ke dalam kartu status, bukan kartu sendiri.
        ->and($panel)->not->toContain('g-tile');
});
