<script module lang="ts">
    import { index } from '@/routes/admin/tahun-ajaran';
    export const layout = {
        breadcrumbs: [{ title: 'Tahun ajaran', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import Eye from 'lucide-svelte/icons/eye';
    import Pencil from 'lucide-svelte/icons/pencil';
    import X from 'lucide-svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        aktifkan as tahunAktifkan,
        lihat as tahunLihat,
        store as tahunStore,
        update as tahunUpdate,
    } from '@/routes/admin/tahun-ajaran';

    type T = {
        id: number;
        nama: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        is_active: boolean;
        jumlah: Record<string, number>;
    };

    let {
        tahunAjarans,
        dilihat,
    }: { tahunAjarans: T[]; dilihat: number | null } = $props();

    const baru = useForm({ nama: '', tanggal_mulai: '', tanggal_selesai: '' });
    const ubah = useForm({ nama: '', tanggal_mulai: '', tanggal_selesai: '' });

    let diubah = $state<number | null>(null);

    const labelJumlah: Record<string, string> = {
        absensi: 'absensi',
        izin: 'izin',
        jadwal: 'jadwal',
        hari_libur: 'hari libur',
        pengumuman: 'pengumuman',
    };

    function mulaiUbah(t: T): void {
        ubah.nama = t.nama;
        ubah.tanggal_mulai = t.tanggal_mulai;
        ubah.tanggal_selesai = t.tanggal_selesai;
        ubah.clearErrors();
        diubah = t.id;
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function aktifkan(t: T): void {
        konfirmasi = {
            judul: `Aktifkan tahun ajaran ${t.nama}?`,
            pesan: 'Absensi, izin, jadwal, hari libur, dan pengumuman mulai dari kosong. Data tahun sebelumnya tetap tersimpan dan bisa dibuka lewat tombol Lihat.',
            label: 'Aktifkan',
            destruktif: false,
            aksi: () =>
                router.post(
                    tahunAktifkan(t.id).url,
                    {},
                    { preserveScroll: true },
                ),
        };
    }

    function lihat(t: T): void {
        router.post(tahunLihat(t.id).url, {}, { preserveScroll: true });
    }

    function isiJumlah(t: T): string {
        const isi = Object.entries(t.jumlah).filter(([, jumlah]) => jumlah > 0);

        return isi.length === 0
            ? 'Belum ada data'
            : isi
                  .map(
                      ([kunci, jumlah]) =>
                          `${jumlah} ${labelJumlah[kunci] ?? kunci}`,
                  )
                  .join(' · ');
    }
</script>

<AppHead title="Tahun ajaran" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Tahun ajaran</h3>
            <p class="text-muted-foreground">
                Absensi, izin, jadwal kerja, hari libur, dan pengumuman terikat
                ke satu tahun ajaran. Guru, role, kantor, lokasi, dan aturan jam
                absen tidak ikut berganti.
            </p>
        </div>

        <ul class="grid gap-2">
            {#each tahunAjarans as t (t.id)}
                <li class="rounded-2xl border border-border px-4 py-3">
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{t.nama}</p>
                                {#if t.is_active}
                                    <Badge>Aktif</Badge>
                                {/if}
                                {#if dilihat === t.id && !t.is_active}
                                    <Badge variant="secondary"
                                        >Sedang dilihat</Badge
                                    >
                                {/if}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {t.tanggal_mulai} sampai {t.tanggal_selesai} · {isiJumlah(
                                    t,
                                )}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <TombolIkon
                                ikon={Eye}
                                label={dilihat === t.id
                                    ? `Sedang melihat ${t.nama}`
                                    : `Lihat data ${t.nama}`}
                                disabled={dilihat === t.id}
                                onclick={() => lihat(t)}
                            />
                            <TombolIkon
                                ikon={diubah === t.id ? X : Pencil}
                                nada="biru"
                                label={diubah === t.id
                                    ? 'Tutup form'
                                    : `Ubah tahun ajaran ${t.nama}`}
                                onclick={() =>
                                    diubah === t.id
                                        ? (diubah = null)
                                        : mulaiUbah(t)}
                            />
                            {#if !t.is_active}
                                <TombolIkon
                                    ikon={CircleCheck}
                                    nada="hijau"
                                    label={`Aktifkan tahun ajaran ${t.nama}`}
                                    onclick={() => aktifkan(t)}
                                />
                            {/if}
                        </div>
                    </div>

                    {#if diubah === t.id}
                        <form
                            class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-3"
                            onsubmit={(e) => {
                                e.preventDefault();
                                ubah.submit(tahunUpdate(t.id), {
                                    preserveScroll: true,
                                    onSuccess: () => (diubah = null),
                                });
                            }}
                        >
                            <div class="grid gap-1.5">
                                <Label for={`ubah-nama-${t.id}`}>Nama</Label>
                                <Input
                                    id={`ubah-nama-${t.id}`}
                                    bind:value={ubah.nama}
                                />
                                {#if ubah.errors.nama}<p
                                        class="text-xs text-destructive"
                                    >
                                        {ubah.errors.nama}
                                    </p>{/if}
                            </div>
                            <div class="grid gap-1.5">
                                <Label for={`ubah-mulai-${t.id}`}>Mulai</Label>
                                <Input
                                    id={`ubah-mulai-${t.id}`}
                                    type="date"
                                    bind:value={ubah.tanggal_mulai}
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for={`ubah-selesai-${t.id}`}
                                    >Selesai</Label
                                >
                                <Input
                                    id={`ubah-selesai-${t.id}`}
                                    type="date"
                                    bind:value={ubah.tanggal_selesai}
                                />
                                {#if ubah.errors.tanggal_selesai}<p
                                        class="text-xs text-destructive"
                                    >
                                        {ubah.errors.tanggal_selesai}
                                    </p>{/if}
                            </div>
                            <div class="sm:col-span-3">
                                <Button type="submit" disabled={ubah.processing}
                                    >Simpan perubahan</Button
                                >
                            </div>
                        </form>
                    {/if}
                </li>
            {/each}
        </ul>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Tambah tahun ajaran</h3>
            <p class="text-muted-foreground">
                Tahun baru dibuat non-aktif. Aktifkan setelah siap.
            </p>
        </div>

        <form
            class="grid gap-3 sm:grid-cols-3"
            onsubmit={(e) => {
                e.preventDefault();
                baru.submit(tahunStore(), { onSuccess: () => baru.reset() });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="nama">Nama</Label>
                <Input
                    id="nama"
                    placeholder="2027/2028"
                    bind:value={baru.nama}
                />
                {#if baru.errors.nama}<p class="text-xs text-destructive">
                        {baru.errors.nama}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="mulai">Mulai</Label>
                <Input id="mulai" type="date" bind:value={baru.tanggal_mulai} />
                {#if baru.errors.tanggal_mulai}<p
                        class="text-xs text-destructive"
                    >
                        {baru.errors.tanggal_mulai}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="selesai">Selesai</Label>
                <Input
                    id="selesai"
                    type="date"
                    bind:value={baru.tanggal_selesai}
                />
                {#if baru.errors.tanggal_selesai}<p
                        class="text-xs text-destructive"
                    >
                        {baru.errors.tanggal_selesai}
                    </p>{/if}
            </div>
            <div class="sm:col-span-3">
                <Button type="submit" disabled={baru.processing}
                    >Tambah tahun ajaran</Button
                >
            </div>
        </form>
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
