<script module lang="ts">
    import { index } from '@/routes/admin/pengumuman';
    export const layout = {
        breadcrumbs: [{ title: 'Pengumuman', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
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
        destroy as pengumumanDestroy,
        store as pengumumanStore,
        update as pengumumanUpdate,
    } from '@/routes/admin/pengumuman';

    type P = {
        id: number;
        judul: string;
        isi: string;
        is_active: boolean;
        dibuat: string | null;
    };

    let { pengumumans }: { pengumumans: P[] } = $props();

    const baru = useForm({ judul: '', isi: '', is_active: true });
    const ubah = useForm({ judul: '', isi: '', is_active: true });

    let diubah = $state<number | null>(null);

    const aktif = $derived(pengumumans.filter((p) => p.is_active).length);

    function mulaiUbah(p: P): void {
        ubah.judul = p.judul;
        ubah.isi = p.isi;
        ubah.is_active = p.is_active;
        ubah.clearErrors();
        diubah = p.id;
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function hapus(p: P): void {
        konfirmasi = {
            judul: `Hapus pengumuman "${p.judul}"?`,
            pesan: 'Pengumuman ini hilang dari halaman absen guru dan tidak bisa dikembalikan. Untuk menyembunyikannya sementara, pakai tombol Ubah lalu matikan "Tampilkan ke guru".',
            aksi: () =>
                router.delete(pengumumanDestroy(p.id).url, {
                    preserveScroll: true,
                }),
        };
    }
</script>

<AppHead title="Pengumuman" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Tulis pengumuman</h3>
            <p class="text-muted-foreground">
                Pengumuman aktif tampil di halaman absen guru, di bawah tombol
                tap. Lima terbaru yang ditampilkan.
            </p>
        </div>

        <form
            class="grid gap-3"
            onsubmit={(e) => {
                e.preventDefault();
                baru.submit(pengumumanStore(), {
                    preserveScroll: true,
                    onSuccess: () => baru.reset(),
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="judul">Judul</Label>
                <Input
                    id="judul"
                    placeholder="Rapat guru Sabtu"
                    bind:value={baru.judul}
                />
                {#if baru.errors.judul}<p class="text-xs text-destructive">
                        {baru.errors.judul}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="isi">Isi</Label>
                <textarea
                    id="isi"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-2xl border border-input bg-background px-4 py-3 text-[0.9375rem] transition-[border-color,box-shadow] hover:border-foreground/45 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/45 focus-visible:outline-none"
                    bind:value={baru.isi}
                ></textarea>
                {#if baru.errors.isi}<p class="text-xs text-destructive">
                        {baru.errors.isi}
                    </p>{/if}
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Checkbox id="aktif" bind:checked={baru.is_active} />
                    <Label for="aktif">Langsung tampilkan ke guru</Label>
                </div>
                <Button type="submit" disabled={baru.processing}
                    >Tambah pengumuman</Button
                >
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Daftar pengumuman</h3>
            <p class="text-muted-foreground">
                {aktif} tampil ke guru dari {pengumumans.length} pengumuman.
            </p>
        </div>

        {#if pengumumans.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada pengumuman.
            </p>
        {:else}
            <ul class="grid gap-2">
                {#each pengumumans as p (p.id)}
                    <li class="rounded-2xl border border-border px-4 py-3">
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">{p.judul}</p>
                                    <Badge
                                        variant={p.is_active
                                            ? 'default'
                                            : 'outline'}
                                        >{p.is_active
                                            ? 'Tampil'
                                            : 'Disembunyikan'}</Badge
                                    >
                                </div>
                                <p
                                    class="mt-1 text-sm whitespace-pre-line text-muted-foreground"
                                >
                                    {p.isi}
                                </p>
                                {#if p.dibuat}
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {p.dibuat}
                                    </p>
                                {/if}
                            </div>
                            <div class="flex items-center gap-1">
                                <TombolIkon
                                    ikon={diubah === p.id ? X : Pencil}
                                    nada="biru"
                                    label={diubah === p.id
                                        ? 'Tutup form'
                                        : `Ubah pengumuman ${p.judul}`}
                                    onclick={() =>
                                        diubah === p.id
                                            ? (diubah = null)
                                            : mulaiUbah(p)}
                                />
                                <TombolIkon
                                    ikon={Trash2}
                                    nada="merah"
                                    label={`Hapus pengumuman ${p.judul}`}
                                    onclick={() => hapus(p)}
                                />
                            </div>
                        </div>

                        {#if diubah === p.id}
                            <form
                                class="mt-3 grid gap-3 border-t border-border pt-3"
                                onsubmit={(e) => {
                                    e.preventDefault();
                                    ubah.submit(pengumumanUpdate(p.id), {
                                        preserveScroll: true,
                                        onSuccess: () => (diubah = null),
                                    });
                                }}
                            >
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-judul-${p.id}`}
                                        >Judul</Label
                                    >
                                    <Input
                                        id={`ubah-judul-${p.id}`}
                                        bind:value={ubah.judul}
                                    />
                                    {#if ubah.errors.judul}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubah.errors.judul}
                                        </p>{/if}
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-isi-${p.id}`}>Isi</Label>
                                    <textarea
                                        id={`ubah-isi-${p.id}`}
                                        rows="3"
                                        maxlength="1000"
                                        class="w-full rounded-2xl border border-input bg-background px-4 py-3 text-[0.9375rem] focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/45 focus-visible:outline-none"
                                        bind:value={ubah.isi}
                                    ></textarea>
                                    {#if ubah.errors.isi}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubah.errors.isi}
                                        </p>{/if}
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id={`ubah-aktif-${p.id}`}
                                            bind:checked={ubah.is_active}
                                        />
                                        <Label for={`ubah-aktif-${p.id}`}
                                            >Tampilkan ke guru</Label
                                        >
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={ubah.processing}
                                        >Simpan perubahan</Button
                                    >
                                </div>
                            </form>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
