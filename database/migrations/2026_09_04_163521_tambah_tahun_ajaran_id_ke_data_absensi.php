<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel yang isinya milik satu tahun ajaran.
     *
     * Guru, role, perangkat, kantor, lokasi, dan aturan jam absen sengaja tidak
     * ikut: itu infrastruktur sekolah, bukan data tahunan.
     *
     * @var list<string>
     */
    private const TABEL = [
        'absensis',
        'absensi_attempts',
        'izins',
        'jadwal_kerjas',
        'hari_liburs',
        'pengumumans',
    ];

    public function up(): void
    {
        $tahunId = $this->tahunPertama();

        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel): void {
                $table->foreignId('tahun_ajaran_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
                $table->index(['tahun_ajaran_id', 'created_at'], $tabel.'_tahun_ajaran_index');
            });

            // Data yang sudah ada dianggap milik tahun ajaran pertama, bukan
            // dibiarkan null -- baris null tidak akan muncul di rekap mana pun.
            DB::table($tabel)->whereNull('tahun_ajaran_id')->update(['tahun_ajaran_id' => $tahunId]);
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel): void {
                $table->dropIndex($tabel.'_tahun_ajaran_index');
                $table->dropConstrainedForeignId('tahun_ajaran_id');
            });
        }
    }

    /**
     * Tahun ajaran aktif, dibuat kalau tabelnya masih kosong.
     */
    private function tahunPertama(): int
    {
        $aktif = DB::table('tahun_ajarans')->where('is_active', true)->value('id');

        if ($aktif !== null) {
            return (int) $aktif;
        }

        $tahun = (int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1;

        return (int) DB::table('tahun_ajarans')->insertGetId([
            'nama' => $tahun.'/'.($tahun + 1),
            'tanggal_mulai' => $tahun.'-07-01',
            'tanggal_selesai' => ($tahun + 1).'-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
