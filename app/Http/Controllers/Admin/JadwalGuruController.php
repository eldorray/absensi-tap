<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanJadwalRequest;
use App\Models\JadwalKerja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal kerja milik guru per orang.
 *
 * Guru yang belum diatur memakai jadwal default sekolah, jadi halaman ini hanya
 * perlu menyimpan yang menyimpang -- dan bisa mengembalikannya ke default.
 */
class JadwalGuruController extends Controller
{
    public function index(): Response
    {
        $default = $this->petaDefault();

        $gurus = User::query()
            ->where('role', Role::Guru)
            ->orderBy('name')
            ->get(['id', 'name', 'nip'])
            ->map(function (User $guru) use ($default): array {
                $milikGuru = JadwalKerja::query()
                    ->where('user_id', $guru->id)
                    ->orderBy('day_of_week')
                    ->get()
                    ->keyBy('day_of_week');

                $jadwals = [];

                foreach (range(0, 6) as $day) {
                    $jadwal = $milikGuru->get($day) ?? $default->get($day);

                    $jadwals[] = [
                        'day_of_week' => $day,
                        'jam_masuk' => substr($jadwal->jam_masuk ?? '07:00:00', 0, 5),
                        'jam_pulang' => substr($jadwal->jam_pulang ?? '14:00:00', 0, 5),
                        'is_hari_kerja' => $jadwal->is_hari_kerja ?? ($day !== 0),
                    ];
                }

                return [
                    'id' => $guru->id,
                    'name' => $guru->name,
                    'nip' => $guru->nip,
                    'punya_jadwal_sendiri' => $milikGuru->isNotEmpty(),
                    'jadwals' => $jadwals,
                ];
            })
            ->all();

        return Inertia::render('admin/JadwalGuru', ['gurus' => $gurus]);
    }

    public function update(SimpanJadwalRequest $request, User $guru): RedirectResponse
    {
        /** @var array<int, array<string, mixed>> $jadwals */
        $jadwals = $request->validated()['jadwals'];

        DB::transaction(function () use ($guru, $jadwals): void {
            foreach ($jadwals as $jadwal) {
                JadwalKerja::updateOrCreate(
                    ['user_id' => $guru->id, 'day_of_week' => $jadwal['day_of_week']],
                    [
                        'jam_masuk' => $jadwal['jam_masuk'].':00',
                        'jam_pulang' => $jadwal['jam_pulang'].':00',
                        'is_hari_kerja' => $jadwal['is_hari_kerja'],
                    ],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal '.$guru->name.' disimpan.']);

        return to_route('admin.jadwal-guru.index');
    }

    /**
     * Buang jadwal khusus guru ini, kembali mengikuti default sekolah.
     */
    public function destroy(User $guru): RedirectResponse
    {
        JadwalKerja::query()->where('user_id', $guru->id)->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal '.$guru->name.' kembali ke default.']);

        return to_route('admin.jadwal-guru.index');
    }

    /**
     * @return Collection<int, JadwalKerja>
     */
    private function petaDefault(): Collection
    {
        return JadwalKerja::query()->whereNull('user_id')->get()->keyBy('day_of_week');
    }
}
