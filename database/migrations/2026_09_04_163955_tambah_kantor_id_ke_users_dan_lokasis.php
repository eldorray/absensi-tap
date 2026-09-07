<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guru ditugaskan ke satu kantor, dan setiap titik absen dimiliki kantor.
     *
     * Keduanya nullable: sekolah yang cuma punya satu unit boleh tidak memakai
     * kantor sama sekali, dan absen tetap jalan seperti sebelumnya.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('kantor_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('lokasis', function (Blueprint $table): void {
            $table->foreignId('kantor_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lokasis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kantor_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kantor_id');
        });
    }
};
