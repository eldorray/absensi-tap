<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahun ajaran yang menyekat seluruh data absensi.
     *
     * Hanya satu boleh aktif. Mengaktifkan yang baru tidak menghapus apa pun:
     * data tahun lalu tetap utuh dan bisa dilihat dengan memilih tahunnya.
     */
    public function up(): void
    {
        Schema::create('tahun_ajarans', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 20)->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahun_ajarans');
    }
};
