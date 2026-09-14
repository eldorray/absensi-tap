<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import AppVersion from '@/components/AppVersion.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();

    const aplikasi = $derived(page.props.aplikasi);
    const nama = $derived(aplikasi?.nama ?? page.props.name);

    // Tiga lapis bukti yang sama dengan halaman depan, diringkas jadi satu
    // baris masing-masing: yang dibaca guru sambil menunggu keyboard muncul.
    const bukti = [
        { ikon: MapPin, teks: 'Di dalam radius sekolah' },
        { ikon: Fingerprint, teks: 'Sidik jari kamu sendiri' },
        { ikon: Smartphone, teks: 'HP yang sudah disetujui' },
    ];
</script>

<div class="bingkai">
    <InstallPrompt />
    <div class="panel">
        <aside class="pita">
            <div class="baris-identitas">
                <Link href={toUrl(home())} class="tautan-identitas">
                    {#if aplikasi?.logo_url}
                        <img
                            src={aplikasi.logo_url}
                            alt=""
                            class="logo-sekolah"
                        />
                    {:else}
                        <span class="lencana"
                            ><AppLogoIcon class="size-5 fill-current" /></span
                        >
                    {/if}
                    <span class="salinan-identitas">
                        <strong>{nama}</strong>
                        <small>Portal Kehadiran</small>
                    </span>
                </Link>
                <span class="aman"><Fingerprint class="size-4" /> Aman</span>
            </div>

            <div class="pesan-pita">
                <p class="nomor">01 / MASUK</p>
                <h2 class="g-display">
                    Satu tap.<br />Tiga bukti.<br />Tercatat.
                </h2>
                <p>
                    Absensi sekolah yang memeriksa kehadiran tanpa memperlambat
                    pagi.
                </p>
            </div>

            <ul class="daftar-bukti">
                {#each bukti as b (b.teks)}
                    <li>
                        <span class="titik">
                            <b.ikon class="size-4" aria-hidden="true" />
                        </span>
                        {b.teks}
                    </li>
                {/each}
            </ul>
        </aside>

        <main class="kartu">
            <div class="w-full max-w-[24rem]">
                <h1 class="g-display judul">{title}</h1>
                {#if description}
                    <p
                        class="mt-3 text-[0.9375rem] leading-relaxed text-muted-foreground"
                    >
                        {description}
                    </p>
                {/if}

                <div class="mt-8">
                    {@render children?.()}
                </div>

                <AppVersion class="mt-10" />
            </div>
        </main>
    </div>
</div>

<style>
    /*
     * Bertumpu HP dulu: di layar sempit halaman ini mengisi layar penuh tanpa
     * kartu mengambang, karena di PWA yang terpasang tidak ada bingkai peramban
     * yang menampung kartu itu. Panel berkartu baru muncul dari 900px.
     */
    .bingkai {
        min-height: 100dvh;
        background: var(--g-band);
        /* Tidak ada alasan halaman ini bergeser mendatar. Tanpa ini, satu anak
           yang kelebaran menggeser seluruh layar dan memotong sisi kirinya. */
        overflow-x: hidden;
    }

    .panel {
        display: grid;
        grid-template-rows: auto 1fr;
        min-height: 100dvh;
    }

    @media (min-width: 900px) {
        .bingkai {
            display: grid;
            place-items: center;
            padding: 2.5rem;
            background:
                radial-gradient(
                    110% 70% at 10% -5%,
                    var(--g-surface) 0%,
                    transparent 55%
                ),
                var(--g-bg);
        }

        .panel {
            grid-template-rows: none;
            grid-template-columns: 0.95fr 1fr;
            min-height: 0;
            width: 100%;
            max-width: 62rem;
            overflow: hidden;
            border-radius: 40px;
            background: var(--card);
            box-shadow: var(--g-shadow);
        }
    }

    /*
     * Pita hijau memikul identitas dan janji produknya, jadi kartu formnya bisa
     * tetap sunyi. Di HP ia menyusut jadi kop tipis: guru membuka halaman ini
     * untuk mengetik, bukan untuk membaca.
     */
    .pita {
        display: flex;
        flex-direction: column;
        /* Anak flex bawaannya min-width: auto, jadi daftar bukti yang panjang
           melebarkan pita melewati lebar layar. */
        min-width: 0;
        gap: 1.25rem;
        /* Notch dan status bar: di PWA standalone tidak ada bilah peramban
           yang menahan konten turun. */
        padding: max(1.25rem, calc(env(safe-area-inset-top) + 0.75rem)) 1.25rem
            2.75rem;
        background: var(--g-band);
        color: var(--g-band-ink);
    }

    .baris-identitas {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-width: 0;
    }
    .logo-sekolah {
        width: 2.75rem;
        height: 2.75rem;
        flex-shrink: 0;
        border-radius: 0.9rem;
        object-fit: contain;
    }
    .salinan-identitas {
        display: grid;
        min-width: 0;
        line-height: 1.1;
    }
    .salinan-identitas strong {
        overflow: hidden;
        font-size: 0.9375rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .salinan-identitas small {
        margin-top: 0.35rem;
        color: var(--g-band-ink-2);
        font: 600 0.625rem var(--font-mono);
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .aman {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-shrink: 0;
        border-radius: 999px;
        background: var(--g-band-field);
        padding: 0.5rem 0.7rem;
        font-size: 0.6875rem;
        font-weight: 700;
    }

    /* Kelas di komponen Link tidak terjangkau CSS terlingkup Svelte. */
    .pita :global(.tautan-identitas) {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        max-width: 100%;
        font-size: 0.9375rem;
        color: inherit;
        text-decoration: none;
    }

    .lencana {
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        flex-shrink: 0;
        border-radius: 0.875rem;
        background: var(--g-band-field);
    }

    .pesan-pita {
        display: none;
    }

    .daftar-bukti {
        display: none;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 0;
        padding: 0;
        min-width: 0;
        list-style: none;
        font-size: 0.8125rem;
        color: var(--g-band-ink);
    }

    /* Di HP ketiganya jadi pil yang membungkus, bukan baris yang digeser:
       yang tergeser tidak pernah dibaca dan ujungnya terlihat terpotong. */
    .daftar-bukti li {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        background: var(--g-band-field);
        padding: 0.4375rem 0.75rem 0.4375rem 0.5rem;
    }

    .titik {
        display: grid;
        place-items: center;
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
        color: var(--g-band-ink);
    }

    @media (min-width: 900px) {
        .pita {
            min-height: 40rem;
            padding: 2.5rem;
        }

        .aman {
            display: none;
        }

        .pesan-pita {
            display: block;
            margin-block: auto;
            max-width: 24rem;
        }

        .pesan-pita .nomor {
            color: var(--g-band-ink-2);
            font: 700 0.7rem var(--font-mono);
            letter-spacing: 0.12em;
        }

        .pesan-pita h2 {
            margin-top: 1.25rem;
            font-size: clamp(2.5rem, 4.5vw, 4rem);
        }

        .pesan-pita > p:last-child {
            max-width: 30ch;
            margin-top: 1.5rem;
            color: var(--g-band-ink-2);
            line-height: 1.65;
        }

        .daftar-bukti {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.875rem;
            font-size: 0.9375rem;
        }

        .daftar-bukti li {
            background: transparent;
            padding: 0;
            gap: 0.75rem;
        }

        .titik {
            width: 2rem;
            height: 2rem;
            background: var(--g-band-field);
            border-radius: 999px;
        }
    }

    .kartu {
        display: grid;
        align-content: start;
        justify-items: stretch;
        padding: 2.25rem 1.25rem
            max(1.5rem, calc(env(safe-area-inset-bottom) + 1rem));
        background: var(--g-bg);
        /* Lembar form naik menutup sudut pita, pola yang sama dengan bottom
           sheet di aplikasinya. */
        border-radius: 32px 32px 0 0;
        margin-top: -1.25rem;
    }

    @media (min-width: 900px) {
        .kartu {
            place-content: center;
            justify-items: center;
            padding: clamp(2.5rem, 4vw, 3.25rem);
            border-radius: 0;
            margin-top: 0;
        }
    }

    .judul {
        font-size: clamp(2rem, 8vw, 2.35rem);
        margin: 0;
    }

    @media (min-width: 420px) and (max-width: 899px) {
        .kartu {
            padding-inline: max(1.5rem, calc((100vw - 26rem) / 2));
        }
    }
</style>
