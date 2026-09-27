export type JadwalHariIni = {
    jam_masuk: string;
    jam_pulang: string;
    buka_masuk: string;
    tutup_masuk: string;
    buka_pulang: string;
    is_hari_kerja: boolean;
};

export type StatusJendela =
    | { bisa: true; konfirmasiPulangCepat: boolean }
    | { bisa: false; judul: string; pesan: string; sarankanIzin?: boolean };

/**
 * Jam dinding server, dalam menit sejak tengah malam.
 *
 * Dihitung dari selisih jam HP dengan waktuServer saat halaman dimuat, dan
 * dibaca dalam zona waktu server (offset di string ISO-nya). Jadi guru di
 * zona lain atau dengan jam HP yang meleset tetap melihat jendela yang sama
 * dengan yang dinilai server.
 */
export function jamServer(
    waktuServer: string,
    selisihMs: number,
    sekarangMs: number = Date.now(),
): number {
    const cocok = /([+-])(\d{2}):(\d{2})$/.exec(waktuServer);
    const offsetMenit = cocok
        ? (cocok[1] === '-' ? -1 : 1) *
          (Number(cocok[2]) * 60 + Number(cocok[3]))
        : 0;
    const menitUtc = Math.floor((sekarangMs + selisihMs) / 60_000);

    return (((menitUtc + offsetMenit) % 1440) + 1440) % 1440;
}

function menit(jam: string): number {
    const [h, m] = jam.split(':').map(Number);

    return h * 60 + m;
}

/**
 * Apakah tombol absen boleh ditekan sekarang, dan kalau tidak, kenapa.
 *
 * Cermin aturan CatatAbsensi::diLuarJendela di server -- server tetap
 * penentu akhir; ini supaya guru tidak menunggu GPS dan sidik jari hanya
 * untuk ditolak.
 */
export function statusJendela(
    jadwal: JadwalHariIni | null,
    libur: string | null,
    sudahMasuk: boolean,
    sekarang: number,
): StatusJendela {
    if (libur) {
        return {
            bisa: false,
            judul: 'Hari ini libur',
            pesan: `${libur}. Tidak perlu absen.`,
        };
    }

    if (jadwal === null) {
        return { bisa: true, konfirmasiPulangCepat: false };
    }

    if (!jadwal.is_hari_kerja) {
        return {
            bisa: false,
            judul: 'Bukan hari kerja',
            pesan: 'Hari ini bukan hari kerjamu, jadi tidak perlu absen.',
        };
    }

    if (!sudahMasuk) {
        if (sekarang < menit(jadwal.buka_masuk)) {
            return {
                bisa: false,
                judul: 'Absen masuk belum dibuka',
                pesan: `Absen masuk dibuka pukul ${jadwal.buka_masuk}.`,
            };
        }

        // >= karena server menolak begitu lewat detik ke-0 menit tutup.
        if (sekarang >= menit(jadwal.tutup_masuk)) {
            return {
                bisa: false,
                judul: 'Absen masuk sudah ditutup',
                pesan: `Ditutup pukul ${jadwal.tutup_masuk}. Kalau kamu hadir, lapor ke TU. Kalau berhalangan, ajukan izin.`,
                sarankanIzin: true,
            };
        }

        return { bisa: true, konfirmasiPulangCepat: false };
    }

    if (sekarang < menit(jadwal.buka_pulang)) {
        return {
            bisa: false,
            judul: 'Absen pulang belum dibuka',
            pesan: `Absen pulang dibuka pukul ${jadwal.buka_pulang}.`,
        };
    }

    return {
        bisa: true,
        konfirmasiPulangCepat: sekarang < menit(jadwal.jam_pulang),
    };
}
