<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siswa sebagai entitas akademik, tanpa kredensial login.
     *
     * Bukan data tahunan: anak yang sama melewati beberapa tahun ajaran, jadi
     * tabel ini tidak memakai tahun_ajaran_id. Yang tahunan adalah kelasnya.
     *
     * NIS unik per kantor, bukan global: MI dan SMP adalah unit terpisah yang
     * masing-masing menomori siswanya sendiri, dan memaksa satu ruang nomor
     * membuat impor buku induk salah satu unit selalu bentrok.
     */
    public function up(): void
    {
        Schema::create('siswas', function (Blueprint $table): void {
            $table->id();
            // restrictOnDelete: kantor yang masih punya siswa tidak boleh
            // lenyap beserta seluruh riwayat kehadiran anak-anaknya.
            $table->foreignId('kantor_id')->constrained()->restrictOnDelete();
            $table->string('nis', 30);
            $table->string('nisn', 20)->nullable();
            $table->string('nama', 120);
            $table->string('jenis_kelamin', 1);
            $table->date('tanggal_lahir')->nullable();
            $table->string('foto', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kantor_id', 'nis']);
            $table->unique('nisn');
            $table->index(['kantor_id', 'is_active']);
            $table->index('nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
