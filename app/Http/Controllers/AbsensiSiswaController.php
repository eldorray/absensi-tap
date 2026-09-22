<?php

namespace App\Http\Controllers;

use App\Actions\Kesiswaan\BukaFinalisasiAbsensiSiswa;
use App\Actions\Kesiswaan\SimpanAbsensiSiswa;
use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Http\Requests\SimpanAbsensiSiswaRequest;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruKelas;
use App\Models\Kelas;
use App\Models\PengaturanAplikasi;
use App\Models\SesiAbsensiSiswa;
use App\Models\User;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiSiswaController extends Controller
{
    public function index(Request $request): Response
    {
        $guru = $request->user();
        $tanggal = $this->tanggalDiminta($request);
        $piket = $guru->can('piket');

        // Guru piket mengabsen seluruh sekolah, jadi daftarnya seluruh kelas
        // aktif tahun berjalan dan diurut per unit agar bisa dijelajahi
        // kelas demi kelas. Guru cukup kelas yang diampu (wali atau
        // pengganti) pada tanggal itu.
        $query = Kelas::query()->where('is_active', true)->with('kantor:id,nama');

        if ($piket) {
            $query->orderBy('kantor_id')->orderBy('tingkat')->orderBy('nama');
        } else {
            $query->diampuOleh($guru, $tanggal);
        }

        $kelas = $query->get()->map(function (Kelas $kelas) use ($tanggal): array {
            $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->first();

            return ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'jumlah_siswa' => AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->count(), 'status' => $sesi?->status->value ?? StatusSesiAbsensiSiswa::BelumDiperiksa->value, 'terakhir_disimpan' => $sesi?->updated_at?->toIso8601String()];
        })->values()->all();

        return Inertia::render('absensi-siswa/Index', ['kelas' => $kelas, 'tanggal' => $tanggal->toDateString(), 'piket' => $piket]);
    }

    public function show(Request $request, Kelas $kelas): Response
    {
        $guru = $request->user();
        $tanggal = $this->tanggalDiminta($request);
        $piket = $guru->can('piket');

        abort_unless($piket || Kelas::query()->diampuOleh($guru, $tanggal)->whereKey($kelas->id)->exists(), 403);
        $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->with('absensis')->first();
        $details = $sesi?->absensis->keyBy('siswa_id') ?? collect();
        $siswas = AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->with('siswa:id,nis,nama')->get()->sortBy(fn (AnggotaKelas $a) => $a->siswa?->nama)->values()->map(function (AnggotaKelas $a) use ($details): array {
            $detail = $details->get($a->siswa_id);

            return ['id' => $a->siswa_id, 'nis' => $a->siswa?->nis, 'nama' => $a->siswa?->nama, 'status' => $detail?->status->value ?? StatusKehadiranSiswa::Hadir->value, 'catatan' => $detail?->catatan, 'jam_datang' => $detail?->jam_datang];
        })->all();
        $status = $sesi === null ? StatusSesiAbsensiSiswa::BelumDiperiksa : $sesi->status;
        $terkunci = in_array($status, [StatusSesiAbsensiSiswa::Final, StatusSesiAbsensiSiswa::Dikoreksi], true);
        // Mengisi absensi hanya hak guru piket, dan hanya untuk hari ini.
        // Bagi guru, lembar ini selalu terbaca saja.
        $dapatMengisi = $piket && $tanggal->isToday() && ! $terkunci;
        $kelas->load('kantor:id,nama', 'tahunAjaran:id,nama');

        return Inertia::render('absensi-siswa/Show', ['kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'tahun_ajaran' => $kelas->tahunAjaran?->nama], 'tanggal' => $tanggal->toDateString(), 'siswa' => $siswas, 'piket' => $piket, 'sesi' => ['status' => $status->value, 'catatan' => $sesi?->catatan, 'read_only' => ! $dapatMengisi, 'dapat_mengisi' => $dapatMengisi, 'dapat_dibuka' => $piket && $tanggal->isToday() && $status === StatusSesiAbsensiSiswa::Final]]);
    }

    /**
     * Membatalkan finalisasi yang terlanjur ditekan, hari ini saja.
     */
    public function bukaFinalisasi(Request $request, Kelas $kelas, BukaFinalisasiAbsensiSiswa $action): RedirectResponse
    {
        $action->execute($kelas, $request->user(), Carbon::today());

        return to_route('absensi-siswa.show', $kelas)->with('success', 'Finalisasi dibatalkan. Absensi kembali jadi draft.');
    }

    /**
     * Lembar cetak absensi dalam bentuk halaman HTML siap cetak.
     *
     * Halaman ini yang dipakai kalau mau melihat dulu sebelum mencetak, atau
     * memakai dialog cetak peramban. Versi berkasnya ada di cetakPdf(), dan
     * keduanya memakai data yang sama dari dataCetak().
     */
    public function cetak(Request $request): View
    {
        return view('absensi-siswa.cetak', $this->dataCetak($request));
    }

    /**
     * Laporan absensi sebagai berkas PDF yang langsung terunduh.
     *
     * Pustaka PDF-nya dipanggil langsung, bukan lewat paket pembungkus
     * Laravel-nya: aplikasi ini memakai kerangka kerja versi terbaru, dan
     * pembungkus itu terikat batas versi yang lebih tua.
     */
    public function cetakPdf(Request $request): HttpResponse
    {
        $data = $this->dataCetak($request);

        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('absensi-siswa.cetak-pdf', $data)->render());
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$data['berkas'].'"',
        ]);
    }

    /**
     * Data laporan absensi, dipakai bersama oleh halaman siap cetak dan berkas
     * PDF yang diunduh.
     *
     * Bentuk laporan ditentukan parameter `jenis`, bukan disimpulkan dari
     * rentang: meminta laporan periode untuk satu hari tetap harus keluar
     * sebagai rekap per siswa, bukan daftar harian.
     *
     * @return array<string, mixed>
     */
    private function dataCetak(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sampai' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:dari'],
            'jenis' => ['nullable', 'in:harian,periode'],
        ]);
        $dari = Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay();
        $guru = $request->user();
        $piket = $guru->can('piket');

        // Kelas yang mungkin tersentuh rentang ini diambil sekali, lengkap
        // dengan penugasan penggantinya, lalu kelayakan per tanggal diuji di
        // memori. Menanyakan scopeDiampuOleh() sekali per hari berarti satu
        // query per hari untuk rentang yang panjang. Guru piket mencetak
        // seluruh kelas, jadi penyaringan itu tidak berlaku baginya.
        $kelasKandidat = Kelas::query()
            ->when(! $piket, function ($query) use ($guru, $dari, $sampai): void {
                $query->where(function ($q) use ($guru, $dari, $sampai): void {
                    $q->where('wali_kelas_id', $guru->id)
                        ->orWhereHas('pengganti', function ($p) use ($guru, $dari, $sampai): void {
                            $p->where('user_id', $guru->id)
                                ->where(function ($b) use ($sampai): void {
                                    $b->whereNull('tanggal_mulai')->orWhere('tanggal_mulai', '<=', $sampai->toDateString());
                                })
                                ->where(function ($b) use ($dari): void {
                                    $b->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $dari->toDateString());
                                });
                        });
                });
            })
            ->with(['kantor:id,nama', 'pengganti' => fn ($p) => $p->where('user_id', $guru->id)])
            ->get()
            ->keyBy('id');

        $sesis = SesiAbsensiSiswa::query()
            ->whereIn('kelas_id', $kelasKandidat->keys())
            ->whereBetween('tanggal', [$dari, $sampai->copy()->endOfDay()])
            ->with('absensis.siswa:id,nis,nama')
            ->orderBy('tanggal')
            ->get()
            ->filter(fn (SesiAbsensiSiswa $sesi): bool => $this->bolehDilihat($kelasKandidat->get($sesi->kelas_id), $guru, $sesi->tanggal))
            ->map(function (SesiAbsensiSiswa $sesi) use ($kelasKandidat): array {
                $kelas = $kelasKandidat->get($sesi->kelas_id);
                $baris = $sesi->absensis
                    ->sortBy(fn (AbsensiSiswa $a) => $a->siswa?->nama)
                    ->map(fn (AbsensiSiswa $a): array => ['nis' => $a->siswa?->nis, 'nama' => $a->siswa?->nama, 'status' => $a->status->value, 'jam_datang' => $a->jam_datang, 'catatan' => $a->catatan])
                    ->values()
                    ->all();

                return [
                    'tanggal' => $sesi->tanggal->translatedFormat('l, d F Y'),
                    'kelas_id' => $sesi->kelas_id,
                    'kelas' => $kelas?->nama,
                    'kantor' => $kelas?->kantor?->nama,
                    'status' => $sesi->status->value,
                    'catatan' => $sesi->catatan,
                    'siswa' => $baris,
                    'ringkasan' => collect(StatusKehadiranSiswa::cases())
                        ->mapWithKeys(fn (StatusKehadiranSiswa $s): array => [$s->value => collect($baris)->where('status', $s->value)->count()])
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $harian = ($data['jenis'] ?? null) === 'harian'
            || (! isset($data['jenis']) && $dari->isSameDay($sampai));

        $aplikasi = PengaturanAplikasi::current();

        return [
            'aplikasi' => $aplikasi,
            'guru' => $guru->name,
            'harian' => $harian,
            'rekap' => $harian ? [] : $this->rekapPerSiswa($sesis),
            'periode' => $harian
                ? $dari->translatedFormat('l, d F Y')
                : $dari->translatedFormat('d F Y').' - '.$sampai->translatedFormat('d F Y'),
            'dicetak' => now()->translatedFormat('d F Y H:i'),
            'sesis' => $sesis,
            'statuses' => array_map(fn (StatusKehadiranSiswa $s): string => $s->value, StatusKehadiranSiswa::cases()),
            'berkas' => $this->namaBerkas($harian, $dari, $sampai, $sesis),
            'logo' => $this->logoDataUri($aplikasi),
        ];
    }

    /**
     * Nama berkas unduhan, mis. absensi-siswa-periode-kelas-vii-a-2026-09-01-sd-2026-09-14.pdf.
     *
     * @param  list<array{kelas: string|null}>  $sesis
     */
    private function namaBerkas(bool $harian, Carbon $dari, Carbon $sampai, array $sesis): string
    {
        // Kelas disebut di nama berkas hanya kalau laporannya memang satu kelas.
        $kelas = count($sesis) === 1 && ($sesis[0]['kelas'] ?? null) !== null
            ? Str::slug((string) $sesis[0]['kelas']).'-'
            : '';

        $rentang = $harian
            ? $dari->toDateString()
            : $dari->toDateString().'-sd-'.$sampai->toDateString();

        return 'absensi-siswa-'.($harian ? 'harian' : 'periode').'-'.$kelas.$rentang.'.pdf';
    }

    /**
     * Logo sekolah sebagai data URI, supaya PDF tidak bergantung pada akses
     * jaringan atau symlink storage saat dirender. Null kalau belum ada logo.
     */
    private function logoDataUri(PengaturanAplikasi $aplikasi): ?string
    {
        $path = $aplikasi->logo_path ?? $aplikasi->favicon_path;

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * Rekap satu baris per siswa: berapa kali hadir, sakit, izin, alpa, dan
     * terlambat sepanjang rentang. Bentuk inilah yang dibaca wali kelas untuk
     * periode panjang -- daftar per hari hanya berguna untuk sehari.
     *
     * @param  list<array{kelas_id: int, kelas: string|null, kantor: string|null, tanggal: string, siswa: list<array{nis: string|null, nama: string|null, status: string}>}>  $sesis
     * @return list<array{kelas: string|null, kantor: string|null, hari: int, siswa: list<array{nis: string|null, nama: string|null, hitung: array<string, int>, total: int}>}>
     */
    private function rekapPerSiswa(array $sesis): array
    {
        $kosong = collect(StatusKehadiranSiswa::cases())->mapWithKeys(fn (StatusKehadiranSiswa $s): array => [$s->value => 0])->all();

        return collect($sesis)
            ->groupBy('kelas_id')
            ->map(function ($sesisKelas) use ($kosong): array {
                $siswa = [];

                foreach ($sesisKelas as $sesi) {
                    foreach ($sesi['siswa'] as $baris) {
                        $kunci = $baris['nis'] ?? $baris['nama'] ?? '-';
                        $siswa[$kunci] ??= ['nis' => $baris['nis'], 'nama' => $baris['nama'], 'hitung' => $kosong, 'total' => 0];
                        $siswa[$kunci]['hitung'][$baris['status']]++;
                        $siswa[$kunci]['total']++;
                    }
                }

                return [
                    'kelas' => $sesisKelas->first()['kelas'],
                    'kantor' => $sesisKelas->first()['kantor'],
                    'hari' => $sesisKelas->count(),
                    'siswa' => collect($siswa)->sortBy('nama')->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Kelayakan guru atas satu kelas pada satu tanggal, diuji di memori dengan
     * aturan yang sama seperti Kelas::scopeDiampuOleh(). Guru piket melihat
     * seluruh kelas, jadi aturan wali/pengganti hanya mengikat guru.
     */
    private function bolehDilihat(?Kelas $kelas, User $guru, CarbonInterface $tanggal): bool
    {
        if ($kelas === null) {
            return false;
        }

        if ($guru->can('piket') || $kelas->wali_kelas_id === $guru->id) {
            return true;
        }

        return $kelas->pengganti->contains(fn (GuruKelas $tugas): bool => ($tugas->tanggal_mulai === null || $tugas->tanggal_mulai->startOfDay()->lessThanOrEqualTo($tanggal))
            && ($tugas->tanggal_selesai === null || $tugas->tanggal_selesai->startOfDay()->greaterThanOrEqualTo($tanggal)));
    }

    public function draft(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action): RedirectResponse
    {
        return $this->save($request, $kelas, $action, false);
    }

    public function finalisasi(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action): RedirectResponse
    {
        return $this->save($request, $kelas, $action, true);
    }

    /**
     * Tanggal yang sedang dilihat. Hanya hari ini yang bisa disunting: hari
     * lampau dibuka untuk membaca hasil finalisasi, hari depan tidak ada.
     */
    private function tanggalDiminta(Request $request): Carbon
    {
        $data = $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);

        return isset($data['tanggal']) ? Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay() : Carbon::today();
    }

    private function save(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $action, bool $final): RedirectResponse
    {
        /** @var array{tanggal: string, catatan?: string|null, absensis: list<array{siswa_id: int, status: string, catatan?: string|null, jam_datang?: string|null}>} $data */
        $data = $request->validated();
        $action->execute($kelas, $request->user(), Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay(), $data, $final);

        return to_route('absensi-siswa.show', $kelas)->with('success', $final ? 'Absensi berhasil difinalisasi.' : 'Draft berhasil disimpan.');
    }
}
