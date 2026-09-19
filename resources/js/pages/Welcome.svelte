<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Check from 'lucide-svelte/icons/check';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import AppHead from '@/components/AppHead.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import ThemeToggle from '@/components/ThemeToggle.svelte';
    import { toUrl } from '@/lib/utils';
    import { aplikasi as bukaAplikasi, home, login } from '@/routes';

    const auth = $derived(page.props.auth);
    const aplikasi = $derived(page.props.aplikasi);
    const nama = $derived(aplikasi?.nama ?? page.props.name);

    const syarat = [
        {
            ikon: MapPin,
            judul: 'Berada di halaman sekolah',
            isi: 'Tap hanya diterima di dalam radius titik absen. Jaraknya terlihat di layar sebelum kamu menekan apa pun.',
        },
        {
            ikon: Fingerprint,
            judul: 'Sidik jari kamu sendiri',
            isi: 'Verifikasi biometrik HP berjalan tepat sebelum tap terkirim, jadi kehadiran terikat ke orangnya, bukan ke perangkatnya.',
        },
        {
            ikon: Smartphone,
            judul: 'Satu HP yang sudah disetujui',
            isi: 'HP pertama langsung aktif. Ganti HP perlu persetujuan TU, dan HP lama otomatis dicabut.',
        },
    ];

    const langkah = [
        {
            judul: 'Buka di halaman sekolah',
            isi: 'Aplikasi membaca lokasi dan menampilkan jarak kamu ke titik absen.',
        },
        {
            judul: 'Tap masuk',
            isi: 'Sidik jari memastikan itu kamu, lalu jam masuk tercatat beserta statusnya.',
        },
        {
            judul: 'Tap pulang',
            isi: 'Sebelum meninggalkan sekolah. Rekap harian dan bulanan tersusun sendiri.',
        },
    ];
</script>

<AppHead title="Masuk" />

