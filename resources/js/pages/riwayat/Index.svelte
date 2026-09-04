<script module lang="ts">
    import { index } from '@/routes/riwayat';

    export const layout = {
        breadcrumbs: [{ title: 'Riwayat', href: index() }],
    };
</script>

<script lang="ts">
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';

    type Riwayat = {
        tanggal: string;
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
        terverifikasi: boolean;
    };

    let { riwayat }: { riwayat: Riwayat[] } = $props();

    const tanggal = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const labelStatus: Record<string, string> = {
        hadir: 'Hadir',
        terlambat: 'Terlambat',
    };
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
            <div>
                <h1 class="text-lg font-bold">Riwayat absensi</h1>
                <p class="text-sm text-muted-foreground">
                    Catatan kehadiran 30 hari terakhir
                </p>
            </div>
        </div>
    </section>

    {#if riwayat.length === 0}
        <section class="g-tile g-tone-plain py-12 text-center">
            <CalendarDays
                class="mx-auto mb-3 size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="font-semibold">Belum ada riwayat</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Absensi yang sudah tercatat akan muncul di sini.
            </p>
        </section>
    {:else}
        <div class="grid gap-3">
            {#each riwayat as baris (baris.tanggal)}
                <article class="g-tile g-tone-plain gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold capitalize">
                                {tanggal.format(
                                    new Date(`${baris.tanggal}T00:00:00`),
                                )}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {baris.tanggal}
                            </p>
                        </div>
                        <Badge
                            variant={baris.status === 'terlambat'
                                ? 'outline'
                                : 'secondary'}
                        >
                            {baris.status
                                ? (labelStatus[baris.status] ?? baris.status)
                                : 'Tercatat'}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-muted/70 p-3">
                            <p class="text-xs text-muted-foreground">Masuk</p>
                            <p
                                class="mt-1 flex items-center gap-1.5 font-mono font-semibold"
                            >
                                <Clock3 class="size-4" aria-hidden="true" />
                                {baris.jam_masuk ?? '-'}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/70 p-3">
                            <p class="text-xs text-muted-foreground">Pulang</p>
                            <p
                                class="mt-1 flex items-center gap-1.5 font-mono font-semibold"
                            >
                                <Clock3 class="size-4" aria-hidden="true" />
                                {baris.jam_pulang ?? '-'}
                            </p>
                        </div>
                    </div>

                    {#if baris.pulang_cepat || !baris.terverifikasi}
                        <div class="flex flex-wrap gap-2">
                            {#if baris.pulang_cepat}<Badge variant="outline"
                                    >Pulang cepat</Badge
                                >{/if}
                            {#if !baris.terverifikasi}
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
