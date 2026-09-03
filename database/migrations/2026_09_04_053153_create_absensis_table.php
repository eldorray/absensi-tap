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
        Schema::create('absensis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('masuk_attempt_id')->nullable()->constrained('absensi_attempts')->nullOnDelete();
            $table->foreignId('pulang_attempt_id')->nullable()->constrained('absensi_attempts')->nullOnDelete();
            $table->string('status')->nullable();
            $table->boolean('pulang_cepat')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};
