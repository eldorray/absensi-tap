<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Kehadiran {{ $periode }}</title>
    <style>
        /* Sengaja CSS mentah, bukan Tailwind: halaman ini dicetak, jadi tidak
           boleh bergantung pada bundel Vite yang bisa belum ter-build. */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }
        header { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #111; padding-bottom: 12px; }
        header img { height: 48px; width: auto; object-fit: contain; }
        h1 { margin: 0; font-size: 16px; }
        .periode { margin: 2px 0 0; font-size: 12px; }
        .dicetak { margin: 2px 0 0; font-size: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #999; padding: 5px 6px; text-align: left; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.angka, th.angka { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) { background: #f7f7f7; }
        tfoot td { font-weight: bold; background: #eee; }
        .ttd { margin-top: 36px; display: flex; justify-content: flex-end; }
        .ttd div { width: 200px; text-align: center; }
        .ttd .garis { margin-top: 56px; border-top: 1px solid #111; padding-top: 4px; }
        .cetak-btn { margin-bottom: 16px; }
        .cetak-btn button { padding: 8px 16px; font-size: 12px; cursor: pointer; }
        @media print {
            body { padding: 0; }
            .cetak-btn { display: none; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
        @page { size: A4 landscape; margin: 12mm; }
    </style>
</head>
<body>
    <div class="cetak-btn">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <header>
        @if ($aplikasi->faviconUrl() || $aplikasi->logoUrl())
            <img src="{{ $aplikasi->logoUrl() ?? $aplikasi->faviconUrl() }}" alt="">
        @endif
        <div>
            <h1>{{ $aplikasi->nama }}</h1>
            <p class="periode">Rekap Kehadiran Guru &middot; {{ $periode }}</p>
            <p class="dicetak">Dicetak {{ $dicetak }}</p>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width:28px">No</th>
                <th>Nama</th>
                <th>NIP</th>
                <th class="angka">Hari efektif</th>
                <th class="angka">Total kehadiran</th>
                <th class="angka">Absen masuk</th>
                <th class="angka">Absen pulang</th>
                <th class="angka">Terlambat</th>
                <th class="angka">Menit terlambat</th>
                <th class="angka">% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $index => $b)
                <tr>
                    <td class="angka">{{ $index + 1 }}</td>
                    <td>{{ $b['nama'] }}</td>
                    <td>{{ $b['nip'] ?? '-' }}</td>
                    <td class="angka">{{ $b['hari_efektif'] }}</td>
                    <td class="angka">{{ $b['kehadiran'] }}</td>
                    <td class="angka">{{ $b['masuk'] }}</td>
                    <td class="angka">{{ $b['pulang'] }}</td>
                    <td class="angka">{{ $b['terlambat'] }}</td>
                    <td class="angka">{{ $b['menit_terlambat'] }} menit</td>
                    <td class="angka">{{ number_format($b['persentase'], 2, ',', '.') }}%</td>
                </tr>
            @empty
                <tr><td colspan="10">Belum ada akun guru pada tahun ajaran ini.</td></tr>
            @endforelse
        </tbody>
        @if (count($baris) > 0)
            <tfoot>
                <tr>
                    <td colspan="3">Jumlah</td>
                    <td class="angka">{{ $totalHariEfektif }}</td>
                    <td class="angka">{{ collect($baris)->sum('kehadiran') }}</td>
                    <td class="angka">{{ collect($baris)->sum('masuk') }}</td>
                    <td class="angka">{{ collect($baris)->sum('pulang') }}</td>
                    <td class="angka">{{ collect($baris)->sum('terlambat') }}</td>
                    <td class="angka">{{ collect($baris)->sum('menit_terlambat') }} menit</td>
                    <td class="angka">
                        {{ $totalHariEfektif > 0 && count($baris) > 0
                            ? number_format(collect($baris)->sum('kehadiran') / ($totalHariEfektif * count($baris)) * 100, 2, ',', '.')
                            : '0,00' }}%
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="ttd">
        <div>
            <p>Mengetahui,</p>
            <p class="garis">Kepala Sekolah</p>
        </div>
    </div>
</body>
</html>
