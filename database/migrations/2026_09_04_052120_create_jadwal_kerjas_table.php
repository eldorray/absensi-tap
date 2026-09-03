<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jadwal_kerjas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->unsignedSmallInteger('toleransi_menit')->default(10);
            $table->boolean('is_hari_kerja')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_kerjas');
    }
};
