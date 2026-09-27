<?php

use App\Models\Pengumuman;
use App\Models\User;
use App\Support\IsiKaya;

test('guru tidak boleh membuka menu pengumuman', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pengumuman.index'))->assertForbidden();
});

test('admin melihat daftar pengumuman', function () {
    Pengumuman::factory()->create(['judul' => 'Rapat guru']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pengumuman.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/Pengumuman')
            ->has('pengumumans', 1)
            ->where('pengumumans.0.judul', 'Rapat guru'));
});

test('admin menambah pengumuman', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), ['judul' => 'Rapat guru', 'isi' => 'Sabtu pukul 09.00 di aula.', 'is_active' => true])
        ->assertRedirect(route('admin.pengumuman.index'));

    expect(Pengumuman::where('judul', 'Rapat guru')->value('is_active'))->toBeTruthy();
});

test('judul pengumuman wajib diisi', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), ['judul' => '', 'isi' => 'Isi', 'is_active' => true])
        ->assertSessionHasErrors('judul');

    expect(Pengumuman::count())->toBe(0);
});

test('admin menyembunyikan pengumuman', function () {
    $pengumuman = Pengumuman::factory()->create(['judul' => 'Rapat guru', 'isi' => 'Isi lama']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pengumuman.update', $pengumuman), ['judul' => 'Rapat guru', 'isi' => 'Isi baru', 'is_active' => false])
        ->assertRedirect(route('admin.pengumuman.index'));

    $pengumuman->refresh();

    expect($pengumuman->isi)->toBe('Isi baru')
        ->and($pengumuman->is_active)->toBeFalse();
});

test('admin menghapus pengumuman', function () {
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.pengumuman.destroy', $pengumuman))
        ->assertRedirect(route('admin.pengumuman.index'));

    expect(Pengumuman::count())->toBe(0);
});

test('guru hanya melihat pengumuman yang aktif di halaman absen', function () {
    Pengumuman::factory()->create(['judul' => 'Tampil']);
    Pengumuman::factory()->nonaktif()->create(['judul' => 'Disembunyikan']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Dashboard')
            ->has('pengumumans', 1)
            ->where('pengumumans.0.judul', 'Tampil'));
});

test('isi berformat disimpan tetapi skrip dan atribut berbahaya dibuang', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), [
            'judul' => 'Rapat',
            'isi' => '<h2>Agenda</h2><p><strong>Wajib</strong> <em>hadir</em> <a href="https://sekolah.sch.id" onclick="curi()">info</a></p>'
                .'<ul><li>Satu</li></ul><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">jahat</a>',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $isi = Pengumuman::query()->value('isi');

    expect($isi)->toContain('<h2>Agenda</h2>')
        ->toContain('<strong>Wajib</strong>')
        ->toContain('<em>hadir</em>')
        ->toContain('<ul><li>Satu</li></ul>')
        ->toContain('href="https://sekolah.sch.id"')
        ->toContain('rel="noopener noreferrer nofollow"')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('<img')
        ->not->toContain('javascript:');
});

test('isi yang hanya berisi tag kosong dari editor ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.pengumuman.store'), ['judul' => 'Rapat', 'isi' => '<p></p><p> </p>', 'is_active' => true])
        ->assertSessionHasErrors('isi');

    expect(Pengumuman::count())->toBe(0);
});

test('daftar admin memakai ringkasan teks polos dari isi berformat', function () {
    Pengumuman::factory()->create(['isi' => '<p><strong>Rapat</strong> guru</p><p>Sabtu &amp; Minggu</p>']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pengumuman.index'))
        ->assertInertia(fn ($page) => $page->where('pengumumans.0.ringkasan', 'Rapat guru Sabtu & Minggu'));
});

test('isi teks polos lama diubah jadi paragraf html yang aman', function () {
    expect(IsiKaya::dariTeksPolos("Baris satu\nbaris dua\n\n<b>Paragraf</b> kedua"))
        ->toBe("<p>Baris satu<br>\nbaris dua</p><p>&lt;b&gt;Paragraf&lt;/b&gt; kedua</p>");
});
