<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Bell from 'lucide-svelte/icons/bell';
    import BellOff from 'lucide-svelte/icons/bell-off';
    import { Button } from '@/components/ui/button';
    import {
        destroy as langgananDestroy,
        store as langgananStore,
    } from '@/routes/admin/langganan-push';

    let { vapidPublicKey }: { vapidPublicKey: string | null } = $props();

    let didukung = $state(false);
    let ditolak = $state(false);
    let langganan = $state<PushSubscription | null>(null);
    let sibuk = $state(false);

    $effect(() => {
        didukung =
            vapidPublicKey !== null &&
            'serviceWorker' in navigator &&
            'PushManager' in window;
        ditolak =
            'Notification' in window && Notification.permission === 'denied';

        if (didukung) {
            navigator.serviceWorker.ready
                .then((registrasi) => registrasi.pushManager.getSubscription())
                .then((s) => (langganan = s));
        }
    });

    /**
     * PushManager meminta kunci VAPID sebagai bytes, server memberinya base64url.
     */
    function kunciKeBytes(base64url: string): Uint8Array<ArrayBuffer> {
        const base64 = (
            base64url + '='.repeat((4 - (base64url.length % 4)) % 4)
        )
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
    }

    async function aktifkan(): Promise<void> {
        sibuk = true;

        try {
            const registrasi = await navigator.serviceWorker.ready;
            const baru = await registrasi.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: kunciKeBytes(vapidPublicKey!),
            });

            const { endpoint, keys } = baru.toJSON();

            router.post(
                langgananStore().url,
                { endpoint, keys },
                {
                    preserveScroll: true,
                    onSuccess: () => (langganan = baru),
                    onFinish: () => (sibuk = false),
                },
            );
        } catch {
            ditolak = Notification.permission === 'denied';
            sibuk = false;
        }
    }

    async function matikan(): Promise<void> {
        if (!langganan) {
            return;
        }

        const endpoint = langganan.endpoint;
        sibuk = true;
        await langganan.unsubscribe();
        langganan = null;

        router.delete(langgananDestroy().url, {
            data: { endpoint },
            preserveScroll: true,
            onFinish: () => (sibuk = false),
        });
    }
</script>

<section class="g-tile g-tone-plain gap-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-base">Notifikasi izin di perangkat ini</h3>
            <p class="text-sm text-muted-foreground">
                {#if !didukung}
                    Browser ini belum mendukung notifikasi. Di iPhone, pasang
                    aplikasi ke Layar Utama lalu buka dari sana.
                {:else if ditolak}
                    Notifikasi diblokir. Izinkan lewat pengaturan situs di
                    browser.
                {:else if langganan}
                    Aktif. Pengajuan izin baru akan muncul sebagai notifikasi.
                {:else}
                    Dapatkan notifikasi saat guru atau orang tua mengajukan
                    izin.
                {/if}
            </p>
        </div>

        {#if didukung && !ditolak}
            {#if langganan}
                <Button variant="outline" disabled={sibuk} onclick={matikan}>
                    <BellOff class="size-4" aria-hidden="true" />
                    Matikan
                </Button>
            {:else}
                <Button disabled={sibuk} onclick={aktifkan}>
                    <Bell class="size-4" aria-hidden="true" />
                    Aktifkan
                </Button>
            {/if}
        {/if}
    </div>
</section>
