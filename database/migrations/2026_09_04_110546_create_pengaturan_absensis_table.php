<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan waktu absen yang berlaku untuk seluruh sekolah.
     *
     * Satu baris saja: toleransi dan lebar jendela tidak berbeda antar guru,
     * yang berbeda hanya jam masuk/pulangnya (lihat jadwal_kerjas).
     */
    public function up(): void
    {
        Schema::create('pengaturan_absensis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('toleransi_menit')->default(10);
            $table->unsignedSmallInteger('buka_masuk_menit')->default(60);
            $table->unsignedSmallInteger('tutup_masuk_menit')->default(120);
            $table->unsignedSmallInteger('buka_pulang_menit')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_absensis');
    }
};
