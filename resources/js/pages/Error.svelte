<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Construction from 'lucide-svelte/icons/construction';
    import House from 'lucide-svelte/icons/house';
    import Lock from 'lucide-svelte/icons/lock';
    import SearchX from 'lucide-svelte/icons/search-x';
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { aplikasi, login } from '@/routes';

    let { status }: { status: 403 | 404 | 500 | 503 } = $props();

    const isi = {
        403: {
            ikon: Lock,
            judul: 'Tidak punya akses',
            pesan: 'Halaman ini bukan untuk akun Anda. Kalau menurut Anda ini keliru, hubungi TU sekolah.',
        },
        404: {
            ikon: SearchX,
            judul: 'Halaman tidak ditemukan',
            pesan: 'Alamatnya mungkin salah ketik, atau halamannya sudah dipindah.',
        },
        500: {
            ikon: TriangleAlert,
            judul: 'Terjadi kesalahan',
            pesan: 'Ada masalah di server kami. Coba lagi beberapa saat lagi. Kalau terus terjadi, beri tahu TU sekolah.',
        },
        503: {
            ikon: Construction,
            judul: 'Sedang perbaikan',
            pesan: 'Aplikasi sedang diperbarui sebentar. Silakan coba lagi dalam beberapa menit.',
        },
    } as const;

    const konten = $derived(isi[status] ?? isi[500]);
    const masuk = $derived(page.props.auth?.user != null);
</script>

<AppHead title={konten.judul} />

<main
    class="grid min-h-svh place-items-center bg-background px-4 py-10 text-foreground"
>
    <section
        class="g-tile g-tone-plain w-full max-w-md items-center text-center"
        aria-labelledby="judul-error"
    >
        <div
            class="grid size-16 place-items-center rounded-3xl bg-primary/10 text-primary"
        >
            <konten.ikon class="size-8" />
        </div>
        <p class="text-sm font-semibold text-muted-foreground">
            Kode {status}
        </p>
        <h1 id="judul-error" class="text-2xl font-bold">{konten.judul}</h1>
        <p class="text-muted-foreground">{konten.pesan}</p>

        <div class="mt-2 flex w-full flex-col gap-2 sm:flex-row">
            <button
                type="button"
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl border border-border px-4 font-semibold hover:bg-muted"
                onclick={() => history.back()}
            >
                <ArrowLeft class="size-4" />
                Kembali
            </button>
            <Link
                href={toUrl(masuk ? aplikasi() : login())}
                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-4 font-semibold text-primary-foreground"
            >
                <House class="size-4" />
                {masuk ? 'Ke halaman utama' : 'Ke halaman masuk'}
            </Link>
        </div>
    </section>
</main>
