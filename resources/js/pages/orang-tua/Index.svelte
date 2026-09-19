<script module lang="ts">
    import { dashboard as dashboardRoute } from '@/routes/orang-tua';

    export const layout = {
        title: 'Kehadiran Anak',
        breadcrumbs: [{ title: 'Kehadiran Anak', href: dashboardRoute() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import BookOpenCheck from 'lucide-svelte/icons/book-open-check';
    import CalendarCheck from 'lucide-svelte/icons/calendar-check';
    import CheckCircle2 from 'lucide-svelte/icons/circle-check-big';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import GraduationCap from 'lucide-svelte/icons/graduation-cap';
    import HeartHandshake from 'lucide-svelte/icons/heart-handshake';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import UserRoundCheck from 'lucide-svelte/icons/user-round-check';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { dashboard } from '@/routes/orang-tua';

    type Anak = {
        id: number;
        nama: string;
        nis: string;
        unit: string | null;
        foto: string | null;
        is_active: boolean;
    };

    type SiswaTerpilih = {
        id: number;
        nama: string;
        nis: string;
        unit: string | null;
        kelas: string | null;
        foto: string | null;
    };

    type Riwayat = {
        id: number;
        tanggal: string;
        tanggal_label: string;
        kelas: string | null;
        status: string;
        status_label: string;
        jam_datang: string | null;
        catatan: string | null;
    };

    let {
        anak,
        siswaTerpilih,
        ringkasan,
        riwayat,
    }: {
        anak: Anak[];
        siswaTerpilih: SiswaTerpilih | null;
        ringkasan: Record<string, number>;
        riwayat: Riwayat[];
    } = $props();

    const warna: Record<string, string> = {
        hadir: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
        sakit: 'bg-amber-500/14 text-amber-700 dark:text-amber-300',
        izin: 'bg-sky-500/14 text-sky-700 dark:text-sky-300',
        alpa: 'bg-rose-500/14 text-rose-700 dark:text-rose-300',
        terlambat: 'bg-violet-500/14 text-violet-700 dark:text-violet-300',
    };

    const statusHariIni = $derived(
        riwayat.find(
            (item) => item.tanggal === new Date().toLocaleDateString('sv-SE'),
        ) ?? null,
    );

    function pilihAnak(id: number): void {
        router.get(
            dashboard.url({ query: { siswa: id } }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }
</script>

<AppHead title="Kehadiran Anak" />

<div
    class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <section class="g-tile g-tone-green overflow-hidden">
        <div class="flex items-start justify-between gap-4">
            <div class="grid gap-2">
                <span
                    class="grid size-12 place-items-center rounded-2xl bg-white/40 text-emerald-800 dark:bg-white/10 dark:text-emerald-200"
                >
                    <HeartHandshake class="size-6" aria-hidden="true" />
                </span>
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.14em] uppercase opacity-70"
                    >
                        Portal Orang Tua
                    </p>
                    <h1
                        class="g-display text-[clamp(2rem,9vw,3rem)] leading-none"
                    >
                        Kehadiran anak
                    </h1>
                </div>
            </div>
            <ShieldCheck class="size-8 opacity-60" aria-hidden="true" />
        </div>
        <p class="max-w-sm text-sm leading-relaxed">
            Hanya hasil yang sudah difinalisasi sekolah yang tampil di sini.
        </p>
    </section>

    {#if anak.length === 0}
        <section class="g-tile g-tone-plain items-center py-10 text-center">
            <span
                class="grid size-16 place-items-center rounded-[1.5rem] bg-muted text-muted-foreground"
            >
                <UserRoundCheck class="size-8" aria-hidden="true" />
            </span>
            <h2 class="text-xl font-bold">Belum ada anak yang ditautkan</h2>
            <p class="max-w-xs text-sm text-muted-foreground">
                Hubungi admin sekolah agar akun ini ditautkan ke data siswa anak
                Anda.
            </p>
        </section>
    {:else}
        {#if anak.length > 1}
            <section class="g-tile g-tone-plain gap-2">
                <p
                    class="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Pilih anak
                </p>
                <div class="grid grid-cols-2 gap-2">
                    {#each anak as item (item.id)}
                        <button
                            type="button"
                            onclick={() => pilihAnak(item.id)}
                            aria-pressed={item.id === siswaTerpilih?.id}
                            class="min-h-16 rounded-2xl border px-3 py-2 text-left transition {item.id ===
                            siswaTerpilih?.id
                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                : 'border-border bg-background hover:bg-muted'}"
                        >
                            <span class="block truncate font-bold"
                                >{item.nama}</span
                            >
                            <span class="block truncate text-xs opacity-75"
                                >NIS {item.nis}</span
                            >
                        </button>
                    {/each}
                </div>
            </section>
        {/if}

        {#if siswaTerpilih}
            <section class="g-tile g-tone-blue">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-[1.25rem] bg-white/35 text-xl font-black dark:bg-white/10"
                    >
                        {#if siswaTerpilih.foto}
                            <img
                                src={`/storage/${siswaTerpilih.foto}`}
                                alt={`Foto ${siswaTerpilih.nama}`}
                                class="size-full object-cover"
                            />
                        {:else}
                            {siswaTerpilih.nama.charAt(0).toUpperCase()}
                        {/if}
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-xl font-bold">
                            {siswaTerpilih.nama}
                        </h2>
                        <p class="truncate text-sm opacity-75">
                            {siswaTerpilih.kelas ?? 'Belum ditempatkan'} · {siswaTerpilih.unit ??
                                'Tanpa unit'}
                        </p>
                    </div>
                    <GraduationCap
                        class="size-6 shrink-0 opacity-60"
                        aria-hidden="true"
                    />
                </div>
            </section>

            <section
                class="g-tile {statusHariIni
                    ? 'g-tone-green'
                    : 'g-tone-yellow'}"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.12em] uppercase opacity-70"
                        >
                            Hari ini
                        </p>
                        <h2 class="text-xl font-bold">
                            {statusHariIni?.status_label ??
                                'Belum dipublikasikan'}
                        </h2>
                    </div>
                    <CalendarCheck
                        class="size-7 opacity-70"
                        aria-hidden="true"
                    />
                </div>
                {#if statusHariIni}
                    <p class="text-sm">
                        {statusHariIni.jam_datang
                            ? `Tiba pukul ${statusHariIni.jam_datang}`
                            : 'Kehadiran sudah dicatat sekolah.'}
                    </p>
                {:else}
                    <p class="text-sm">
                        Hasil hari ini akan muncul setelah guru menyelesaikan
                        pemeriksaan kelas.
                    </p>
                {/if}
            </section>

            <section class="g-tile g-tone-plain">
                <div class="flex items-center gap-2">
                    <BookOpenCheck
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                    <h2 class="text-lg font-bold">
                        Ringkasan 60 catatan terakhir
                    </h2>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    {#each ['hadir', 'sakit', 'izin', 'alpa', 'terlambat'] as status (status)}
                        <div class="rounded-2xl bg-muted/70 p-3 text-center">
                            <p class="text-2xl font-black tabular-nums">
                                {ringkasan[status] ?? 0}
                            </p>
                            <p
                                class="text-[0.6875rem] font-bold capitalize text-muted-foreground"
                            >
                                {status}
                            </p>
                        </div>
                    {/each}
                </div>
            </section>

            <section class="g-tile g-tone-plain gap-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            Riwayat
                        </p>
                        <h2 class="text-xl font-bold">Kehadiran terbaru</h2>
                    </div>
                    <Clock3
                        class="size-6 text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>

                <div class="grid gap-2">
                    {#each riwayat as item (item.id)}
                        <article
                            class="rounded-[1.25rem] border border-border/70 bg-background p-3.5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-bold leading-tight">
                                        {item.tanggal_label}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {item.kelas ??
                                            siswaTerpilih.kelas ??
                                            'Kelas'}{item.jam_datang
                                            ? ` · ${item.jam_datang}`
                                            : ''}
                                    </p>
                                </div>
                                <Badge class={warna[item.status] ?? ''}
                                    >{item.status_label}</Badge
                                >
                            </div>
                            {#if item.catatan}
                                <p
                                    class="mt-3 rounded-xl bg-muted/65 px-3 py-2 text-sm"
                                >
                                    {item.catatan}
                                </p>
                            {/if}
                        </article>
                    {:else}
                        <div
                            class="grid place-items-center gap-2 rounded-[1.25rem] bg-muted/55 px-4 py-8 text-center"
                        >
                            <CheckCircle2
                                class="size-7 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p class="font-bold">Belum ada hasil kehadiran</p>
                            <p class="text-sm text-muted-foreground">
                                Hasil final dari sekolah akan tersusun di sini.
                            </p>
                        </div>
                    {/each}
                </div>
            </section>
        {/if}
    {/if}
</div>
