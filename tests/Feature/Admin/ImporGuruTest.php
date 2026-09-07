<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

/**
 * Berkas CSV sementara dengan isi apa adanya.
 */
function berkasCsv(string $isi, string $nama = 'guru.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'impor').'.csv';
    file_put_contents($path, $isi);

    return new UploadedFile($path, $nama, 'text/csv', null, true);
}

test('admin mengunduh template impor', function () {
    $respons = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.guru.template'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($respons->streamedContent())->toContain('nama,nip,email,password')
        // Baris contoh pertama sengaja kosong di email dan password.
        ->toContain('"Siti Aminah",198501012010012001,,');
});

test('guru tidak bisa mengunduh template impor', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.guru.template'))->assertForbidden();
});

test('impor membuat akun guru aktif dan terverifikasi', function () {
    $csv = "nama,nip,email,password\nSiti Aminah,1985,siti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)])
        ->assertRedirect(route('admin.guru.index'));

    $guru = User::where('email', 'siti@sekolah.sch.id')->firstOrFail();

    expect($guru->name)->toBe('Siti Aminah')
        ->and($guru->nip)->toBe('1985')
        ->and($guru->role)->toBe(Role::Guru)
        ->and($guru->is_active)->toBeTrue()
        ->and($guru->email_verified_at)->not->toBeNull()
        ->and($guru->password)->not->toBe('RahasiaKuat123');
});

test('baris salah dilewati tetapi baris benar tetap dibuat', function () {
    $csv = "nama,nip,email,password\n"
        ."Siti,,siti@sekolah.sch.id,RahasiaKuat123\n"
        .",,tanpa-nama@sekolah.sch.id,RahasiaKuat123\n"
        ."Budi,,bukan-email,RahasiaKuat123\n"
        ."Ani,,ani@sekolah.sch.id,pendek\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(User::where('role', Role::Guru)->count())->toBe(1)
        ->and(session('impor_guru')['dibuat'])->toBe(1)
        ->and(session('impor_guru')['dilewati'])->toBe(3)
        ->and(session('impor_guru')['galat'])->toHaveCount(3);
});

test('email kembar di dalam berkas hanya dibuat sekali', function () {
    $csv = "nama,nip,email,password\n"
        ."Siti,,siti@sekolah.sch.id,RahasiaKuat123\n"
        ."Siti Lagi,,siti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(User::where('email', 'siti@sekolah.sch.id')->count())->toBe(1)
        ->and(session('impor_guru')['dilewati'])->toBe(1);
});

test('email yang sudah terdaftar dilewati', function () {
    User::factory()->create(['email' => 'siti@sekolah.sch.id']);
    $csv = "nama,nip,email,password\nSiti,,siti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(session('impor_guru')['dibuat'])->toBe(0)
        ->and(session('impor_guru')['dilewati'])->toBe(1);
});

test('csv bertitik koma dari Excel Indonesia ikut terbaca', function () {
    $csv = "nama;nip;email;password\nSiti;1985;siti@sekolah.sch.id;RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(User::where('email', 'siti@sekolah.sch.id')->exists())->toBeTrue();
});

test('berkas tanpa kolom nama ditolak tanpa membuat akun', function () {
    $csv = "email,password\nsiti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(User::where('role', Role::Guru)->count())->toBe(0)
        ->and(session('impor_guru')['galat'][0])->toContain('nama');
});

test('berkas berisi nama dan nip saja tetap membuat akun dengan email dan password bikinan', function () {
    $csv = "nama,nip\nSiti Aminah,198501012010012001\nAhmad Fauzi,\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)])
        ->assertRedirect(route('admin.guru.index'));

    $siti = User::where('name', 'Siti Aminah')->firstOrFail();
    $ahmad = User::where('name', 'Ahmad Fauzi')->firstOrFail();
    $hasil = session('impor_guru');

    expect($hasil['dibuat'])->toBe(2)
        ->and($hasil['dilewati'])->toBe(0)
        // NIP dipakai jadi email karena pasti unik; tanpa NIP dipakai namanya.
        ->and($siti->email)->toBe('198501012010012001@sekolah.local')
        ->and($ahmad->email)->toBe('ahmad.fauzi@sekolah.local')
        ->and($siti->role)->toBe(Role::Guru)
        ->and($siti->is_active)->toBeTrue()
        // Password bikinan dikembalikan sekali supaya bisa dibagikan.
        ->and($hasil['akun'])->toHaveCount(2)
        ->and($hasil['akun'][0]['password'])->toHaveLength(12)
        ->and(Hash::check($hasil['akun'][0]['password'], $siti->password))->toBeTrue();
});

test('nama kembar tanpa nip mendapat email berbeda', function () {
    User::factory()->create(['email' => 'siti.aminah@sekolah.local']);
    $csv = "nama\nSiti Aminah\nSiti Aminah\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(session('impor_guru')['dibuat'])->toBe(2)
        ->and(User::where('email', 'siti.aminah2@sekolah.local')->exists())->toBeTrue()
        ->and(User::where('email', 'siti.aminah3@sekolah.local')->exists())->toBeTrue();
});

test('password yang ditulis sendiri di berkas tidak dikembalikan', function () {
    $csv = "nama,nip,email,password\nSiti,1,siti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    expect(session('impor_guru')['dibuat'])->toBe(1)
        ->and(session('impor_guru')['akun'])->toHaveCount(0);
});

test('guru hasil impor langsung muncul di kelola user sebagai role guru', function () {
    $csv = "nama,nip\nSiti Aminah,198501012010012001\n";
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)]);

    $this->actingAs($admin)
        ->get(route('admin.user.index'))
        ->assertInertia(fn ($p) => $p->has('users', 2));

    $this->actingAs($admin)
        ->get(route('admin.guru.index'))
        ->assertInertia(fn ($p) => $p->has('gurus', 1));
});

test('berkas selain csv ditolak', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.guru.impor'), ['berkas' => UploadedFile::fake()->create('daftar.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('berkas');

    expect(User::where('role', Role::Guru)->count())->toBe(0);
});

test('guru tidak bisa mengimpor', function () {
    $csv = "nama,nip,email,password\nSiti,,siti@sekolah.sch.id,RahasiaKuat123\n";

    $this->actingAs(User::factory()->create())
        ->post(route('admin.guru.impor'), ['berkas' => berkasCsv($csv)])
        ->assertForbidden();

    expect(User::where('email', 'siti@sekolah.sch.id')->exists())->toBeFalse();
});
