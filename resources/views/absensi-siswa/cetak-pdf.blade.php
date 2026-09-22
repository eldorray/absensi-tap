<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Absensi Siswa {{ $periode }}</title>
    <style>
        /* Tata letak untuk dompdf: tabel dan blok biasa saja, tanpa flex/grid.
           Huruf memakai DejaVu Sans bawaan dompdf supaya karakter Indonesia
           (é, —, tanda kutip melengkung) tidak jatuh jadi kotak. */
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .kepala td { vertical-align: middle; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .kepala .logo { width: 64px; }
        .kepala img { height: 48px; }
        .nama { font-size: 14px; font-weight: bold; }
        .sub { font-size: 10px; color: #444; }
        .sesi { margin-top: 16px; page-break-inside: avoid; }
        .sesi h2 { margin: 0 0 2px; font-size: 12px; }
        .meta { margin: 0; font-size: 9px; color: #555; }
        table.data { margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; }
        table.data th { background: #eee; font-size: 9px; text-transform: uppercase; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        td.angka, th.angka { text-align: right; }
        td.status { text-transform: capitalize; }
        .ringkas { margin: 4px 0 0; font-size: 9px; }
        .ringkas span { margin-right: 10px; text-transform: capitalize; }
        .kosong { margin-top: 18px; padding: 12px; border: 1px dashed #999; text-align: center; color: #555; }
        table.ttd { margin-top: 28px; }
        table.ttd td { text-align: center; }
        .garis { margin-top: 44px; border-top: 1px solid #111; padding-top: 3px; }
    </style>
</head>
<body>
    <table class="kepala">
        <tr>
            @if ($logo)
                <td class="logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="nama">{{ $aplikasi->nama }} — Absensi Siswa</div>
                <div class="sub">{{ $harian ? 'Harian' : 'Periode' }}: {{ $periode }}</div>
                <div class="sub">Guru: {{ $guru }} · Dicetak {{ $dicetak }}</div>
            </td>
        </tr>
    </table>

    @if (! $harian)
        @forelse ($rekap as $kelas)
            <section class="sesi">
                <h2>Kelas {{ $kelas['kelas'] }} @if ($kelas['kantor']) <span class="meta">({{ $kelas['kantor'] }})</span> @endif</h2>
                <p class="meta">{{ $kelas['hari'] }} hari absensi tercatat pada rentang ini</p>

                <table class="data">
                    <thead>
                        <tr>
                            <th style="width: 26px;" class="angka">#</th>
                            <th style="width: 80px;">NIS</th>
                            <th>Nama</th>
                            @foreach ($statuses as $status)
                                <th style="width: 30px;" class="angka" title="{{ $status }}">{{ Str::upper(Str::substr($status, 0, 1)) }}</th>
                            @endforeach
                            <th style="width: 42px;" class="angka">Total</th>
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

                <table class="data">
                    <thead>
                        <tr>
                            <th style="width: 26px;" class="angka">#</th>
                            <th style="width: 80px;">NIS</th>
                            <th>Nama</th>
                            <th style="width: 70px;">Status</th>
                            <th style="width: 52px;">Datang</th>
                            <th style="width: 130px;">Catatan</th>
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
                    <p class="meta"><em>Catatan sesi: {{ $sesi['catatan'] }}</em></p>
                @endif
            </section>
        @empty
            <p class="kosong">Tidak ada absensi yang tercatat pada rentang ini.</p>
        @endforelse
    @endif

    <table class="ttd">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <div>Guru kelas</div>
                <div class="garis">{{ $guru }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
