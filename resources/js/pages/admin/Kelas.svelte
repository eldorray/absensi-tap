<script module lang="ts">
    import { index } from '@/routes/admin/kelas';
    export const layout = { breadcrumbs: [{ title: 'Kelas', href: index() }] };
</script>

<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { store, update, destroy } from '@/routes/admin/kelas';
    import * as anggota from '@/routes/admin/kelas/anggota';
    import * as pengganti from '@/routes/admin/kelas/pengganti';
    type K = {
        id: number;
        kantor_id: number;
        nama: string;
        tingkat: number;
        wali_kelas_id: number | null;
        wali_kelas: string | null;
        kantor: string | null;
        is_active: boolean;
        jumlah_anggota: number;
    };
    type A = {
        id: number;
        nama: string;
        nis: string;
        tanggal_mulai: string;
        tanggal_selesai: string | null;
        is_active: boolean;
    };
    type P = {
        id: number;
        user_id: number;
        nama: string;
        tanggal_mulai: string | null;
        tanggal_selesai: string | null;
    };
    let {
        kelas,
        terpilih,
        kantors,
        gurus,
        hariIni,
    }: {
        kelas: K[];
        terpilih: {
            id: number;
            nama: string;
            anggotas: A[];
            penggantis: P[];
            calonSiswas: { id: number; nama: string; nis: string }[];
        } | null;
        kantors: { id: number; nama: string }[];
        gurus: { id: number; name: string }[];
        hariIni: string;
    } = $props();
    let dialogKelas = $state(false);
    let dialogAnggota = $state(false);
    let dialogPengganti = $state(false);
    let diubah = $state<number | null>(null);
    let konfirmasi = $state<Konfirmasi | null>(null);
    const form = useForm({
        kantor_id: 0,
        nama: '',
        tingkat: 1,
        wali_kelas_id: null as number | null,
        is_active: true,
    });
    const tambah = useForm({
        siswa_ids: [] as number[],
        tanggal_mulai: hariIni,
    });
    const tugas = useForm({
        user_id: null as number | null,
        tanggal_mulai: '',
        tanggal_selesai: '',
    });
    function edit(k: K): void {
        diubah = k.id;
        dialogKelas = true;
        form.kantor_id = k.kantor_id;
        form.nama = k.nama;
        form.tingkat = k.tingkat;
        form.wali_kelas_id = k.wali_kelas_id;
        form.is_active = k.is_active;
        form.clearErrors();
    }
    function konfirm(judul: string, pesan: string, url: string): void {
        konfirmasi = {
            judul,
            pesan,
            aksi: () => router.delete(url, { preserveScroll: true }),
        };
    }
</script>

