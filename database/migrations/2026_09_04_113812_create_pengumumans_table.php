<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengumuman singkat yang tampil di halaman absen guru.
     */
    public function up(): void
    {
        Schema::create('pengumumans', function (Blueprint $table): void {
            $table->id();
            $table->string('judul', 120);
            $table->text('isi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumumans');
    }
};
