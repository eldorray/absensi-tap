<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login } from '@/routes';

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
    <header
        class="safe-top mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-5 pt-5 pb-5 sm:px-8"
    >
        <div class="flex min-w-0 items-center gap-3">
            {#if aplikasi?.logo_url}
                <img
                    src={aplikasi.logo_url}
                    alt=""
                    class="h-10 w-auto max-w-32 object-contain"
                />
            {:else}
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground"
                >
                    <Fingerprint class="size-5" aria-hidden="true" />
                </span>
            {/if}
            <span class="truncate text-[0.9375rem] font-semibold">{nama}</span>
        </div>

        <Link
            href={toUrl(auth.user ? dashboard() : login())}
            class="tautan-atas"
        >
            {auth.user ? 'Buka aplikasi' : 'Masuk'}
        </Link>
    </header>

    <main class="mx-auto w-full max-w-5xl px-5 pb-8 sm:px-8 sm:pb-16">
        <section class="pt-6 pb-14 sm:pt-14 sm:pb-20">
            <h1 class="g-display text-[clamp(2.25rem,7.5vw,4.25rem)]">
                Absen dari halaman sekolah,<br class="hidden sm:block" /> bukan dari
                mana saja.
            </h1>
            <p
                class="ukuran-baca mt-5 text-[1.0625rem] leading-relaxed text-muted-foreground"
            >
                Kehadiran tercatat lewat tiga bukti sekaligus: kamu ada di dalam
                radius sekolah, sidik jarimu yang menekan, dan HP-mu sendiri
                yang terdaftar. Butuh sepuluh detik, dua kali sehari.
            </p>

            <div class="mt-9 flex flex-wrap items-center gap-x-5 gap-y-3">
                <Link
                    href={toUrl(auth.user ? dashboard() : login())}
                    class="tombol-utama"
                >
                    <span class="denyut" aria-hidden="true"></span>
                    <span class="relative flex items-center gap-2">
                        {auth.user ? 'Buka aplikasi' : 'Masuk untuk absen'}
                        <ArrowRight class="size-5" aria-hidden="true" />
                    </span>
                </Link>
                <p class="text-sm text-muted-foreground">
                    Belum punya akun? Akun dibuatkan TU sekolah.
                </p>
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

    /* Ukuran baca dijaga di bawah 75ch supaya barisnya tidak melelahkan. */
    .ukuran-baca {
        max-width: 62ch;
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