<AppHead title="Kelas" />
<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <h3>Kelas</h3>
        <p class="text-muted-foreground">
            Kelas dan riwayat anggota untuk tahun ajaran yang sedang dilihat.
        </p>
        {#each Object.values(page.props.errors) as error, i (i)}<p
                class="text-destructive"
                role="alert"
            >
                {error}
            </p>{/each}
        <Button
            type="button"
            onclick={() => {
                diubah = null;
                form.reset();
                form.clearErrors();
                dialogKelas = true;
            }}>Tambah kelas</Button
        >
        <div class="overflow-x-auto rounded-2xl border border-border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted text-muted-foreground"
                    ><tr
                        ><th class="px-4 py-3">Kelas</th><th class="px-4 py-3"
                            >Tingkat</th
                        ><th class="px-4 py-3">Unit</th><th class="px-4 py-3"
                            >Wali kelas</th
                        ><th class="px-4 py-3">Anggota</th><th class="px-4 py-3"
                            >Status</th
                        ><th class="px-4 py-3">Aksi</th></tr
                    ></thead
                ><tbody>
                    {#each kelas as k (k.id)}<tr
                            class="border-t border-border hover:bg-muted/50"
                            ><td class="px-4 py-3 font-semibold"
                                ><Link
                                    href={terpilih?.id === k.id
                                        ? index()
                                        : index({ query: { kelas: k.id } })}
                                    class="inline-flex min-h-11 items-center text-primary"
                                    >{k.nama}</Link
                                ></td
                            ><td class="px-4 py-3">{k.tingkat}</td><td
                                class="px-4 py-3">{k.kantor ?? '—'}</td
                            ><td class="px-4 py-3"
                                >{k.wali_kelas ?? 'Belum ada wali'}</td
                            ><td class="px-4 py-3">{k.jumlah_anggota}</td><td
                                class="px-4 py-3"
                                >{k.is_active ? 'Aktif' : 'Nonaktif'}</td
                            ><td class="px-4 py-3"
                                ><div class="flex gap-2">
                                    <TombolIkon
                                        ikon={Pencil}
                                        nada="biru"
                                        label={`Ubah kelas ${k.nama}`}
                                        onclick={() => edit(k)}
                                    /><TombolIkon
                                        ikon={Trash2}
                                        nada="merah"
                                        label={`Hapus kelas ${k.nama}`}
                                        onclick={() =>
                                            konfirm(
                                                `Hapus kelas ${k.nama}?`,
                                                'Kelas dengan riwayat anggota hanya dinonaktifkan.',
                                                destroy(k.id).url,
                                            )}
                                    />
                                </div></td
                            ></tr
                        >{:else}<tr
                            ><td
                                colspan="7"
                                class="px-4 py-10 text-center text-muted-foreground"
                                >Belum ada kelas di tahun ajaran ini.</td
                            ></tr
                        >{/each}
                </tbody>
            </table>
        </div>
    </section>
    <Dialog bind:open={dialogKelas}
        ><DialogContent class="max-h-[85dvh] overflow-y-auto"
            ><DialogTitle>{diubah ? 'Ubah kelas' : 'Tambah kelas'}</DialogTitle
            >{#each Object.values(form.errors) as error, i (i)}<p
                    role="alert"
                    class="text-destructive"
                >
                    {error}
                </p>{/each}
            <form
                class="grid gap-3 sm:grid-cols-2"
                onsubmit={(e) => {
                    e.preventDefault();
                    form.submit(diubah ? update(diubah) : store(), {
                        onSuccess: () => {
                            form.reset();
                            diubah = null;
                            dialogKelas = false;
                        },
                    });
                }}
            >
                <div class="grid gap-2">
                    <Label for="kelas-nama">Nama kelas</Label><Input
                        id="kelas-nama"
                        bind:value={form.nama}
                        required
                        maxlength={30}
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="kelas-tingkat">Tingkat</Label><Input
                        id="kelas-tingkat"
                        type="number"
                        min={1}
                        max={12}
                        bind:value={form.tingkat}
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="kelas-unit">Unit</Label><select
                        id="kelas-unit"
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={form.kantor_id}
                        ><option value={0}>Pilih unit</option
                        >{#each kantors as k (k.id)}<option value={k.id}
                                >{k.nama}</option
                            >{/each}</select
                    >
                </div>
                <div class="grid gap-2">
                    <Label for="kelas-wali">Wali kelas</Label><select
                        id="kelas-wali"
                        class="h-11 rounded-xl border border-border bg-background px-3"
                        bind:value={form.wali_kelas_id}
                        ><option value={null}>Tanpa wali</option
                        >{#each gurus as g (g.id)}<option value={g.id}
                                >{g.name}</option
                            >{/each}</select
                    >
                </div>
                <div class="flex min-h-11 items-center gap-2">
                    <Checkbox
                        id="kelas-aktif"
                        bind:checked={form.is_active}
                    /><Label for="kelas-aktif">Kelas aktif</Label>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="submit"
                        class="min-h-14"
                        disabled={form.processing}>Simpan kelas</Button
                    >{#if diubah}<Button
                            onclick={() => {
                                diubah = null;
                                dialogKelas = false;
                                form.reset();
                                form.clearErrors();
                            }}>Batal</Button
                        >{/if}
                </div>
            </form>
        </DialogContent></Dialog
    >
    {#if terpilih}
        <section class="g-tile g-tone-plain">
            <h3>Anggota kelas {terpilih.nama}</h3>
            <div class="overflow-x-auto rounded-2xl border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted"
                        ><tr
                            ><th class="px-4 py-3">NIS</th><th class="px-4 py-3"
                                >Nama</th
                            ><th class="px-4 py-3">Mulai</th><th
                                class="px-4 py-3">Selesai</th
                            ><th class="px-4 py-3">Aksi</th></tr
                        ></thead
                    ><tbody
                        >{#each terpilih.anggotas as a (a.id)}<tr
                                class="border-t border-border"
                                ><td class="px-4 py-3">{a.nis}</td><td
                                    class="px-4 py-3">{a.nama}</td
                                ><td class="px-4 py-3">{a.tanggal_mulai}</td><td
                                    class="px-4 py-3"
                                    >{a.tanggal_selesai ?? 'Aktif'}</td
                                ><td class="px-4 py-3"
                                    >{#if !a.tanggal_selesai}<TombolIkon
                                            ikon={Trash2}
                                            nada="merah"
                                            label={`Keluarkan ${a.nama}`}
                                            onclick={() => {
                                                if (terpilih) {
                                                    konfirm(
                                                        `Keluarkan ${a.nama}?`,
                                                        'Keanggotaan ditutup hari ini tanpa menghapus riwayat.',
                                                        anggota.destroy([
                                                            terpilih.id,
                                                            a.id,
                                                        ]).url,
                                                    );
                                                }
                                            }}
                                        />{/if}
                                </td></tr
                            >{:else}<tr
                                ><td colspan="5" class="px-4 py-8 text-center"
                                    >Belum ada anggota.</td
                                ></tr
                            >{/each}</tbody
                    >
                </table>
            </div>
            <Button
                type="button"
                onclick={() => {
                    tambah.clearErrors();
                    dialogAnggota = true;
                }}>Tambah siswa ke kelas</Button
            >
            <Dialog bind:open={dialogAnggota}
                ><DialogContent class="max-h-[85dvh] overflow-y-auto"
                    ><DialogTitle>Tambah siswa ke kelas</DialogTitle
                    >{#each Object.values(tambah.errors) as error, i (i)}<p
                            role="alert"
                            class="text-destructive"
                        >
                            {error}
                        </p>{/each}
                    <form
                        class="grid gap-3"
                        onsubmit={(e) => {
                            e.preventDefault();

                            if (terpilih) {
                                tambah.submit(anggota.store(terpilih.id), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        tambah.reset('siswa_ids');
                                        dialogAnggota = false;
                                    },
                                });
                            }
                        }}
                    >
                        <fieldset class="grid gap-2">
                            <legend class="mb-1 font-medium"
                                >Pilih siswa yang belum memiliki kelas</legend
                            >
                            <div
                                class="max-h-72 overflow-y-auto rounded-2xl border border-border"
                            >
                                {#each terpilih.calonSiswas as s (s.id)}
                                    <label
                                        class="flex min-h-12 items-center gap-3 border-b border-border px-3 py-2 last:border-b-0 hover:bg-muted/60"
                                    >
                                        <input
                                            type="checkbox"
                                            value={s.id}
                                            bind:group={tambah.siswa_ids}
                                            class="size-5 accent-primary"
                                        />
                                        <span
                                            ><span class="block font-medium"
                                                >{s.nama}</span
                                            ><span
                                                class="font-mono text-xs text-muted-foreground"
                                                >NIS {s.nis}</span
                                            ></span
                                        >
                                    </label>
                                {:else}<p
                                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        Semua siswa aktif pada unit ini sudah
                                        memiliki kelas.
                                    </p>{/each}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {tambah.siswa_ids.length} siswa dipilih
                            </p>
                        </fieldset>
                        <Label for="anggota-mulai">Tanggal mulai</Label><Input
                            id="anggota-mulai"
                            type="date"
                            bind:value={tambah.tanggal_mulai}
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            Penempatan menutup keanggotaan sebelumnya sehari
                            sebelum tanggal mulai.
                        </p>
                        <Button
                            type="submit"
                            class="min-h-14"
                            disabled={tambah.processing ||
                                tambah.siswa_ids.length === 0}
                            >Tempatkan siswa</Button
                        >
                    </form>
                </DialogContent></Dialog
            >
        </section>
        <section class="g-tile g-tone-plain">
            <h3>Guru pengganti</h3>
            <ul class="grid gap-2">
                {#each terpilih.penggantis as p (p.id)}<li
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p>{p.nama}</p>
                            <p class="text-sm text-muted-foreground">
                                {p.tanggal_mulai ?? 'Tanpa batas awal'} – {p.tanggal_selesai ??
                                    'Tanpa batas akhir'}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <TombolIkon
                                ikon={Pencil}
                                nada="biru"
                                label={`Ubah penugasan ${p.nama}`}
                                onclick={() => {
                                    dialogPengganti = true;
                                    tugas.clearErrors();
                                    tugas.user_id = p.user_id;
                                    tugas.tanggal_mulai = p.tanggal_mulai ?? '';
                                    tugas.tanggal_selesai =
                                        p.tanggal_selesai ?? '';
                                }}
                            /><TombolIkon
                                ikon={Trash2}
                                nada="merah"
                                label={`Cabut ${p.nama}`}
                                onclick={() => {
                                    if (terpilih) {
                                        konfirm(
                                            `Cabut penugasan ${p.nama}?`,
                                            'Akses sebagai pengganti untuk kelas ini dicabut.',
                                            pengganti.destroy([
                                                terpilih.id,
                                                p.id,
                                            ]).url,
                                        );
                                    }
                                }}
                            />
                        </div>
                    </li>{:else}<li>Belum ada guru pengganti.</li>{/each}
            </ul>
            <Button
                type="button"
                onclick={() => {
                    tugas.clearErrors();
                    dialogPengganti = true;
                }}>Penugasan guru pengganti</Button
            >
            <Dialog bind:open={dialogPengganti}
                ><DialogContent class="max-h-[85dvh] overflow-y-auto"
                    ><DialogTitle>Penugasan guru pengganti</DialogTitle
                    >{#each Object.values(tugas.errors) as error, i (i)}<p
                            role="alert"
                            class="text-destructive"
                        >
                            {error}
                        </p>{/each}
                    <form
                        class="grid gap-3"
                        onsubmit={(e) => {
                            e.preventDefault();

                            if (terpilih) {
                                tugas.submit(pengganti.store(terpilih.id), {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        tugas.reset();
                                        dialogPengganti = false;
                                    },
                                });
                            }
                        }}
                    >
                        <Label for="pengganti-guru">Guru</Label><select
                            id="pengganti-guru"
                            class="h-11 rounded-xl border border-border bg-background px-3"
                            bind:value={tugas.user_id}
                            ><option value={null}>Pilih guru</option
                            >{#each gurus as g (g.id)}<option value={g.id}
                                    >{g.name}</option
                                >{/each}</select
                        >
                        <Label for="pengganti-mulai"
                            >Tanggal mulai (opsional)</Label
                        ><Input
                            id="pengganti-mulai"
                            type="date"
                            bind:value={tugas.tanggal_mulai}
                        />
                        <Label for="pengganti-selesai"
                            >Tanggal selesai (opsional)</Label
                        ><Input
                            id="pengganti-selesai"
                            type="date"
                            bind:value={tugas.tanggal_selesai}
                        />
                        <Button
                            type="submit"
                            class="min-h-14"
                            disabled={tugas.processing}>Simpan penugasan</Button
                        >
                    </form>
                </DialogContent></Dialog
            >
        </section>
    {/if}
</div>
<KonfirmasiDialog bind:permintaan={konfirmasi} />
