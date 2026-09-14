<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guru pengganti untuk satu kelas, berjangka waktu.
     *
     * ponytail: tanpa kolom "jenis". Wali kelas tinggal di kelas.wali_kelas_id
     * dan itu satu-satunya sumbernya; setiap baris di sini selalu berarti
     * pengganti. Kolom yang cuma pernah bernilai satu hal adalah keleluasaan
     * palsu yang harus dijaga selamanya. Kalau nanti benar-benar muncul jenis
     * kedua (mis. guru mapel), tambahkan kolomnya lewat satu migration
     * additive dan perbaiki scope Kelas::diampuOleh().
     *
     * Tanggal boleh kosong: penugasan tanpa batas waktu berlaku terus.
     */
    public function up(): void
    {
        Schema::create('guru_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_kelas');
    }
};
