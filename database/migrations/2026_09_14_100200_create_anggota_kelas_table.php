<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat penempatan siswa di kelas.
     *
     * Sengaja bukan kolom kelas_id di tabel siswa: kalau kelas ditulis di
     * siswa, kenaikan kelas menimpanya dan seluruh absensi tahun lalu ikut
     * berpindah kelas. Di sini perpindahan menutup baris lama dan membuka
     * yang baru, jadi "siswa ini ada di kelas mana pada 20 Agustus" tetap
     * bisa dijawab bertahun-tahun kemudian.
     *
     * Aturan "hanya satu keanggotaan aktif per siswa per tahun ajaran"
     * ditegakkan App\Actions\Kesiswaan\TempatkanSiswa; MySQL tidak punya
     * exclusion constraint. Unique di bawah adalah pertahanan terakhir
     * terhadap klik ganda, bukan penegak aturannya.
     */
    public function up(): void
    {
        Schema::create('anggota_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kelas_id', 'siswa_id', 'tanggal_mulai']);
            $table->index(['siswa_id', 'is_active']);
            $table->index(['kelas_id', 'is_active']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota_kelas');
    }
};
