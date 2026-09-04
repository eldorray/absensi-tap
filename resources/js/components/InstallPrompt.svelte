<script lang="ts">
    import Share from 'lucide-svelte/icons/share';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import X from 'lucide-svelte/icons/x';
    import { Button } from '@/components/ui/button';

    type PromptInstall = Event & {
        prompt: () => Promise<void>;
        userChoice: Promise<{ outcome: string }>;
    };

    let terpasang = $state(true);
    let promptAndroid = $state<PromptInstall | null>(null);
    let iOS = $state(false);
    let ditutup = $state(false);

    $effect(() => {
        // Sudah berjalan sebagai app terpasang? Tidak perlu banner.
        const standalone =
            window.matchMedia('(display-mode: standalone)').matches ||
            (navigator as Navigator & { standalone?: boolean }).standalone ===
                true;

        terpasang = standalone;

        iOS =
            /iphone|ipad|ipod/i.test(navigator.userAgent) &&
            !('onbeforeinstallprompt' in window);

        const tangkap = (event: Event) => {
            event.preventDefault();
            promptAndroid = event as PromptInstall;
        };

        window.addEventListener('beforeinstallprompt', tangkap);

        return () => window.removeEventListener('beforeinstallprompt', tangkap);
    });

    const tampil = $derived(
        !terpasang && !ditutup && (promptAndroid !== null || iOS),
    );

    async function pasang(): Promise<void> {
        if (!promptAndroid) {
            return;
        }

        await promptAndroid.prompt();
        promptAndroid = null;
    }
</script>

{#if tampil}
    <!-- Selama dibuka lewat tab browser, address bar tetap ada dan start_url tidak
         berlaku. Tanpa banner ini sebagian guru tidak akan pernah memasangnya. -->
    <aside class="g-tile g-tone-blue gap-3">
        <div class="flex items-start justify-between gap-3">
            <h3 class="flex items-center gap-2">
                <Smartphone class="size-5" aria-hidden="true" />
                Pasang aplikasi
            </h3>
            <button
                type="button"
                onclick={() => (ditutup = true)}
                aria-label="Tutup"
                class="opacity-70"
            >
                <X class="size-4" aria-hidden="true" />
            </button>
        </div>

        {#if promptAndroid}
            <p>
                Pasang ke layar utama supaya absen bisa dibuka sekali ketuk,
                tanpa address bar.
            </p>
            <Button onclick={pasang}>Pasang sekarang</Button>
        {:else}
            <p class="flex items-center gap-2">
                Ketuk
                <Share class="size-4" aria-hidden="true" />
                <strong>Bagikan</strong>
                lalu pilih
                <strong>Tambahkan ke Layar Utama</strong>.
            </p>
        {/if}
    </aside>
{/if}
