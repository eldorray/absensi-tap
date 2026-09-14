<script module lang="ts">
    import { index } from '@/routes/kelas-saya';
    export const layout = {
        title: 'Kelas Saya',
        breadcrumbs: [{ title: 'Kelas Saya', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import UsersRound from 'lucide-svelte/icons/users-round';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { show as absensiShow } from '@/routes/absensi-siswa';

    type Kelas = {
        id: number;
        nama: string;
        tingkat: number;
        kantor: string | null;
        anggotas: { id: number; nis: string | null; nama: string | null }[];
    };

    let { kelas }: { kelas: Kelas[] } = $props();
</script>

<AppHead title="Kelas Saya" />

<div
    class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <section class="g-tile g-tone-blue">
        <UsersRound class="size-7" aria-hidden="true" />
        <h1 class="g-display text-[clamp(1.75rem,7vw,2.25rem)]">Kelas Saya</h1>
        <p>
            Kelas yang dipercayakan kepada Anda sebagai wali kelas atau guru
            pengganti hari ini.
        </p>
    </section>

    {#each kelas as item (item.id)}
        <section class="g-tile g-tone-plain">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold">{item.nama}</h2>
                    <p class="text-sm text-muted-foreground">
                        {item.kantor ?? 'Tanpa unit'} · Tingkat {item.tingkat}
                    </p>
                </div>
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary"
                    >{item.anggotas.length} siswa</span
                >
            </div>
            <div class="overflow-hidden rounded-2xl border border-border">
                {#each item.anggotas as siswa, nomor (siswa.id)}
                    <div
                        class="flex min-h-14 items-center gap-3 border-b border-border px-3 last:border-b-0"
                    >
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-xl bg-muted font-mono text-xs font-bold"
                            >{nomor + 1}</span
                        >
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{siswa.nama}</p>
                            <p class="font-mono text-xs text-muted-foreground">
                                NIS {siswa.nis}
                            </p>
                        </div>
                    </div>
                {:else}<p
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Belum ada siswa aktif di kelas ini.
                    </p>{/each}
            </div>
            <Link
                href={toUrl(absensiShow(item.id))}
                class="flex min-h-12 items-center justify-center rounded-2xl bg-primary px-4 font-bold text-primary-foreground"
                >Periksa absensi siswa</Link
            >
        </section>
    {:else}
        <section class="g-tile g-tone-plain text-center">
            <h2 class="font-bold">Belum ada kelas</h2>
            <p class="text-muted-foreground">
                Anda belum ditunjuk sebagai wali kelas atau guru pengganti
                aktif.
            </p>
        </section>
    {/each}
</div>
