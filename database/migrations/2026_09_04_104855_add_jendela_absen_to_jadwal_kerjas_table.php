<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jendela waktu absen, dalam menit, relatif terhadap jam jadwal hari itu.
     *
     * Default meniru AbsenKU: masuk dibuka satu jam sebelum bel, ditutup dua jam
     * sesudahnya, dan absen pulang dibuka setengah jam sebelum jam pulang.
     */
    public function up(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('buka_masuk_menit')->default(60)->after('toleransi_menit');
            $table->unsignedSmallInteger('tutup_masuk_menit')->default(120)->after('buka_masuk_menit');
            $table->unsignedSmallInteger('buka_pulang_menit')->default(30)->after('tutup_masuk_menit');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->dropColumn(['buka_masuk_menit', 'tutup_masuk_menit', 'buka_pulang_menit']);
        });
    }
};
