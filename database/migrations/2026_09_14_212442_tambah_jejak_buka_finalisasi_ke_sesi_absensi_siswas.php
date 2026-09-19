<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_absensi_siswas', function (Blueprint $table): void {
            // Finalisasi yang kepencet harus bisa dibuka lagi, tapi jangan diam-diam:
            // dua kolom ini menjawab "siapa yang membuka, dan kapan".
            $table->foreignId('dibuka_oleh')->nullable()->after('finalisasi_pada')->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_pada')->nullable()->after('dibuka_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_absensi_siswas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dibuka_oleh');
            $table->dropColumn('dibuka_pada');
        });
    }
};
