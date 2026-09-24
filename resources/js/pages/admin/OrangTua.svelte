<script module lang="ts">
    import { index } from '@/routes/admin/orang-tua';

    export const layout = {
        breadcrumbs: [{ title: 'Orang tua', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Power from 'lucide-svelte/icons/power';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Upload from 'lucide-svelte/icons/upload';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import UsersRound from 'lucide-svelte/icons/users-round';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        destroy,
        impor as orangTuaImpor,
        resetPassword,
        store,
        template as orangTuaTemplate,
        update,
    } from '@/routes/admin/orang-tua';

    type Anak = { id: number; nama: string; nis: string };
    type OrangTua = {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        anak: Anak[];
    };
    type PasswordBaru = { nama: string; email: string; password: string };
    type AkunImpor = { nama: string; email: string; password: string };
    type HasilImpor = {
        dibuat: number;
        dilewati: number;
        galat: string[];
        akun: AkunImpor[];
    };

    let {
        orangTuas,
        hasilImpor = null,
        passwordBaru = null,
    }: {
        orangTuas: OrangTua[];
        hasilImpor?: HasilImpor | null;
        passwordBaru?: PasswordBaru | null;
    } = $props();

    const tambah = useForm({ name: '', email: '', password: '' });
    const impor = useForm<{ berkas: File | null }>({ berkas: null });
    let dialogImpor = $state(false);
    const ubah = useForm({ name: '', email: '' });
    let dialogTambah = $state(false);
    let diubah = $state<OrangTua | null>(null);
    let cari = $state('');
    let konfirmasi = $state<Konfirmasi | null>(null);
    let passwordDitutup = $state(false);
    const passwordTampil = $derived(passwordDitutup ? null : passwordBaru);
    const terfilter = $derived(
        orangTuas.filter((orangTua) =>
            `${orangTua.name} ${orangTua.email} ${orangTua.anak.map((anak) => anak.nama).join(' ')}`
                .toLowerCase()
                .includes(cari.trim().toLowerCase()),
        ),
    );

    function unduhAkun(): void {
        if (!hasilImpor || hasilImpor.akun.length === 0) {
            return;
        }

        const csv = [
            ['Nama', 'Email', 'Password'],
            ...hasilImpor.akun.map((akun) => [
                akun.nama,
                akun.email,
                akun.password,
            ]),
        ]
            .map((baris) =>
                baris
                    .map((nilai) => {
                        const aman = /^[=+\-@\t\r]/.test(nilai)
                            ? `'${nilai}`
                            : nilai;

                        return `"${aman.replaceAll('"', '""')}"`;
                    })
                    .join(','),
            )
            .join('\r\n');
        const tautan = document.createElement('a');
        tautan.href = URL.createObjectURL(
            new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }),
        );
        tautan.download = 'akun-orang-tua-baru.csv';
        tautan.click();
        URL.revokeObjectURL(tautan.href);
    }

    function mulaiUbah(orangTua: OrangTua): void {
        ubah.name = orangTua.name;
        ubah.email = orangTua.email;
        ubah.clearErrors();
        diubah = orangTua;
    }

    function ubahStatus(orangTua: OrangTua): void {
        const kirim = (): void => {
            router.patch(
                update(orangTua.id).url,
                { is_active: !orangTua.is_active },
                { preserveScroll: true },
            );
        };

        if (!orangTua.is_active) {
            kirim();

            return;
        }

        konfirmasi = {
            judul: `Nonaktifkan ${orangTua.name}?`,
            pesan: 'Akun ini tidak dapat masuk ke portal orang tua sampai diaktifkan kembali. Tautan siswa dan riwayat absensi tetap tersimpan.',
            label: 'Nonaktifkan',
            aksi: kirim,
        };
    }

    function reset(orangTua: OrangTua): void {
        passwordDitutup = false;
        konfirmasi = {
            judul: `Reset password ${orangTua.name}?`,
            pesan: 'Password lama langsung tidak berlaku. Password baru ditampilkan satu kali setelah proses selesai.',
            label: 'Reset password',
            aksi: () =>
                router.post(
                    resetPassword(orangTua.id).url,
                    {},
                    { preserveScroll: true },
                ),
        };
    }

    function hapus(orangTua: OrangTua): void {
        konfirmasi = {
            judul: `Hapus akun ${orangTua.name}?`,
            pesan: 'Akun dan seluruh tautannya ke siswa akan dihapus. Data siswa dan riwayat absensi tidak ikut terhapus.',
            aksi: () =>
                router.delete(destroy(orangTua.id).url, {
                    preserveScroll: true,
                }),
        };
    }
</script>

