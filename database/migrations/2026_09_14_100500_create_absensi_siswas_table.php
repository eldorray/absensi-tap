<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sesi_absensi_siswa_id')->constrained('sesi_absensi_siswas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->restrictOnDelete();
            $table->string('status', 20);
            $table->time('jam_datang')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['sesi_absensi_siswa_id', 'siswa_id']);
            $table->index(['siswa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_siswas');
    }
};
