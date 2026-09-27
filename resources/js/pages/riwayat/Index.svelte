<script module lang="ts">
    import { index } from '@/routes/riwayat';

    export const layout = {
        breadcrumbs: [{ title: 'Riwayat', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';

    type Hari = {
        tanggal: string;
        status: string;
        jam_masuk: string | null;
        jam_pulang: string | null;
        menit_terlambat: number;
        pulang_cepat: boolean;
        tanpa_biometrik: boolean;
    };

    let {
        bulan,
        ringkasan,
        hari,
    }: {
        bulan: {
            nilai: string;
            label: string;
            sebelumnya: string | null;
            berikutnya: string | null;
        };
        ringkasan: {
            hari_efektif: number;
            hadir: number;
            terlambat: number;
            menit_terlambat: number;
            izin: number;
            sakit: number;
            cuti: number;
            alfa: number;
            persentase: number;
        };
        hari: Hari[];
    } = $props();

    /** Tanggal ISO dibaca sebagai UTC supaya tidak bergeser zona waktu. */
    const formatTanggal = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        weekday: 'long',
        day: 'numeric',
        month: 'short',
    });

    /**
     * "Belum absen" sengaja netral: hari ini masih berjalan, jadi belum tentu
     * ada yang salah.
     */
    const status: Record<string, { label: string; kelas: string }> = {
        hadir: {
            label: 'Hadir',
            kelas: 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]',
        },
        terlambat: {
            label: 'Terlambat',
            kelas: 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink)]',
        },
        alfa: {
            label: 'Alfa',
            kelas: 'bg-[var(--g-red-c)] text-[var(--g-red-ink)]',
        },
        izin: {
            label: 'Izin',
            kelas: 'bg-[var(--g-blue-c)] text-[var(--g-blue-ink)]',
        },
        sakit: {
            label: 'Sakit',
            kelas: 'bg-[var(--g-blue)] text-[var(--g-on-blue)]',
        },
        cuti: {
            label: 'Cuti',
            kelas: 'border-dashed border-[var(--g-blue-ink-2)] text-[var(--g-blue-ink)]',
        },
        libur: { label: 'Libur', kelas: 'bg-muted text-muted-foreground' },
        belum: { label: 'Belum absen', kelas: 'text-muted-foreground' },
        bukan_hari_kerja: {
            label: 'Bukan hari kerja',
            kelas: 'text-muted-foreground',
        },
    };

    const statusTanpaJam = ['alfa', 'izin', 'sakit', 'cuti', 'libur', 'belum'];

    let izinTotal = $derived(ringkasan.izin + ringkasan.sakit + ringkasan.cuti);
</script>

<AppHead title="Riwayat absensi" />

