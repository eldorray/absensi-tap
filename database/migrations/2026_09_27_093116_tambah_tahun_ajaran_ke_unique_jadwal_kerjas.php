<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal milik tahun ajaran, jadi keunikannya juga per tahun ajaran.
     *
     * Tanpa tahun_ajaran_id di sini, jadwal khusus seorang guru hanya bisa ada
     * di satu tahun ajaran: menyimpannya di tahun berikutnya bentrok dengan
     * baris tahun lalu.
     */
    public function up(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            // Index user_id dibuat dulu: di MySQL foreign key user_id butuh
            // index, dan unique lama adalah satu-satunya yang diawali user_id.
            $table->index('user_id');
            $table->unique(['tahun_ajaran_id', 'user_id', 'day_of_week']);
            $table->dropUnique(['user_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->unique(['user_id', 'day_of_week']);
            $table->dropUnique(['tahun_ajaran_id', 'user_id', 'day_of_week']);
            $table->dropIndex(['user_id']);
        });
    }
};
