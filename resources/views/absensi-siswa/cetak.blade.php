<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Absensi Siswa {{ $periode }}</title>
    <style>
        /* CSS mentah, bukan Tailwind: halaman ini dicetak, jadi tidak boleh
           bergantung pada bundel Vite yang bisa belum ter-build. */
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
        .sesi { margin-top: 20px; }
        .sesi h2 { margin: 0 0 2px; font-size: 13px; }
        .sesi .meta { margin: 0; font-size: 10px; color: #555; }
        .sesi .catatan { margin: 6px 0 0; font-size: 10px; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.angka, th.angka { text-align: right; font-variant-numeric: tabular-nums; }
        td.status { text-transform: capitalize; }
        tbody tr:nth-child(even) { background: #f7f7f7; }
        .ringkas { margin-top: 6px; font-size: 10px; }
        .ringkas span { display: inline-block; margin-right: 12px; text-transform: capitalize; }
        .kosong { margin-top: 24px; padding: 16px; border: 1px dashed #999; text-align: center; color: #555; }
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
            .sesi { break-inside: avoid; }
        }
        @page { size: A4 portrait; margin: 12mm; }
    </style>
</head>
<body>
    <div class="cetak-btn">
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <header>
        @if ($aplikasi->logoUrl() || $aplikasi->faviconUrl())
            <img src="{{ $aplikasi->logoUrl() ?? $aplikasi->faviconUrl() }}" alt="">
        @endif
        <div>
            <h1>{{ $aplikasi->nama }} — Absensi Siswa</h1>
            <p class="periode">{{ $harian ? 'Harian' : 'Periode' }}: {{ $periode }}</p>
            <p class="dicetak">Guru: {{ $guru }} · Dicetak {{ $dicetak }}</p>
        </div>
    </header>

    @if (! $harian)
        @forelse ($rekap as $kelas)
            <section class="sesi">
                <h2>Kelas {{ $kelas['kelas'] }} @if ($kelas['kantor']) <span class="meta">({{ $kelas['kantor'] }})</span> @endif</h2>
                <p class="meta">{{ $kelas['hari'] }} hari absensi tercatat pada rentang ini</p>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 28px;" class="angka">#</th>
                            <th style="width: 90px;">NIS</th>
                            <th>Nama</th>
                            @foreach ($statuses as $status)
                                <th style="width: 44px;" class="angka" title="{{ $status }}">{{ Str::upper(Str::substr($status, 0, 1)) }}</th>
                            @endforeach
                            <th style="width: 52px;" class="angka">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kelas['siswa'] as $index => $siswa)
                            <tr>
                                <td class="angka">{{ $index + 1 }}</td>
                                <td>{{ $siswa['nis'] }}</td>
                                <td>{{ $siswa['nama'] }}</td>
                                @foreach ($statuses as $status)
                                    <td class="angka">{{ $siswa['hitung'][$status] ?: '' }}</td>
                                @endforeach
                                <td class="angka">{{ $siswa['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="ringkas">
                    @foreach ($statuses as $status)
                        <span><strong>{{ Str::upper(Str::substr($status, 0, 1)) }}</strong> = {{ $status }}</span>
                    @endforeach
                </p>
            </section>
        @empty
            <p class="kosong">Tidak ada absensi yang tercatat pada rentang ini.</p>
        @endforelse
    @else
    @forelse ($sesis as $sesi)
        <section class="sesi">
            <h2>Kelas {{ $sesi['kelas'] }} @if ($sesi['kantor']) <span class="meta">({{ $sesi['kantor'] }})</span> @endif</h2>
            <p class="meta">{{ $sesi['tanggal'] }} · Status sesi: {{ str_replace('_', ' ', $sesi['status']) }}</p>

            <table>
                <thead>
                    <tr>
                        <th style="width: 28px;" class="angka">#</th>
                        <th style="width: 90px;">NIS</th>
                        <th>Nama</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 60px;">Datang</th>
                        <th style="width: 140px;">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sesi['siswa'] as $index => $siswa)
                        <tr>
                            <td class="angka">{{ $index + 1 }}</td>
                            <td>{{ $siswa['nis'] }}</td>
                            <td>{{ $siswa['nama'] }}</td>
                            <td class="status">{{ $siswa['status'] }}</td>
                            <td>{{ $siswa['jam_datang'] }}</td>
                            <td>{{ $siswa['catatan'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="ringkas">
                @foreach ($statuses as $status)
                    <span><strong>{{ $sesi['ringkasan'][$status] }}</strong> {{ $status }}</span>
                @endforeach
            </p>

            @if ($sesi['catatan'])
                <p class="catatan">Catatan sesi: {{ $sesi['catatan'] }}</p>
            @endif
        </section>
    @empty
        <p class="kosong">Tidak ada absensi yang tercatat pada rentang ini.</p>
    @endforelse
    @endif

    <div class="ttd">
        <div>
            <span>Guru kelas</span>
            <div class="garis">{{ $guru }}</div>
        </div>
    </div>
</body>
</html>
