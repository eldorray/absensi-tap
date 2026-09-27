<?php

use App\Support\IsiKaya;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Isi pengumuman kini HTML dari editor. Isi lama berupa teks polos diubah
     * jadi paragraf HTML (karakternya di-escape), supaya frontend cukup
     * mengenal satu format.
     */
    public function up(): void
    {
        DB::table('pengumumans')->orderBy('id')->each(function (object $pengumuman): void {
            if (str_contains((string) $pengumuman->isi, '<')) {
                return;
            }

            DB::table('pengumumans')
                ->where('id', $pengumuman->id)
                ->update(['isi' => IsiKaya::dariTeksPolos((string) $pengumuman->isi)]);
        });
    }

    public function down(): void
    {
        DB::table('pengumumans')->orderBy('id')->each(function (object $pengumuman): void {
            DB::table('pengumumans')
                ->where('id', $pengumuman->id)
                ->update(['isi' => IsiKaya::teks((string) $pengumuman->isi)]);
        });
    }
};
