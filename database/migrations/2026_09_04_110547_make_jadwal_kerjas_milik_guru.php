<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal jadi milik guru, dengan baris default sekolah sebagai cadangan.
     *
     * user_id null = jadwal default yang dipakai setiap guru yang belum punya
     * jadwal sendiri, jadi admin cukup mengatur yang menyimpang saja. Kolom
     * menit pindah ke pengaturan_absensis karena berlaku global.
     */
    public function up(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['day_of_week']);
            $table->unique(['user_id', 'day_of_week']);
            $table->dropColumn([
                'toleransi_menit',
                'buka_masuk_menit',
                'tutup_masuk_menit',
                'buka_pulang_menit',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('toleransi_menit')->default(10);
            $table->unsignedSmallInteger('buka_masuk_menit')->default(60);
            $table->unsignedSmallInteger('tutup_masuk_menit')->default(120);
            $table->unsignedSmallInteger('buka_pulang_menit')->default(30);
            $table->dropUnique(['user_id', 'day_of_week']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('day_of_week');
        });
    }
};