<div class="flex flex-col gap-4 px-4 py-5 safe-bottom">
    <section class="g-tile g-tone-plain">
        <div class="flex items-center gap-3">
            <div
                class="grid size-11 place-items-center rounded-2xl bg-primary/10 text-primary"
            >
                <CalendarDays class="size-5" aria-hidden="true" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-bold">Riwayat absensi</h1>
                <p class="text-sm text-muted-foreground">
                    Catatan kehadiran per bulan
                </p>
            </div>
        </div>

        <nav
            class="flex items-center justify-between gap-2"
            aria-label="Pilih bulan"
        >
            {#if bulan.sebelumnya}
                <Link
                    href={index.url({ query: { bulan: bulan.sebelumnya } })}
                    class="grid size-10 place-items-center rounded-full bg-muted/70 hover:bg-muted"
                    aria-label="Bulan sebelumnya"
                    preserveScroll
                >
                    <ChevronLeft class="size-5" aria-hidden="true" />
                </Link>
            {:else}
                <span class="size-10" aria-hidden="true"></span>
            {/if}
            <p class="font-semibold" aria-live="polite">{bulan.label}</p>
            {#if bulan.berikutnya}
                <Link
                    href={index.url({ query: { bulan: bulan.berikutnya } })}
                    class="grid size-10 place-items-center rounded-full bg-muted/70 hover:bg-muted"
                    aria-label="Bulan berikutnya"
                    preserveScroll
                >
                    <ChevronRight class="size-5" aria-hidden="true" />
                </Link>
            {:else}
                <span class="size-10" aria-hidden="true"></span>
            {/if}
        </nav>

        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-xs text-muted-foreground">
                    Persentase kehadiran
                </p>
                <p class="text-3xl font-bold tabular-nums">
                    {ringkasan.persentase.toLocaleString('id-ID')}%
                </p>
            </div>
            <p class="text-right text-xs text-muted-foreground">
                dari {ringkasan.hari_efektif} hari efektif
            </p>
        </div>

        <dl class="grid grid-cols-2 gap-2 text-sm">
            <div class="rounded-2xl bg-muted/70 p-3">
                <dt class="text-xs text-muted-foreground">Hadir</dt>
                <dd class="mt-1 font-semibold tabular-nums">
                    {ringkasan.hadir}
                </dd>
            </div>
            <div class="rounded-2xl bg-muted/70 p-3">
                <dt class="text-xs text-muted-foreground">Terlambat</dt>
                <dd class="mt-1 font-semibold tabular-nums">
                    {ringkasan.terlambat}
                    {#if ringkasan.menit_terlambat > 0}<span
                            class="text-xs font-normal text-muted-foreground"
                            >· {ringkasan.menit_terlambat} menit</span
                        >{/if}
                </dd>
            </div>
            <div class="rounded-2xl bg-muted/70 p-3">
                <dt class="text-xs text-muted-foreground">Izin/Sakit/Cuti</dt>
                <dd class="mt-1 font-semibold tabular-nums">
                    {izinTotal}
                    {#if izinTotal > 0}<span
                            class="text-xs font-normal text-muted-foreground"
                            >· {ringkasan.izin}/{ringkasan.sakit}/{ringkasan.cuti}</span
                        >{/if}
                </dd>
            </div>
            <div class="rounded-2xl bg-muted/70 p-3">
                <dt class="text-xs text-muted-foreground">Alfa</dt>
                <dd class="mt-1 font-semibold tabular-nums">
                    {ringkasan.alfa}
                </dd>
            </div>
        </dl>
    </section>

    {#if hari.length === 0}
        <section class="g-tile g-tone-plain py-12 text-center">
            <CalendarDays
                class="mx-auto mb-3 size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="font-semibold">Belum ada hari kerja</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Tidak ada hari kerja pada bulan ini.
            </p>
        </section>
    {:else}
        <div class="grid gap-3">
            {#each hari as baris (baris.tanggal)}
                <article class="g-tile g-tone-plain gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold">
                            {formatTanggal.format(new Date(baris.tanggal))}
                        </p>
                        <Badge
                            variant="outline"
                            class="border-transparent {status[baris.status]
                                ?.kelas ?? ''}"
                        >
                            {status[baris.status]?.label ?? baris.status}
                        </Badge>
                    </div>

                    {#if !statusTanpaJam.includes(baris.status) || baris.jam_masuk}
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-2xl bg-muted/70 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Masuk
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 font-mono font-semibold"
                                >
                                    <Clock3 class="size-4" aria-hidden="true" />
                                    {baris.jam_masuk ?? '-'}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-muted/70 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Pulang
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1.5 font-mono font-semibold"
                                >
                                    <Clock3 class="size-4" aria-hidden="true" />
                                    {baris.jam_pulang ?? '-'}
                                </p>
                            </div>
                        </div>
                    {/if}

                    {#if baris.menit_terlambat > 0 || baris.pulang_cepat || baris.tanpa_biometrik}
                        <div class="flex flex-wrap gap-2">
                            {#if baris.menit_terlambat > 0}<Badge
                                    variant="outline"
                                    >Terlambat {baris.menit_terlambat} menit</Badge
                                >{/if}
                            {#if baris.pulang_cepat}<Badge variant="outline"
                                    >Pulang cepat</Badge
                                >{/if}
                            {#if baris.tanpa_biometrik}
                                <Badge variant="outline" class="gap-1">
                                    <ShieldAlert
                                        class="size-3"
                                        aria-hidden="true"
                                    /> Tanpa biometrik
                                </Badge>
                            {/if}
                        </div>
                    {/if}
                </article>
            {/each}
        </div>
    {/if}
</div>