<AppHead title="Orang tua" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Akun orang tua</h3>
                <p class="text-muted-foreground">
                    {orangTuas.length} akun wali murid. Penautan anak tetap dilakukan
                    dari menu Siswa.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (dialogImpor = true)}
                    data-test="buka-impor-orang-tua"
                >
                    <Upload class="size-4" aria-hidden="true" />
                    Upload CSV
                </Button>
                <Button type="button" onclick={() => (dialogTambah = true)}>
                    <UserPlus class="size-4" aria-hidden="true" />
                    Tambah orang tua
                </Button>
            </div>
        </div>

        {#if hasilImpor}
            <div class="grid gap-2 rounded-2xl border border-border px-4 py-3">
                <p class="text-sm font-semibold">
                    Impor terakhir: {hasilImpor.dibuat} dibuat · {hasilImpor.dilewati}
                    dilewati
                </p>
                {#if hasilImpor.galat.length > 0}
                    <ul class="grid gap-1">
                        {#each hasilImpor.galat as galat (galat)}
                            <li class="text-xs text-destructive">{galat}</li>
                        {/each}
                    </ul>
                {/if}
                {#if hasilImpor.akun.length > 0}
                    <div class="grid gap-2 border-t border-border pt-2">
                        <p class="text-xs text-muted-foreground">
                            Password yang dibuat otomatis hanya tampil sekali.
                            Unduh atau catat sebelum meninggalkan halaman ini.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="text-left text-muted-foreground">
                                        <th class="px-2 py-1 font-medium"
                                            >Nama</th
                                        >
                                        <th class="px-2 py-1 font-medium"
                                            >Email</th
                                        >
                                        <th class="px-2 py-1 font-medium"
                                            >Password</th
                                        >
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each hasilImpor.akun as akun (akun.email)}
                                        <tr class="border-t border-border/60">
                                            <td class="px-2 py-1"
                                                >{akun.nama}</td
                                            >
                                            <td class="px-2 py-1 font-mono"
                                                >{akun.email}</td
                                            >
                                            <td class="px-2 py-1 font-mono"
                                                >{akun.password}</td
                                            >
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onclick={unduhAkun}
                            >
                                <Download class="size-4" aria-hidden="true" />
                                Unduh daftar akun
                            </Button>
                        </div>
                    </div>
                {/if}
            </div>
        {/if}

        <div class="relative">
            <Label for="cari-orang-tua" class="sr-only">Cari orang tua</Label>
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                id="cari-orang-tua"
                class="pl-11"
                placeholder="Cari nama, email, atau nama anak"
                bind:value={cari}
            />
        </div>

        {#if terfilter.length === 0}
            <div
                class="rounded-2xl border border-dashed border-border px-4 py-8 text-center"
            >
                <UsersRound
                    class="mx-auto mb-2 size-8 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="font-semibold">Belum ada akun orang tua</p>
                <p class="text-sm text-muted-foreground">
                    Tambahkan akun wali murid, lalu tautkan dari menu Siswa.
                </p>
            </div>
        {:else}
            <div class="overflow-x-auto rounded-2xl border border-border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Orang tua</th>
                            <th class="px-4 py-3 font-medium">Anak tertaut</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium"
                                >Aksi</th
                            >
                        </tr>
                    </thead>
                    <tbody>
                        {#each terfilter as orangTua (orangTua.id)}
                            <tr class="border-t border-border">
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{orangTua.name}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {orangTua.email}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    {#if orangTua.anak.length > 0}
                                        <div
                                            class="flex max-w-sm flex-wrap gap-1.5"
                                        >
                                            {#each orangTua.anak as anak (anak.id)}
                                                <Badge variant="outline">
                                                    {anak.nama} · {anak.nis}
                                                </Badge>
                                            {/each}
                                        </div>
                                    {:else}
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Belum ditautkan
                                        </span>
                                    {/if}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant={orangTua.is_active
                                            ? 'secondary'
                                            : 'destructive'}
                                    >
                                        {orangTua.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <TombolIkon
                                            ikon={Pencil}
                                            nada="biru"
                                            label={`Ubah data ${orangTua.name}`}
                                            onclick={() => mulaiUbah(orangTua)}
                                        />
                                        <TombolIkon
                                            ikon={KeyRound}
                                            nada="kuning"
                                            label={`Reset password ${orangTua.name}`}
                                            onclick={() => reset(orangTua)}
                                        />
                                        <TombolIkon
                                            ikon={Power}
                                            nada={orangTua.is_active
                                                ? 'netral'
                                                : 'hijau'}
                                            label={`${orangTua.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${orangTua.name}`}
                                            onclick={() => ubahStatus(orangTua)}
                                        />
                                        <TombolIkon
                                            ikon={Trash2}
                                            nada="merah"
                                            label={`Hapus akun ${orangTua.name}`}
                                            onclick={() => hapus(orangTua)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>

<Dialog bind:open={dialogTambah}>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        <DialogTitle class="text-xl font-bold">Tambah orang tua</DialogTitle>
        <p class="mt-1 mb-4 text-sm text-muted-foreground">
            Akun langsung aktif dan dapat digunakan untuk membuka portal orang
            tua.
        </p>
        <form
            class="grid gap-3"
            onsubmit={(event) => {
                event.preventDefault();
                tambah.submit(store(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        tambah.reset();
                        dialogTambah = false;
                    },
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="orang-tua-nama">Nama lengkap</Label>
                <Input id="orang-tua-nama" bind:value={tambah.name} />
                {#if tambah.errors.name}<p class="text-xs text-destructive">
                        {tambah.errors.name}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="orang-tua-email">Email login</Label>
                <Input
                    id="orang-tua-email"
                    type="email"
                    bind:value={tambah.email}
                />
                {#if tambah.errors.email}<p class="text-xs text-destructive">
                        {tambah.errors.email}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="orang-tua-password">Password awal</Label>
                <Input
                    id="orang-tua-password"
                    type="password"
                    autocomplete="new-password"
                    bind:value={tambah.password}
                />
                {#if tambah.errors.password}<p class="text-xs text-destructive">
                        {tambah.errors.password}
                    </p>{/if}
            </div>
            <div class="mt-2 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (dialogTambah = false)}>Batal</Button
                >
                <Button type="submit" disabled={tambah.processing}
                    >Buat akun</Button
                >
            </div>
        </form>
    </DialogContent>
</Dialog>

<Dialog bind:open={dialogImpor}>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        <DialogTitle class="text-xl font-bold">Upload akun dari CSV</DialogTitle
        >
        <p class="mt-1 mb-4 text-sm text-muted-foreground">
            Unduh template, isi di Excel, simpan sebagai CSV, lalu unggah di
            sini. Kolom <b>nama</b> wajib; email dan password boleh dikosongkan karena
            akan dibuatkan otomatis.
        </p>
        <form
            class="grid gap-3"
            onsubmit={(event) => {
                event.preventDefault();
                impor.submit(orangTuaImpor(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        impor.reset();
                        dialogImpor = false;
                    },
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="berkas-impor-orang-tua">Berkas CSV</Label>
                <input
                    id="berkas-impor-orang-tua"
                    type="file"
                    accept=".csv,text/csv"
                    class="h-12 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary file:px-3 file:py-1 file:text-primary-foreground"
                    onchange={(event) => {
                        impor.berkas =
                            event.currentTarget.files?.item(0) ?? null;
                    }}
                />
                {#if impor.errors.berkas}
                    <p class="text-xs text-destructive">
                        {impor.errors.berkas}
                    </p>
                {/if}
            </div>
            <div class="mt-2 flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => {
                        window.location.href = orangTuaTemplate().url;
                    }}
                >
                    <Download class="size-4" aria-hidden="true" />
                    Template CSV
                </Button>
                <Button
                    type="submit"
                    disabled={impor.processing || impor.berkas === null}
                >
                    <Upload class="size-4" aria-hidden="true" />
                    Upload
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>

<Dialog
    open={diubah !== null}
    onOpenChange={(terbuka) => {
        if (!terbuka) {
            diubah = null;
        }
    }}
>
    <DialogContent>
        {#if diubah}
            {@const orangTua = diubah}
            <DialogTitle class="text-xl font-bold"
                >Ubah {orangTua.name}</DialogTitle
            >
            <form
                class="mt-4 grid gap-3"
                onsubmit={(event) => {
                    event.preventDefault();
                    ubah.submit(update(orangTua.id), {
                        preserveScroll: true,
                        onSuccess: () => (diubah = null),
                    });
                }}
            >
                <div class="grid gap-1.5">
                    <Label for="ubah-orang-tua-nama">Nama lengkap</Label>
                    <Input id="ubah-orang-tua-nama" bind:value={ubah.name} />
                    {#if ubah.errors.name}<p class="text-xs text-destructive">
                            {ubah.errors.name}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="ubah-orang-tua-email">Email login</Label>
                    <Input
                        id="ubah-orang-tua-email"
                        type="email"
                        bind:value={ubah.email}
                    />
                    {#if ubah.errors.email}<p class="text-xs text-destructive">
                            {ubah.errors.email}
                        </p>{/if}
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (diubah = null)}>Batal</Button
                    >
                    <Button type="submit" disabled={ubah.processing}
                        >Simpan perubahan</Button
                    >
                </div>
            </form>
        {/if}
    </DialogContent>
</Dialog>

<KonfirmasiDialog bind:permintaan={konfirmasi} />

<Dialog
    open={passwordTampil !== null}
    onOpenChange={(terbuka) => {
        if (!terbuka) {
            passwordDitutup = true;
        }
    }}
>
    <DialogContent class="max-w-md">
        {#if passwordTampil}
            <DialogTitle class="text-xl font-bold">Password baru</DialogTitle>
            <p class="mt-1 text-sm text-muted-foreground">
                Catat sekarang. Password ini tidak dapat dilihat lagi setelah
                dialog ditutup.
            </p>
            <dl
                class="mt-4 grid gap-2 rounded-2xl border border-border px-4 py-3"
            >
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Email</dt>
                    <dd class="font-mono text-sm">{passwordTampil.email}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-sm text-muted-foreground">Password</dt>
                    <dd class="font-mono font-bold">
                        {passwordTampil.password}
                    </dd>
                </div>
            </dl>
            <div class="mt-5 flex justify-end">
                <Button type="button" onclick={() => (passwordDitutup = true)}>
                    Sudah dicatat
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