<div class="halaman">
    <InstallPrompt />
    <header class="safe-top site-header">
        <Link href={toUrl(home())} class="brand-lockup" aria-label={nama}>
            {#if aplikasi?.logo_url}
                <img src={aplikasi.logo_url} alt={nama} class="brand-logo" />
            {:else}
                <span class="brand-mark">
                    <Fingerprint class="size-5" aria-hidden="true" />
                </span>
            {/if}
            <span class="brand-copy">
                <strong>{nama}</strong>
                <small>Sistem Kehadiran</small>
            </span>
        </Link>

        <div class="header-actions">
            <ThemeToggle />
            <Link
                href={toUrl(auth.user ? bukaAplikasi() : login())}
                class="header-action"
            >
                <span>{auth.user ? 'Buka aplikasi' : 'Masuk'}</span>
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl px-5 pb-8 sm:px-8 sm:pb-16">
        <section class="hero pt-6 pb-14 sm:pt-14 sm:pb-20">
            <div class="hero-copy">
                <p class="eyebrow">
                    <span class="status-dot"></span>Siap untuk tap masuk dan
                    pulang
                </p>
                <h1 class="g-display mt-5 text-[clamp(2.5rem,7.5vw,4.75rem)]">
                    Kehadiran yang<br class="hidden sm:block" /> benar-benar hadir.
                </h1>
                <p
                    class="ukuran-baca mt-5 text-[1.0625rem] leading-relaxed text-muted-foreground"
                >
                    Absen langsung dari halaman sekolah. Lokasi, sidik jari, dan
                    perangkatmu diperiksa dalam satu tap yang cepat.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-x-5 gap-y-3">
                    <Link
                        href={toUrl(auth.user ? bukaAplikasi() : login())}
                        class="tombol-utama"
                    >
                        <span class="denyut" aria-hidden="true"></span>
                        <span class="relative flex items-center gap-2">
                            {auth.user ? 'Buka aplikasi' : 'Masuk untuk absen'}
                            <ArrowRight class="size-5" aria-hidden="true" />
                        </span>
                    </Link>
                    <p class="text-sm text-muted-foreground">
                        Akun disiapkan oleh TU sekolah
                    </p>
                </div>
            </div>

            <div
                class="visual-tap"
                aria-label="Lokasi, biometrik, dan perangkat siap"
            >
                <div class="orbit orbit-luar"></div>
                <div class="orbit orbit-dalam"></div>
                <div class="tap-core">
                    <span class="tap-check"
                        ><Check class="size-7" strokeWidth={3} /></span
                    >
                    <strong>Siap absen</strong><span>Lokasi terdeteksi</span>
                </div>
                <span class="proof proof-lokasi"
                    ><MapPin class="size-4" /> Lokasi</span
                >
                <span class="proof proof-bio"
                    ><Fingerprint class="size-4" /> Biometrik</span
                >
                <span class="proof proof-hp"
                    ><Smartphone class="size-4" /> Perangkat</span
                >
                <p class="visual-caption">Lokasi • Biometrik • Perangkat</p>
            </div>
        </section>

        <section class="g-tile g-tone-plain gap-0 p-0 sm:gap-0">
            <h2
                class="g-display border-b border-border px-6 py-5 text-[1.375rem] sm:px-9"
            >
                Tap diterima kalau tiga hal ini benar
            </h2>

            <ul>
                {#each syarat as s, i (s.judul)}
                    <li
                        class="flex items-start gap-4 px-6 py-6 sm:px-9 {i > 0
                            ? 'border-t border-border'
                            : ''}"
                    >
                        <span class="chip">
                            <s.ikon class="size-5" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-[1.0625rem] font-bold">
                                {s.judul}
                            </h3>
                            <p
                                class="ukuran-baca mt-1.5 text-[0.9375rem] leading-relaxed text-muted-foreground"
                            >
                                {s.isi}
                            </p>
                        </div>
                    </li>
                {/each}
            </ul>
        </section>

        <section
            class="mt-4 rounded-[32px] bg-primary px-6 py-9 text-primary-foreground sm:px-9 sm:py-11"
        >
            <h2 class="g-display text-[1.375rem]">Sehari-harinya begini</h2>

            <ol class="mt-7 grid gap-7 sm:grid-cols-3 sm:gap-6">
                {#each langkah as l, i (l.judul)}
                    <li class="langkah">
                        <span class="urutan">{i + 1}</span>
                        <h3 class="mt-4 font-bold">{l.judul}</h3>
                        <p
                            class="isi-langkah mt-1.5 text-[0.9375rem] leading-relaxed"
                        >
                            {l.isi}
                        </p>
                    </li>
                {/each}
            </ol>
        </section>

        <section
            class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-[28px] border border-border px-6 py-6 sm:px-9"
        >
            <p class="ukuran-baca text-[0.9375rem] text-muted-foreground">
                Admin dan TU masuk lewat pintu yang sama, lalu mengurus jadwal,
                izin, rekap, dan persetujuan HP dari dalam aplikasi.
            </p>
            <Link href={toUrl(login())} class="tautan-atas">Masuk</Link>
        </section>
    </main>

    <footer
        class="safe-bottom mx-auto w-full max-w-5xl px-5 pt-2 text-sm text-muted-foreground sm:px-8"
    >
        {nama}
    </footer>
</div>

<style>
    .halaman {
        /* dvh, bukan vh: bilah alamat Android muncul-hilang dan memotong
           halaman kalau tingginya dikunci ke vh. */
        min-height: 100dvh;
        background:
            radial-gradient(
                120% 80% at 8% -10%,
                var(--g-surface) 0%,
                transparent 60%
            ),
            var(--g-bg);
        color: var(--g-ink);
    }

    .site-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: calc(100% - 2rem);
        max-width: 64rem;
        min-height: 4.75rem;
        margin: 1rem auto 0;
        padding: 0.7rem 0.75rem 0.7rem 1rem;
        border: 1px solid color-mix(in srgb, var(--g-line) 78%, transparent);
        border-radius: 1.35rem;
        background: color-mix(in srgb, var(--g-bg) 88%, transparent);
        box-shadow: 0 12px 36px -28px
            color-mix(in srgb, var(--g-ink) 45%, transparent);
        backdrop-filter: blur(14px);
    }

    .halaman :global(.brand-lockup) {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
        color: inherit;
        text-decoration: none;
    }
    .brand-logo {
        width: 2.85rem;
        height: 2.85rem;
        flex-shrink: 0;
        border-radius: 0.9rem;
        object-fit: contain;
    }
    .brand-mark {
        display: grid;
        place-items: center;
        width: 2.85rem;
        height: 2.85rem;
        flex-shrink: 0;
        border-radius: 0.9rem;
        background: var(--g-blue);
        color: var(--g-on-blue);
    }
    .brand-copy {
        display: grid;
        min-width: 0;
        line-height: 1.15;
    }
    .brand-copy strong {
        overflow: hidden;
        font-size: 0.9375rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .brand-copy small {
        margin-top: 0.3rem;
        color: var(--g-ink-2);
        font: 600 0.625rem var(--font-mono);
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .halaman :global(.header-action) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 2.85rem;
        flex-shrink: 0;
        border-radius: 0.95rem;
        background: var(--g-blue);
        padding: 0.7rem 1rem;
        color: var(--g-on-blue);
        font-size: 0.875rem;
        font-weight: 700;
        text-decoration: none;
        transition: transform 0.2s var(--g-emphasized);
    }
    .halaman :global(.header-action:hover) {
        transform: translateY(-1px);
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    @media (min-width: 640px) {
        .site-header {
            width: calc(100% - 4rem);
            margin-top: 1.5rem;
            padding-inline: 1.2rem 0.8rem;
        }
        .brand-copy strong {
            font-size: 1rem;
        }
        .halaman :global(.header-action) {
            padding-inline: 1.15rem;
        }
    }

    /* Ukuran baca dijaga di bawah 75ch supaya barisnya tidak melelahkan. */
    .ukuran-baca {
        max-width: 62ch;
    }

    .hero {
        display: grid;
        align-items: center;
        gap: 3rem;
    }
    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        width: fit-content;
        border-radius: 999px;
        background: var(--g-blue-c);
        padding: 0.55rem 0.9rem;
        color: var(--g-blue-ink);
        font-size: 0.8125rem;
        font-weight: 700;
    }
    .status-dot {
        width: 0.55rem;
        height: 0.55rem;
        border-radius: 999px;
        background: var(--g-blue);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--g-blue) 14%, transparent);
    }
    .visual-tap {
        position: relative;
        display: grid;
        place-items: center;
        width: min(100%, 25rem);
        aspect-ratio: 1;
        margin-inline: auto;
    }
    .orbit {
        position: absolute;
        border: 1px solid color-mix(in srgb, var(--g-blue) 24%, transparent);
        border-radius: 50%;
    }
    .orbit-luar {
        inset: 4%;
    }
    .orbit-dalam {
        inset: 18%;
        background: color-mix(in srgb, var(--g-blue-c) 45%, transparent);
    }
    .tap-core {
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 9.75rem;
        height: 9.75rem;
        border-radius: 50%;
        background: var(--g-blue);
        color: var(--g-on-blue);
        box-shadow: 0 20px 50px -20px
            color-mix(in srgb, var(--g-blue) 70%, transparent);
    }
    .tap-core strong {
        margin-top: 0.65rem;
        font-size: 1.05rem;
    }
    .tap-core > span:last-child {
        margin-top: 0.2rem;
        color: var(--g-band-ink-2);
        font-size: 0.75rem;
    }
    .tap-check {
        display: grid;
        place-items: center;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: var(--g-band-field);
    }
    .proof {
        position: absolute;
        z-index: 3;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        border: 1px solid var(--g-line);
        border-radius: 999px;
        background: var(--g-bg);
        padding: 0.6rem 0.8rem;
        box-shadow: var(--g-shadow);
        color: var(--g-blue-ink);
        font-size: 0.75rem;
        font-weight: 700;
    }
    .proof-lokasi {
        top: 17%;
        left: 2%;
    }
    .proof-bio {
        top: 23%;
        right: 0;
    }
    .proof-hp {
        bottom: 18%;
        right: 7%;
    }
    .visual-caption {
        position: absolute;
        bottom: 4%;
        color: var(--g-ink-2);
        font: 600 0.6875rem var(--font-mono);
        letter-spacing: 0.04em;
    }
    @media (min-width: 800px) {
        .hero {
            grid-template-columns: minmax(0, 1.18fr) minmax(19rem, 0.82fr);
            min-height: 34rem;
        }
    }

    /*
     * Kelas ini menempel di komponen Link, dan CSS terlingkup Svelte tidak
     * menjangkau elemen yang dirender komponen lain. Dibungkus :global di bawah
     * .halaman supaya tetap terbatas pada halaman ini.
     */
    .halaman :global(.tautan-atas) {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        border: 1px solid var(--g-line);
        padding: 0.5rem 1.125rem;
        font-size: 0.9375rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--g-ink);
        transition:
            background-color 0.2s var(--g-emphasized),
            border-color 0.2s var(--g-emphasized);
    }

    .halaman :global(.tautan-atas:hover) {
        background: var(--g-surface);
        border-color: var(--g-blue);
    }

    .halaman :global(.tombol-utama) {
        position: relative;
        display: inline-flex;
        justify-content: center;
        width: 100%;
        align-items: center;
        isolation: isolate;
        border-radius: 999px;
        background: var(--g-blue);
        color: var(--g-on-blue);
        padding: 1.0625rem 1.75rem;
        font-size: 1.0625rem;
        font-weight: 700;
        text-decoration: none;
        box-shadow: var(--g-shadow);
        transition: transform 0.2s var(--g-emphasized);
    }

    @media (min-width: 640px) {
        .halaman :global(.tombol-utama) {
            width: auto;
        }
    }

    .halaman :global(.tombol-utama:hover) {
        transform: translateY(-1px);
    }

    .halaman :global(.tombol-utama:active) {
        transform: scale(0.98);
    }

    /*
     * Satu-satunya momen gerak di halaman ini: satu lingkaran melebar dari
     * tombol, meniru radius geofence yang jadi mekanisme produknya. Sekali
     * jalan, bukan gelung, supaya tidak menyita perhatian dari tombolnya.
     */
    .halaman :global(.denyut) {
        position: absolute;
        inset: 0;
        z-index: -1;
        border-radius: inherit;
        border: 2px solid var(--g-blue);
        opacity: 0;
        animation: denyut 1.6s cubic-bezier(0.2, 0, 0, 1) 0.35s 1 forwards;
    }

    @keyframes denyut {
        0% {
            opacity: 0.55;
            transform: scale(1);
        }
        100% {
            opacity: 0;
            transform: scale(1.45);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .halaman :global(.denyut) {
            animation: none;
        }

        .halaman :global(.tombol-utama:hover) {
            transform: none;
        }
    }

    .chip {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 1rem;
        background: var(--g-blue-c);
        color: var(--g-blue-ink);
    }

    .langkah {
        position: relative;
    }

    /*
     * Tinta sekunder di atas pita hijau diambil dari keluarga warnanya
     * (--g-band-ink-2), bukan putih transparan: putih 75% jatuh ke sekitar
     * ambang kontras 4.5:1 di ukuran teks ini.
     */
    .isi-langkah {
        color: var(--g-band-ink-2);
    }

    .urutan {
        display: grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        background: var(--g-band-field);
        font-family: var(--font-mono);
        font-size: 0.875rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--g-band-ink);
    }

    /* Rel penghubung antar langkah, hanya saat ketiganya sebaris. */
    @media (min-width: 640px) {
        .langkah:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 1rem;
            left: 2.75rem;
            right: -1.5rem;
            height: 1px;
            background: var(--g-band-field);
        }
    }
</style>
