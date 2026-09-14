<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelas/rombel, satu baris per tahun ajaran.
     *
     * "5A" tahun ini bukan "5A" tahun lalu: isinya anak yang berbeda dan
     * walinya bisa berganti. Karena itu namanya hanya unik di dalam satu
     * tahun ajaran dan satu kantor, bukan unik selamanya.
     *
     * Wali kelas ditaruh di sini, bukan di tabel penugasan terpisah, supaya
     * pertanyaan "siapa wali kelas ini" punya satu jawaban saja.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('kantor_id')->constrained()->restrictOnDelete();
            $table->string('nama', 30);
            $table->unsignedTinyInteger('tingkat');
            $table->foreignId('wali_kelas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'kantor_id', 'nama']);
            $table->index(['tahun_ajaran_id', 'is_active']);
            $table->index('wali_kelas_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
