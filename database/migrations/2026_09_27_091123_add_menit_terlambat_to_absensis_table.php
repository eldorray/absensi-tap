<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table): void {
            $table->unsignedSmallInteger('menit_terlambat')->default(0)->after('status');
        });

        // Baris lama diisi dari jadwal yang berlaku sekarang -- jadwal saat tap
        // tidak pernah disimpan. Selisih negatif (jam masuk sudah diundur)
        // dicatat 0.
        $terlambat = DB::table('absensis')
            ->join('absensi_attempts', 'absensi_attempts.id', '=', 'absensis.masuk_attempt_id')
            ->where('absensis.status', 'terlambat')
            ->get(['absensis.id', 'absensis.user_id', 'absensis.tahun_ajaran_id', 'absensis.tanggal', 'absensi_attempts.created_at']);

        foreach ($terlambat as $absensi) {
            $tanggal = Carbon::parse($absensi->tanggal);
            $jamMasuk = DB::table('jadwal_kerjas')
                ->where('tahun_ajaran_id', $absensi->tahun_ajaran_id)
                ->where('day_of_week', $tanggal->dayOfWeek)
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $absensi->user_id))
                // Jadwal milik guru didahulukan dari default sekolah.
                ->orderByRaw('user_id is null')
                ->value('jam_masuk');

            if ($jamMasuk === null) {
                continue;
            }

            $menit = (int) $tanggal->setTimeFromTimeString($jamMasuk)->diffInMinutes(Carbon::parse($absensi->created_at));

            DB::table('absensis')->where('id', $absensi->id)->update(['menit_terlambat' => max(0, $menit)]);
        }
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table): void {
            $table->dropColumn('menit_terlambat');
        });
    }
};
