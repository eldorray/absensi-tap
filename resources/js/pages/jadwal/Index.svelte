<script module lang="ts">
    import { index } from '@/routes/jadwal';

    export const layout = {
        breadcrumbs: [{ title: 'Jadwal Saya', href: index() }],
    };
</script>

<script lang="ts">
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Coffee from 'lucide-svelte/icons/coffee';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';

    type Jadwal = {
        day_of_week: number;
        nama_hari: string;
        jam_masuk: string;
        jam_pulang: string;
        toleransi_menit: number;
        is_hari_kerja: boolean;
    };

    let {
        jadwals,
        hariIni,
    }: {
        jadwals: Jadwal[];
        hariIni: number;
    } = $props();
</script>

<AppHead title="Jadwal Saya" />

<div class="flex flex-col gap-4 px-4 py-5 safe-bottom">
    <section class="g-tile g-tone-plain">
        <div class="flex items-center gap-3">
            <div
                class="grid size-11 place-items-center rounded-2xl bg-primary/10 text-primary"
            >
                <CalendarClock class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h1 class="text-lg font-bold">Jadwal Saya</h1>
                <p class="text-sm text-muted-foreground">
                    Jadwal kerja dan waktu absensi mingguan
                </p>
            </div>
        </div>
    </section>

    <div class="grid gap-3">
        {#each jadwals as jadwal (jadwal.day_of_week)}
            <article
                class="g-tile gap-3 {jadwal.day_of_week === hariIni
                    ? 'g-tone-green ring-2 ring-primary/20'
                    : 'g-tone-plain'}"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="grid size-10 place-items-center rounded-2xl {jadwal.is_hari_kerja
                                ? 'bg-primary/10 text-primary'
                                : 'bg-muted text-muted-foreground'}"
                        >
                            {#if jadwal.is_hari_kerja}
                                <Clock3 class="size-5" aria-hidden="true" />
                            {:else}
                                <Coffee class="size-5" aria-hidden="true" />
                            {/if}
                        </div>
                        <div>
                            <h2 class="font-bold">{jadwal.nama_hari}</h2>
                            <p class="text-xs text-muted-foreground">
                                {jadwal.is_hari_kerja
                                    ? 'Hari kerja'
                                    : 'Hari libur'}
                            </p>
                        </div>
                    </div>
                    {#if jadwal.day_of_week === hariIni}
                        <Badge>Hari ini</Badge>
                    {/if}
                </div>

                {#if jadwal.is_hari_kerja}
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-background/70 p-3">
                            <p class="text-xs text-muted-foreground">
                                Jam masuk
                            </p>
                            <p class="mt-1 font-mono text-lg font-bold">
                                {jadwal.jam_masuk}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-background/70 p-3">
                            <p class="text-xs text-muted-foreground">
                                Jam pulang
                            </p>
                            <p class="mt-1 font-mono text-lg font-bold">
                                {jadwal.jam_pulang}
                            </p>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Toleransi keterlambatan {jadwal.toleransi_menit} menit
                    </p>
                {/if}
            </article>
        {/each}
    </div>
</div>
