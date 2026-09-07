<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identitas aplikasi: nama, logo, dan favicon.
     *
     * Satu baris saja, dan bukan data tahunan -- identitas sekolah tidak
     * berganti setiap tahun ajaran.
     */
    public function up(): void
    {
        Schema::create('pengaturan_aplikasis', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 60)->default('Absensi Guru');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_aplikasis');
    }
};
