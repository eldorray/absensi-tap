<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_absensi_siswas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained()->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->date('tanggal');
            $table->string('status', 20)->default('belum_diperiksa');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalisasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalisasi_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['kelas_id', 'tanggal']);
            $table->index(['tahun_ajaran_id', 'tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_absensi_siswas');
    }
};
