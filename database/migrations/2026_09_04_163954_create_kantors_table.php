<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit sekolah di bawah satu yayasan, mis. MI dan SMP.
     *
     * Bukan data tahunan: kantor tetap ada meski tahun ajaran berganti.
     */
    public function up(): void
    {
        Schema::create('kantors', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 120);
            $table->string('jenjang', 20)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantors');
    }
};
