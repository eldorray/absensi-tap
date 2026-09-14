<script module lang="ts">
    import { index } from '@/routes/admin/siswa';
    export const layout = {
        breadcrumbs: [{ title: 'Siswa', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import {
        impor as siswaImpor,
        template as siswaTemplate,
    } from '@/routes/admin/siswa';
    import {
        destroy as siswaDestroy,
        index as siswaIndex,
        store as siswaStore,
        update as siswaUpdate,
    } from '@/routes/admin/siswa';

    type S = {
        id: number;
        kantor_id: number;
        kantor: string | null;
        nis: string;
        nisn: string | null;
        nama: string;
        jenis_kelamin: string;
        jenis_kelamin_label: string;
        tanggal_lahir: string | null;
        is_active: boolean;
    };
    type Halaman<T> = {
        data: T[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    type Pilihan = { value: string; label: string };

    let {
        siswas,
        kelases,
        kantors,
        jenisKelamins,
        filter,
        hasilImpor,
    }: {
        siswas: Halaman<S>;
        kelases: { id: number; nama: string; kantor_id: number }[];
        kantors: { id: number; nama: string }[];
        jenisKelamins: Pilihan[];
        filter: { cari: string; kantor_id: number | null };
        hasilImpor: {
            dibuat: number;
            dilewati: number;
            galat: string[];
        } | null;
    } = $props();

    let dialogTambah = $state(false);
    let dialogImpor = $state(false);
    let dialogUbah = $state(false);
    let cari = $state(filter.cari);
    let unitFilter = $state(filter.kantor_id ?? 0);
    const impor = useForm({ kantor_id: 0, berkas: null as File | null });
    let diubah = $state<number | null>(null);
    let konfirmasi = $state<Konfirmasi | null>(null);

    const kosong = {
        kantor_id: kantors[0]?.id ?? 0,
        nis: '',
        nisn: '',
        nama: '',
        jenis_kelamin: jenisKelamins[0]?.value ?? 'L',
        tanggal_lahir: '',
        is_active: true,
    };

    const baru = useForm({ ...kosong, kelas_id: null as number | null });
    $effect(() => {
        if (
            !kelases.some(
                (k) => k.id === baru.kelas_id && k.kantor_id === baru.kantor_id,
            )
        ) {
            baru.kelas_id = null;
        }
    });
    const ubah = useForm({ ...kosong });

    function submitCari(event: Event): void {
        event.preventDefault();
        router.get(
            siswaIndex().url,
            { cari, kantor_id: unitFilter || undefined },
            { preserveState: true, replace: true },
        );
    }

    function mulaiUbah(s: S): void {
        ubah.kantor_id = s.kantor_id;
        ubah.nis = s.nis;
        ubah.nisn = s.nisn ?? '';
        ubah.nama = s.nama;
        ubah.jenis_kelamin = s.jenis_kelamin;
        ubah.tanggal_lahir = s.tanggal_lahir ?? '';
        ubah.is_active = s.is_active;
        ubah.clearErrors();
        diubah = s.id;
        dialogUbah = true;
    }

    function hapus(s: S): void {
        konfirmasi = {
            judul: `Hapus ${s.nama}?`,
            pesan: 'Kalau siswa ini sudah pernah masuk kelas, dia hanya dinonaktifkan supaya riwayat kehadirannya tidak ikut hilang.',
            aksi: () =>
                router.delete(siswaDestroy(s.id).url, {
                    preserveScroll: true,
                }),
        };
    }
</script>

<AppHead title="Siswa" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    {#each Object.values(page.props.errors) as error, i (i)}<p
            class="text-destructive"
            role="alert"
        >
            {error}
        </p>{/each}
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Siswa</h3>
            <p class="text-muted-foreground">
                Buku induk siswa per unit. Siswa tidak punya akun; yang melihat
                kehadirannya adalah orang tua yang ditautkan admin.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                onclick={() => {
                    baru.clearErrors();
                    dialogTambah = true;
                }}>Tambah siswa</Button
            ><Button
                type="button"
                variant="outline"
                onclick={() => {
                    impor.clearErrors();
                    dialogImpor = true;
                }}>Impor CSV</Button
            >
        </div>
        <form class="flex flex-wrap gap-2" onsubmit={submitCari}>
            <select
                aria-label="Filter unit"
                class="h-11 rounded-xl border border-border bg-background px-3"
                bind:value={unitFilter}
                ><option value={0}>Semua unit</option
                >{#each kantors as k (k.id)}<option value={k.id}
                        >{k.nama}</option
                    >{/each}</select
            >
            <Input
                class="min-w-48 flex-1"
                placeholder="Cari nama atau NIS"
                bind:value={cari}
                aria-label="Cari siswa"
            />
            <Button type="submit" variant="secondary">
                <Search class="size-4" aria-hidden="true" />
                Cari
            </Button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-muted-foreground"
                    ><tr
                        ><th class="px-4 py-3">Nama siswa</th><th
                            class="px-4 py-3">NIS / NISN</th
                        ><th class="px-4 py-3">Unit</th><th class="px-4 py-3"
                            >Jenis kelamin</th
                        ><th class="px-4 py-3">Status</th><th
                            class="px-4 py-3 text-right">Aksi</th
                        ></tr
                    ></thead
                >
                <tbody
                    >{#each siswas.data as s (s.id)}
                        <tr class="border-t border-border hover:bg-muted/50"
                            ><td class="px-4 py-3 font-semibold">{s.nama}</td
                            ><td class="px-4 py-3 font-mono"
                                >{s.nis}<span
                                    class="block text-xs text-muted-foreground"
                                    >{s.nisn ?? '—'}</span
                                ></td
                            ><td class="px-4 py-3">{s.kantor ?? '—'}</td><td
                                class="px-4 py-3">{s.jenis_kelamin_label}</td
                            ><td class="px-4 py-3"
                                ><Badge variant="secondary"
                                    >{s.is_active ? 'Aktif' : 'Nonaktif'}</Badge
                                ></td
                            ><td class="px-4 py-3"
                                ><div class="flex justify-end gap-1">
                                    <TombolIkon
                                        ikon={Pencil}
                                        nada="biru"
                                        label={`Ubah data ${s.nama}`}
                                        onclick={() => mulaiUbah(s)}
                                    /><TombolIkon
                                        ikon={Trash2}
                                        nada="merah"
                                        label={`Hapus ${s.nama}`}
                                        onclick={() => hapus(s)}
                                    />
                                </div></td
                            ></tr
                        >
                    {:else}<tr
                            ><td
                                colspan="6"
                                class="px-4 py-10 text-center text-muted-foreground"
                                >Belum ada siswa yang cocok. Tambahkan siswa
                                atau impor CSV.</td
                            ></tr
                        >{/each}</tbody
                >
            </table>
        </div>
        <nav
            class="flex flex-wrap items-center gap-1"
            aria-label="Halaman daftar siswa"
        >
            {#each siswas.links as tautan (tautan.label)}
                {#if tautan.url}
                    <Link
                        href={toUrl(tautan.url)}
                        preserveScroll
                        class="min-h-11 min-w-11 rounded-xl px-3 py-2 text-sm font-semibold {tautan.active
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-muted'}"
                        aria-current={tautan.active ? 'page' : undefined}
                    >
                        {tautan.label.includes('Previous') ||
                        tautan.label.includes('laquo')
                            ? 'Sebelumnya'
                            : tautan.label.includes('Next') ||
                                tautan.label.includes('raquo')
                              ? 'Berikutnya'
                              : tautan.label}
                    </Link>
                {/if}
            {/each}
            <span class="ml-auto text-xs text-muted-foreground"
                >{siswas.total} siswa</span
            >
        </nav>
    </section>

    <Dialog bind:open={dialogTambah}
        ><DialogContent class="max-h-[85dvh] overflow-y-auto"
            ><DialogTitle>Tambah siswa</DialogTitle
            >{#each Object.values(baru.errors) as error, i (i)}<p
                    role="alert"
                    class="text-destructive"
                >
                    {error}
                </p>{/each}

            <form
                class="grid gap-3 sm:grid-cols-2"
                onsubmit={(e) => {
                    e.preventDefault();
                    baru.submit(siswaStore(), {
                        onSuccess: () => {
                            baru.reset();
                            dialogTambah = false;
                        },
                    });
                }}
            >
                <div class="grid gap-1.5">
                    <Label for="nama">Nama lengkap</Label>
                    <Input
                        id="nama"
                        placeholder="Aisyah Putri"
                        bind:value={baru.nama}
                    />
                    {#if baru.errors.nama}<p class="text-xs text-destructive">
                            {baru.errors.nama}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="kelas-siswa">Kelas</Label>
                    <select
                        id="kelas-siswa"
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={baru.kelas_id}
                    >
                        <option value={null}>Belum ditempatkan</option>
                        {#each kelases.filter((k) => k.kantor_id === baru.kantor_id) as k (k.id)}<option
                                value={k.id}>{k.nama}</option
                            >{/each}
                    </select>
                    <Label for="nis">NIS</Label>
                    <Input
                        id="nis"
                        placeholder="20260001"
                        bind:value={baru.nis}
                    />
                    {#if baru.errors.nis}<p class="text-xs text-destructive">
                            {baru.errors.nis}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="nisn"
                        >NISN <span class="text-muted-foreground"
                            >(opsional)</span
                        ></Label
                    >
                    <Input id="nisn" bind:value={baru.nisn} />
                    {#if baru.errors.nisn}<p class="text-xs text-destructive">
                            {baru.errors.nisn}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="jk">Jenis kelamin</Label>
                    <select
                        id="jk"
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={baru.jenis_kelamin}
                    >
                        {#each jenisKelamins as jk (jk.value)}
                            <option value={jk.value}>{jk.label}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="lahir">Tanggal lahir</Label>
                    <Input
                        id="lahir"
                        type="date"
                        bind:value={baru.tanggal_lahir}
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="kantor">Unit</Label>
                    <select
                        id="kantor"
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={baru.kantor_id}
                    >
                        {#each kantors as k (k.id)}
                            <option value={k.id}>{k.nama}</option>
                        {/each}
                    </select>
                    {#if baru.errors.kantor_id}<p
                            class="text-xs text-destructive"
                        >
                            {baru.errors.kantor_id}
                        </p>{/if}
                </div>
                <div class="sm:col-span-2">
                    <Button type="submit" disabled={baru.processing}
                        >Tambah siswa</Button
                    >
                </div>
            </form>
        </DialogContent></Dialog
    >
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
<Dialog bind:open={dialogImpor}
    ><DialogContent class="max-h-[85dvh] overflow-y-auto"
        ><DialogTitle>Impor dari CSV</DialogTitle>
        <p>
            Kolom wajib: nama, nis, dan kelas. Nama kelas harus sama persis
            dengan kelas aktif pada unit dan tahun ajaran yang dipilih. Jika ada
            nama kelas salah, seluruh impor dibatalkan. Maksimal 1 MB dan 1.000
            baris. Jenis kelamin kosong menggunakan L; periksa buku induk
            sebelum mengunggah.
        </p>
        <form
            class="grid gap-3"
            onsubmit={(e) => {
                e.preventDefault();
                impor.submit(siswaImpor(), { forceFormData: true });
            }}
        >
            <Label for="impor-unit">Unit tujuan</Label><select
                id="impor-unit"
                class="h-11 rounded-xl border border-border bg-background px-3"
                bind:value={impor.kantor_id}
                ><option value={0}>Pilih unit</option
                >{#each kantors as k (k.id)}<option value={k.id}
                        >{k.nama}</option
                    >{/each}</select
            >
            <Label for="impor-berkas">Berkas CSV</Label><input
                id="impor-berkas"
                class="min-h-11 max-w-full"
                type="file"
                accept=".csv,text/csv,text/plain"
                onchange={(e) =>
                    (impor.berkas = e.currentTarget.files?.[0] ?? null)}
            />
            {#each Object.values(impor.errors) as error, i (i)}<p
                    class="text-destructive"
                >
                    {error}
                </p>{/each}
            <Button type="submit" class="min-h-14" disabled={impor.processing}
                >Unggah dan impor</Button
            >
            <a
                class="inline-flex min-h-11 items-center text-primary underline"
                href={siswaTemplate().url}>Unduh template CSV</a
            >
        </form>
        {#if hasilImpor}<div role="status">
                <p>
                    {hasilImpor.dibuat} siswa dibuat · {hasilImpor.dilewati} dilewati
                </p>
                <ul>
                    {#each hasilImpor.galat as galat, i (i)}<li>
                            {galat}
                        </li>{/each}
                </ul>
            </div>{/if}
    </DialogContent></Dialog
>
<Dialog bind:open={dialogUbah}
    ><DialogContent class="max-h-[85dvh] overflow-y-auto"
        ><DialogTitle>Ubah siswa</DialogTitle
        >{#each Object.values(ubah.errors) as error, i (i)}<p
                role="alert"
                class="text-destructive"
            >
                {error}
            </p>{/each}{#if diubah}
            <form
                class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2"
                onsubmit={(e) => {
                    e.preventDefault();
                    ubah.submit(siswaUpdate(diubah!), {
                        preserveScroll: true,
                        onSuccess: () => {
                            dialogUbah = false;
                            diubah = null;
                        },
                    });
                }}
            >
                <div class="grid gap-1.5">
                    <Label for={`ubah-nama-${diubah!}`}>Nama lengkap</Label>
                    <Input id={`ubah-nama-${diubah!}`} bind:value={ubah.nama} />
                    {#if ubah.errors.nama}<p class="text-xs text-destructive">
                            {ubah.errors.nama}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for={`ubah-nis-${diubah!}`}>NIS</Label>
                    <Input id={`ubah-nis-${diubah!}`} bind:value={ubah.nis} />
                    {#if ubah.errors.nis}<p class="text-xs text-destructive">
                            {ubah.errors.nis}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for={`ubah-nisn-${diubah!}`}>NISN</Label>
                    <Input id={`ubah-nisn-${diubah!}`} bind:value={ubah.nisn} />
                    {#if ubah.errors.nisn}<p class="text-xs text-destructive">
                            {ubah.errors.nisn}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for={`ubah-jk-${diubah!}`}>Jenis kelamin</Label>
                    <select
                        id={`ubah-jk-${diubah!}`}
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={ubah.jenis_kelamin}
                    >
                        {#each jenisKelamins as jk (jk.value)}
                            <option value={jk.value}>{jk.label}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for={`ubah-lahir-${diubah!}`}>Tanggal lahir</Label>
                    <Input
                        id={`ubah-lahir-${diubah!}`}
                        type="date"
                        bind:value={ubah.tanggal_lahir}
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for={`ubah-kantor-${diubah!}`}>Unit</Label>
                    <select
                        id={`ubah-kantor-${diubah!}`}
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={ubah.kantor_id}
                    >
                        {#each kantors as k (k.id)}
                            <option value={k.id}>{k.nama}</option>
                        {/each}
                    </select>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2"
                >
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id={`ubah-aktif-${diubah!}`}
                            bind:checked={ubah.is_active}
                        />
                        <Label for={`ubah-aktif-${diubah!}`}>Siswa aktif</Label>
                    </div>
                    <Button type="submit" disabled={ubah.processing}
                        >Simpan perubahan</Button
                    >
                </div>
            </form>
        {/if}</DialogContent
    ></Dialog
>
