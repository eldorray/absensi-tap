export type Pengumuman = {
    id: number;
    judul: string;
    isi: string;
    dibuat: string | null;
};

/**
 * Penanda "Baru" hanya kenyamanan per HP, jadi cukup localStorage: kalau
 * tidak tersedia (mode privat), pengumuman tetap tampil tanpa penanda.
 */
const KUNCI_TERAKHIR_DIBACA = 'pengumuman.terakhir-dibaca';
const TIGA_HARI = 3 * 24 * 60 * 60 * 1000;

export function bacaTerakhirDibaca(): number | null {
    try {
        const nilai = localStorage.getItem(KUNCI_TERAKHIR_DIBACA);

        return nilai === null ? null : Number(nilai);
    } catch {
        return null;
    }
}

/** Ingat pengumuman terbaru yang sudah terlihat; tidak pernah mundur. */
export function tandaiTerbaca(pengumumans: Pengumuman[]): void {
    const terbaru = pengumumans[0]?.dibuat;

    if (!terbaru) {
        return;
    }

    try {
        const waktu = new Date(terbaru).getTime();

        if (waktu > (bacaTerakhirDibaca() ?? 0)) {
            localStorage.setItem(KUNCI_TERAKHIR_DIBACA, String(waktu));
        }
    } catch {
        // Penyimpanan diblokir: penanda "Baru" saja yang tidak diingat.
    }
}

/**
 * Kunjungan pertama (belum ada catatan): yang tiga hari terakhir dianggap baru.
 */
export function pembacaBaru(
    terakhirDibaca: number | null = bacaTerakhirDibaca(),
    sekarang: number = Date.now(),
): (pengumuman: Pengumuman) => boolean {
    return (pengumuman) => {
        if (pengumuman.dibuat === null) {
            return false;
        }

        const waktu = new Date(pengumuman.dibuat).getTime();

        return terakhirDibaca === null
            ? sekarang - waktu < TIGA_HARI
            : waktu > terakhirDibaca;
    };
}

const formatJam = new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
});
const formatTanggal = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
const formatTanggalLengkap = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function labelWaktu(iso: string | null): string {
    if (iso === null) {
        return '';
    }

    const waktu = new Date(iso);
    const hariIni = new Date();
    const selisihHari = Math.round(
        (new Date(hariIni.toDateString()).getTime() -
            new Date(waktu.toDateString()).getTime()) /
            86_400_000,
    );

    if (selisihHari === 0) {
        return `Hari ini, ${formatJam.format(waktu)}`;
    }

    if (selisihHari === 1) {
        return `Kemarin, ${formatJam.format(waktu)}`;
    }

    return waktu.getFullYear() === hariIni.getFullYear()
        ? formatTanggal.format(waktu)
        : formatTanggalLengkap.format(waktu);
}

/**
 * Warna sampul kartu. Diambil dari id supaya satu pengumuman selalu
 * berwarna sama, dan kartu yang bersebelahan jarang kembar.
 */
const SAMPUL = [
    'bg-[var(--g-blue-c)] text-[var(--g-blue-ink-2)]',
    'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink-2)]',
    'bg-[var(--g-green-c)] text-[var(--g-green-ink-2)]',
    'bg-[var(--g-red-c)] text-[var(--g-red-ink-2)]',
] as const;

export function warnaSampul(id: number): string {
    return SAMPUL[id % SAMPUL.length];
}
