<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import Download from 'lucide-svelte/icons/download';
    import Share from 'lucide-svelte/icons/share';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import X from 'lucide-svelte/icons/x';

    type PromptInstall = Event & {
        prompt: () => Promise<void>;
        userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
    };

    let terpasang = $state(true);
    let promptAndroid = $state<PromptInstall | null>(null);
    let iOS = $state(false);
    let android = $state(false);
    let ditutup = $state(true);
    let langkahIphone = $state(false);

    $effect(() => {
        terpasang =
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone ===
                true;
        iOS =
            /iphone|ipad|ipod/i.test(navigator.userAgent) &&
            !('onbeforeinstallprompt' in window);
        android = /android/i.test(navigator.userAgent);
        ditutup = false;

        const tangkap = (event: Event) => {
            event.preventDefault();
            promptAndroid = event as PromptInstall;
        };
        const selesai = () => (terpasang = true);

        window.addEventListener('beforeinstallprompt', tangkap);
        window.addEventListener('appinstalled', selesai);

        return () => {
            window.removeEventListener('beforeinstallprompt', tangkap);
            window.removeEventListener('appinstalled', selesai);
        };
    });

    const tampil = $derived(!terpasang && !ditutup);

    function tutup(): void {
        ditutup = true;
    }

    async function pasangAndroid(): Promise<void> {
        if (!promptAndroid) {
            return;
        }

        await promptAndroid.prompt();
        const pilihan = await promptAndroid.userChoice;
        promptAndroid = null;

        if (pilihan.outcome === 'accepted') {
            terpasang = true;
        }
    }
</script>

{#if tampil}
    <aside class="install-banner" aria-label="Pasang aplikasi absensi">
        <button
            type="button"
            class="tutup"
            onclick={tutup}
            aria-label="Tutup banner instalasi"
        >
            <X class="size-5" aria-hidden="true" />
        </button>

        <span class="ikon-app"
            ><Smartphone class="size-6" aria-hidden="true" /></span
        >
        <div class="salinan">
            <span class="label">APLIKASI SEKOLAH</span>
            <h2>
                {iOS
                    ? 'Tambahkan di iPhone'
                    : android
                      ? 'Pasang di Android'
                      : 'Pasang aplikasi'}
            </h2>
            <p>Buka absensi lebih cepat dari layar utama, tanpa address bar.</p>
        </div>

        {#if promptAndroid}
            <button type="button" class="aksi" onclick={pasangAndroid}>
                <Download class="size-4" aria-hidden="true" /> Pasang sekarang
            </button>
        {:else if iOS}
            <button
                type="button"
                class="aksi"
                onclick={() => (langkahIphone = !langkahIphone)}
                aria-expanded={langkahIphone}
            >
                <Share class="size-4" aria-hidden="true" /> Lihat caranya
            </button>
        {:else}
            <p class="petunjuk-android">
                {android
                    ? 'Buka menu browser ⋮ lalu pilih Pasang aplikasi atau Tambahkan ke layar utama.'
                    : 'Buka halaman ini dari Chrome Android atau Safari iPhone untuk memasang aplikasi.'}
            </p>
        {/if}

        {#if iOS && langkahIphone}
            <ol class="langkah">
                <li>
                    <span>1</span>Ketuk <Share
                        class="size-4"
                        aria-hidden="true"
                    /> <strong>Bagikan</strong> di Safari.
                </li>
                <li>
                    <span>2</span>Pilih
                    <strong>Tambahkan ke Layar Utama</strong>.
                </li>
                <li>
                    <span><Check class="size-4" /></span>Ketuk
                    <strong>Tambah</strong>.
                </li>
            </ol>
        {/if}
    </aside>
{/if}

<style>
    .install-banner {
        position: fixed;
        z-index: 60;
        right: 1rem;
        bottom: max(1rem, env(safe-area-inset-bottom));
        left: 1rem;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 0.85rem;
        max-width: 30rem;
        margin-inline: auto;
        border: 1px solid color-mix(in srgb, var(--g-line) 65%, transparent);
        border-radius: 1.6rem;
        background: color-mix(in srgb, var(--g-bg) 94%, transparent);
        padding: 1rem;
        box-shadow: 0 20px 60px -22px rgba(16, 48, 12, 0.48);
        color: var(--g-ink);
        backdrop-filter: blur(18px);
    }
    .ikon-app {
        display: grid;
        place-items: center;
        width: 3rem;
        height: 3rem;
        border-radius: 1rem;
        background: var(--g-blue);
        color: var(--g-on-blue);
    }
    .salinan {
        min-width: 0;
        padding-right: 1.8rem;
    }
    .label {
        color: var(--g-blue-ink-2);
        font: 700 0.625rem var(--font-mono);
        letter-spacing: 0.1em;
    }
    h2 {
        margin-top: 0.2rem;
        font-size: 1.05rem;
        font-weight: 750;
        letter-spacing: -0.015em;
    }
    p {
        margin-top: 0.25rem;
        color: var(--g-ink-2);
        font-size: 0.8125rem;
        line-height: 1.45;
    }
    .tutup {
        position: absolute;
        top: 0.75rem;
        right: 0.75rem;
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        color: var(--g-ink-2);
    }
    .aksi {
        grid-column: 1 / -1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 3.25rem;
        border-radius: 1rem;
        background: var(--g-blue);
        color: var(--g-on-blue);
        font-size: 0.875rem;
        font-weight: 750;
    }
    .petunjuk-android {
        grid-column: 1 / -1;
        margin: 0;
        border-radius: 1rem;
        background: var(--g-surface);
        padding: 0.8rem 1rem;
        color: var(--g-ink-2);
        font-size: 0.8125rem;
        line-height: 1.5;
    }
    .langkah {
        grid-column: 1 / -1;
        display: grid;
        gap: 0.65rem;
        margin: 0;
        border-radius: 1.1rem;
        background: var(--g-surface);
        padding: 0.85rem;
        list-style: none;
        color: var(--g-ink-2);
        font-size: 0.8125rem;
    }
    .langkah li {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .langkah li > span {
        display: grid;
        place-items: center;
        width: 1.5rem;
        height: 1.5rem;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--g-blue-c);
        color: var(--g-blue-ink);
        font: 700 0.6875rem var(--font-mono);
    }
    @media (min-width: 700px) {
        .install-banner {
            right: 1.5rem;
            bottom: 1.5rem;
            left: auto;
            margin: 0;
        }
    }
    @media (display-mode: standalone) {
        .install-banner {
            display: none;
        }
    }
</style>
