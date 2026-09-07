<script module lang="ts">
    import { index } from '@/routes/admin/jadwal-guru';
    export const layout = {
        breadcrumbs: [{ title: 'Jadwal guru', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        destroy as jadwalGuruDestroy,
        update as jadwalGuruUpdate,
    } from '@/routes/admin/jadwal-guru';

    type J = {
        day_of_week: number;
        jam_masuk: string;
        jam_pulang: string;
        is_hari_kerja: boolean;
    };
    type G = {
        id: number;
        name: string;
        nip: string | null;
        punya_jadwal_sendiri: boolean;
        jadwals: J[];
    };

    let { gurus }: { gurus: G[] } = $props();

    const hari = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
    ];

    let dibuka = $state<number | null>(null);

    const form = useForm<{ jadwals: J[] }>({ jadwals: [] });

    function mulaiUbah(g: G): void {
        form.jadwals = g.jadwals.map((j) => ({ ...j }));
        form.clearErrors();
        dibuka = g.id;
    }

    /** Ringkasan sepekan untuk baris yang sedang tertutup. */
    function ringkas(g: G): string {
        const kerja = g.jadwals.filter((j) => j.is_hari_kerja);

        if (kerja.length === 0) {
            return 'Tidak ada hari kerja';
        }

        const jam = new Set(kerja.map((j) => `${j.jam_masuk}-${j.jam_pulang}`));

        return jam.size === 1
            ? `${kerja.length} hari kerja · ${[...jam][0].replace('-', ' sampai ')}`
            : `${kerja.length} hari kerja · jam berbeda-beda`;
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function kembalikanDefault(g: G): void {
        konfirmasi = {
            judul: `Kembalikan jadwal ${g.name} ke default?`,
            pesan: 'Jadwal khusus guru ini dihapus dan dia mengikuti jadwal default sekolah.',
            label: 'Ikut default',
            aksi: () =>
                router.delete(jadwalGuruDestroy(g.id).url, {
                    preserveScroll: true,
                    onSuccess: () => (dibuka = null),
                }),
        };
    }
</script>

<AppHead title="Jadwal guru" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Jadwal guru</h3>
            <p class="text-muted-foreground">
                Guru tanpa jadwal sendiri mengikuti jadwal default sekolah.
                Toleransi dan jendela absen diatur sekali di halaman Pengaturan.
            </p>
        </div>

        <ul class="grid gap-2">
            {#each gurus as g (g.id)}
                <li class="rounded-2xl border border-border px-4 py-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold">{g.name}</p>
                            <p class="text-xs text-muted-foreground">
                                {g.nip ? `NIP ${g.nip} · ` : ''}{ringkas(g)}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                variant={g.punya_jadwal_sendiri
                                    ? 'default'
                                    : 'outline'}
                                >{g.punya_jadwal_sendiri
                                    ? 'Jadwal sendiri'
                                    : 'Ikut default'}</Badge
                            >
                            {#if g.punya_jadwal_sendiri}
                                <TombolIkon
                                    ikon={RotateCcw}
                                    nada="kuning"
                                    label={`Kembalikan jadwal ${g.name} ke default`}
                                    onclick={() => kembalikanDefault(g)}
                                />
                            {/if}
                            <TombolIkon
                                ikon={dibuka === g.id ? X : Pencil}
                                nada="biru"
                                label={dibuka === g.id
                                    ? 'Tutup form'
                                    : `Atur jadwal ${g.name}`}
                                onclick={() =>
                                    dibuka === g.id
                                        ? (dibuka = null)
                                        : mulaiUbah(g)}
                            />
                        </div>
                    </div>

                    {#if dibuka === g.id}
                        <form
                            class="mt-3 grid gap-3 border-t border-border pt-3"
                            onsubmit={(e) => {
                                e.preventDefault();
                                form.submit(jadwalGuruUpdate(g.id), {
                                    preserveScroll: true,
                                    onSuccess: () => (dibuka = null),
                                });
                            }}
                        >
                            {#each form.jadwals as j, i (j.day_of_week)}
                                <div
                                    class="grid gap-3 rounded-2xl border border-border px-4 py-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id={`kerja-${g.id}-${j.day_of_week}`}
                                            bind:checked={
                                                form.jadwals[i].is_hari_kerja
                                            }
                                        />
                                        <Label
                                            for={`kerja-${g.id}-${j.day_of_week}`}
                                            class="font-semibold"
                                            >{hari[j.day_of_week]}</Label
                                        >
                                        {#if !j.is_hari_kerja}
                                            <Badge variant="outline"
                                                >Libur</Badge
                                            >
                                        {/if}
                                    </div>

                                    {#if j.is_hari_kerja}
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div class="grid gap-1.5">
                                                <Label
                                                    for={`masuk-${g.id}-${j.day_of_week}`}
                                                    >Jam masuk</Label
                                                >
                                                <Input
                                                    id={`masuk-${g.id}-${j.day_of_week}`}
                                                    type="time"
                                                    bind:value={
                                                        form.jadwals[i]
                                                            .jam_masuk
                                                    }
                                                />
                                            </div>
                                            <div class="grid gap-1.5">
                                                <Label
                                                    for={`pulang-${g.id}-${j.day_of_week}`}
                                                    >Jam pulang</Label
                                                >
                                                <Input
                                                    id={`pulang-${g.id}-${j.day_of_week}`}
                                                    type="time"
                                                    bind:value={
                                                        form.jadwals[i]
                                                            .jam_pulang
                                                    }
                                                />
                                            </div>
                                        </div>
                                    {/if}
                                </div>
                            {/each}

                            {#if form.hasErrors}
                                <p class="text-sm text-destructive">
                                    Ada isian jadwal yang belum benar. Jam
                                    pulang harus setelah jam masuk.
                                </p>
                            {/if}

                            <div class="flex justify-end">
                                <Button type="submit" disabled={form.processing}
                                    >Simpan jadwal {g.name}</Button
                                >
                            </div>
                        </form>
                    {/if}
                </li>
            {/each}
        </ul>
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
