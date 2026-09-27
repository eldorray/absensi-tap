/**
 * Percobaan001: letupan konfeti seperti ulang tahun dari titik (x, y) layar.
 *
 * Partikel ditempel ke <body> dengan posisi fixed, bukan ke tombol: setelah
 * absen berhasil halaman memuat ulang props dan tombolnya bisa hilang, jadi
 * letupan tidak boleh ikut terhapus bersamanya.
 *
 * ponytail: DOM + Web Animations API, tanpa pustaka konfeti. 40 partikel
 * ringan untuk HP; ganti ke canvas kalau suatu saat butuh ratusan.
 */
const WARNA = [
    '#b6e26a',
    '#2f6d21',
    '#f2c94c',
    '#f2994a',
    '#eb5757',
    '#56ccf2',
];
const JUMLAH = 40;

export function letupan(x: number, y: number): void {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    for (let i = 0; i < JUMLAH; i++) {
        const partikel = document.createElement('span');
        const ukuran = 6 + Math.random() * 6;
        const bulat = Math.random() < 0.3;

        Object.assign(partikel.style, {
            position: 'fixed',
            left: `${x}px`,
            top: `${y}px`,
            width: `${ukuran}px`,
            height: `${bulat ? ukuran : ukuran * 0.45}px`,
            borderRadius: bulat ? '50%' : '2px',
            background: WARNA[i % WARNA.length],
            pointerEvents: 'none',
            zIndex: '60',
        });
        partikel.setAttribute('aria-hidden', 'true');
        document.body.appendChild(partikel);

        // Menyebar ke atas dalam kipas, lalu jatuh karena "gravitasi".
        const sudut = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.1;
        const jarak = 90 + Math.random() * 150;
        const dx = Math.cos(sudut) * jarak;
        const dy = Math.sin(sudut) * jarak;
        const putar = (Math.random() - 0.5) * 1080;
        const durasi = 900 + Math.random() * 600;

        partikel
            .animate(
                [
                    {
                        transform: 'translate(-50%, -50%) scale(0.6)',
                        opacity: 1,
                    },
                    {
                        transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) rotate(${putar / 2}deg) scale(1)`,
                        opacity: 1,
                        offset: 0.45,
                    },
                    {
                        transform: `translate(calc(-50% + ${dx * 1.2}px), calc(-50% + ${dy + 220}px)) rotate(${putar}deg) scale(0.9)`,
                        opacity: 0,
                    },
                ],
                { duration: durasi, easing: 'cubic-bezier(0.2, 0.7, 0.3, 1)' },
            )
            .addEventListener('finish', () => partikel.remove());
    }
}
