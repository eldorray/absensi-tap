<script module lang="ts">
    import { index } from '@/routes/admin/kantor';
    export const layout = {
        breadcrumbs: [{ title: 'Kantor', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Building2 from 'lucide-svelte/icons/building-2';
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
        destroy as kantorDestroy,
        store as kantorStore,
        update as kantorUpdate,
    } from '@/routes/admin/kantor';

    type K = {
        id: number;
        nama: string;
        jenjang: string | null;
        alamat: string | null;
        is_active: boolean;
        jumlah_guru: number;
        jumlah_lokasi: number;
    };
    type L = {
        id: number;
        kantor_id: number | null;
        nama: string;
        radius_meter: number;
        is_active: boolean;
    };
    type G = {
        id: number;
        name: string;
        nip: string | null;
        kantor_id: number | null;
    };

    let {
        kantors,
        lokasis,
        gurus,
    }: { kantors: K[]; lokasis: L[]; gurus: G[] } = $props();

    const baru = useForm({
        nama: '',
        jenjang: '',
        alamat: '',
        is_active: true,
    });
    const ubah = useForm({
        nama: '',
        jenjang: '',
        alamat: '',
        is_active: true,
    });

    let diubah = $state<number | null>(null);

    const tanpaKantor = $derived(gurus.filter((g) => g.kantor_id === null));
    const lokasiBersama = $derived(lokasis.filter((l) => l.kantor_id === null));

    function mulaiUbah(k: K): void {
        ubah.nama = k.nama;
        ubah.jenjang = k.jenjang ?? '';
        ubah.alamat = k.alamat ?? '';
        ubah.is_active = k.is_active;
        ubah.clearErrors();
        diubah = k.id;
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function hapus(k: K): void {
        konfirmasi = {
            judul: `Hapus kantor ${k.nama}?`,
            pesan: `${k.jumlah_guru} guru dan ${k.jumlah_lokasi} lokasinya tidak dihapus, hanya lepas penugasan.`,
            aksi: () =>
                router.delete(kantorDestroy(k.id).url, {
                    preserveScroll: true,
                }),
        };
    }
</script>

<AppHead title="Kantor" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Kantor</h3>
            <p class="text-muted-foreground">
                Unit sekolah di bawah yayasan, mis. MI dan SMP. Guru ditugaskan
                ke satu kantor, dan absennya diukur ke lokasi kantor itu
                ditambah lokasi yang dipakai bersama.
            </p>
        </div>

        {#if kantors.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada kantor. Tanpa kantor, absen guru diukur ke seluruh
                lokasi aktif seperti sebelumnya.
            </p>
        {:else}
            <ul class="grid gap-2">
                {#each kantors as k (k.id)}
                    <li class="rounded-2xl border border-border px-4 py-3">
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Building2
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <p class="font-semibold">{k.nama}</p>
                                    {#if k.jenjang}
                                        <Badge variant="secondary"
                                            >{k.jenjang}</Badge
                                        >
                                    {/if}
                                    {#if !k.is_active}
                                        <Badge variant="outline">Nonaktif</Badge
                                        >
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {k.jumlah_guru} guru · {k.jumlah_lokasi} lokasi{k.alamat
                                        ? ` · ${k.alamat}`
                                        : ''}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <TombolIkon
                                    ikon={diubah === k.id ? X : Pencil}
                                    nada="biru"
                                    label={diubah === k.id
                                        ? 'Tutup form'
                                        : `Ubah kantor ${k.nama}`}
                                    onclick={() =>
                                        diubah === k.id
                                            ? (diubah = null)
                                            : mulaiUbah(k)}
                                />
                                <TombolIkon
                                    ikon={Trash2}
                                    nada="merah"
                                    label={`Hapus kantor ${k.nama}`}
                                    onclick={() => hapus(k)}
                                />
                            </div>
                        </div>

                        {#if diubah === k.id}
                            <form
                                class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2"
                                onsubmit={(e) => {
                                    e.preventDefault();
                                    ubah.submit(kantorUpdate(k.id), {
                                        preserveScroll: true,
                                        onSuccess: () => (diubah = null),
                                    });
                                }}
                            >
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-nama-${k.id}`}
                                        >Nama kantor</Label
                                    >
                                    <Input
                                        id={`ubah-nama-${k.id}`}
                                        bind:value={ubah.nama}
                                    />
                                    {#if ubah.errors.nama}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubah.errors.nama}
                                        </p>{/if}
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-jenjang-${k.id}`}
                                        >Jenjang</Label
                                    >
                                    <Input
                                        id={`ubah-jenjang-${k.id}`}
                                        placeholder="MI / SMP"
                                        bind:value={ubah.jenjang}
                                    />
                                </div>
                                <div class="grid gap-1.5 sm:col-span-2">
                                    <Label for={`ubah-alamat-${k.id}`}
                                        >Alamat</Label
                                    >
                                    <Input
                                        id={`ubah-alamat-${k.id}`}
                                        bind:value={ubah.alamat}
                                    />
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id={`ubah-aktif-${k.id}`}
                                            bind:checked={ubah.is_active}
                                        />
                                        <Label for={`ubah-aktif-${k.id}`}
                                            >Kantor aktif</Label
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

        {#if tanpaKantor.length > 0 || lokasiBersama.length > 0}
            <p class="text-xs text-muted-foreground">
                {tanpaKantor.length} guru belum ditugaskan (atur di menu Akun staf)
                · {lokasiBersama.length} lokasi dipakai semua kantor (atur di Pengaturan).
            </p>
        {/if}
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Tambah kantor</h3>

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(e) => {
                e.preventDefault();
                baru.submit(kantorStore(), { onSuccess: () => baru.reset() });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="nama">Nama kantor</Label>
                <Input
                    id="nama"
                    placeholder="SMP Syekh Yusuf"
                    bind:value={baru.nama}
                />
                {#if baru.errors.nama}<p class="text-xs text-destructive">
                        {baru.errors.nama}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="jenjang">Jenjang</Label>
                <Input
                    id="jenjang"
                    placeholder="MI / SMP"
                    bind:value={baru.jenjang}
                />
            </div>
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="alamat">Alamat</Label>
                <Input id="alamat" bind:value={baru.alamat} />
            </div>
            <div class="sm:col-span-2">
                <Button type="submit" disabled={baru.processing}
                    >Tambah kantor</Button
                >
            </div>
        </form>
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
