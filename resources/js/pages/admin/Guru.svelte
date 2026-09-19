<script module lang="ts">
    import { index } from '@/routes/admin/guru';
    export const layout = {
        breadcrumbs: [{ title: 'Guru', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import Check from 'lucide-svelte/icons/check';
    import Download from 'lucide-svelte/icons/download';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Power from 'lucide-svelte/icons/power';
    import Search from 'lucide-svelte/icons/search';
    import ShieldOff from 'lucide-svelte/icons/shield-off';
    import Smartphone from 'lucide-svelte/icons/smartphone';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Upload from 'lucide-svelte/icons/upload';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import AppHead from '@/components/AppHead.svelte';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import TombolIkon from '@/components/TombolIkon.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import {
        impor as guruImpor,
        store,
        template as guruTemplate,
        update,
    } from '@/routes/admin/guru';
    import { index as jadwalGuruIndex } from '@/routes/admin/jadwal-guru';
    import { update as updateDevice } from '@/routes/admin/perangkat';
    import {
        destroy as userDestroy,
        resetPassword as userResetPassword,
        update as userUpdate,
    } from '@/routes/admin/user';

    type P = {
        id: number;
        label: string;
        status: string;
        terdaftar: string | null;
    };
    type G = {
        id: number;
        name: string;
        nip: string | null;
        email: string;
        role: string;
        is_active: boolean;
        perangkats: P[];
    };
    type Akun = { nama: string; email: string; password: string };
    type PasswordBaru = { nama: string; email: string; password: string };
    type Hasil = {
        dibuat: number;
        dilewati: number;
        galat: string[];
        akun: Akun[];
    };

    let {
        gurus,
        hasilImpor = null,
        passwordBaru = null,
    }: {
        gurus: G[];
        hasilImpor?: Hasil | null;
        passwordBaru?: PasswordBaru | null;
    } = $props();

    const form = useForm({ name: '', nip: '', email: '', password: '' });
    const impor = useForm<{ berkas: File | null }>({ berkas: null });

    // Aksi akun memakai endpoint kelola user: guru adalah User, dan penjaga
    // "admin aktif terakhir" serta "akun sendiri" sudah hidup di sana.
    const ubahForm = useForm<{ name: string; nip: string; email: string }>({
        name: '',
        nip: '',
        email: '',
    });

    let diubah = $state<G | null>(null);
    let passwordDitutup = $state(false);
    const passwordTampil = $derived(passwordDitutup ? null : passwordBaru);

    let dialogTambah = $state(false);
    let dialogImpor = $state(false);
    let guruPerangkat = $state<G | null>(null);

    let cari = $state('');
    let perHalaman = $state<number>(10);
    let tampil = $state(10);

    const terfilter = $derived(
        gurus.filter((g) =>
            `${g.name} ${g.nip ?? ''} ${g.email}`
                .toLowerCase()
                .includes(cari.trim().toLowerCase()),
        ),
    );

    // 0 = "Semua": seluruh hasil filter langsung tampil tanpa tombol next.
    const terlihat = $derived(
        perHalaman === 0 ? terfilter : terfilter.slice(0, tampil),
    );
    const sisa = $derived(terfilter.length - terlihat.length);

    const labelStatus: Record<string, string> = {
        pending: 'Menunggu',
        active: 'Aktif',
        revoked: 'Dicabut',
    };

    /** Kembali ke halaman pertama; dipanggil saat ukuran atau pencarian berubah. */
    function ulangDariAwal(nilai = perHalaman): void {
        perHalaman = nilai;
        tampil = nilai === 0 ? gurus.length : nilai;
    }

    function perangkatAktif(g: G): P | undefined {
        return g.perangkats.find((p) => p.status === 'active');
    }

    function menunggu(g: G): number {
        return g.perangkats.filter((p) => p.status === 'pending').length;
    }

    /**
     * Simpan daftar akun baru sebagai CSV.
     *
     * Dibuat di sisi klien dari data yang sudah ada di halaman: password tidak
     * disimpan di server, jadi tidak ada endpoint yang bisa dipanggil ulang
     * untuk memintanya lagi.
     */
    function unduhAkun(): void {
        if (!hasilImpor) {
            return;
        }

        const baris = [
            ['Nama', 'Email', 'Password'],
            ...hasilImpor.akun.map((a) => [a.nama, a.email, a.password]),
        ];
        const csv = baris
            .map((kolom) => kolom.map((sel) => `"${sel}"`).join(','))
            .join('\n');
        const tautan = document.createElement('a');
        tautan.href = URL.createObjectURL(
            new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }),
        );
        tautan.download = 'akun-guru-baru.csv';
        tautan.click();
        URL.revokeObjectURL(tautan.href);
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function ubahStatusPerangkat(p: P, status: 'active' | 'revoked'): void {
        const kirim = (): void => {
            router.patch(
                updateDevice(p.id).url,
                { status },
                {
                    preserveScroll: true,
                    onSuccess: () => (guruPerangkat = null),
                },
            );
        };

        if (status !== 'revoked') {
            kirim();

            return;
        }

        konfirmasi = {
            judul: `Cabut ${p.label}?`,
            pesan: 'Guru tidak bisa absen dari HP ini lagi sampai ada HP yang disetujui.',
            label: 'Cabut',
            aksi: kirim,
        };
    }

    function mulaiUbah(g: G): void {
        ubahForm.name = g.name;
        ubahForm.nip = g.nip ?? '';
        ubahForm.email = g.email;
        ubahForm.clearErrors();
        diubah = g;
    }

    function resetPassword(g: G): void {
        passwordDitutup = false;

        konfirmasi = {
            judul: `Reset password ${g.name}?`,
            pesan: 'Password lama langsung tidak berlaku. Password baru ditampilkan sekali setelah ini — catat sebelum menutup halaman.',
            label: 'Reset password',
            aksi: () =>
                router.post(
                    userResetPassword(g.id).url,
                    {},
                    { preserveScroll: true },
                ),
        };
    }

    function hapus(g: G): void {
        konfirmasi = {
            judul: `Hapus akun ${g.name}?`,
            pesan: 'Seluruh absensi, izin, jadwal khusus, dan perangkat miliknya ikut terhapus dan tidak bisa dikembalikan. Untuk sekadar menghentikan aksesnya, pakai tombol nonaktifkan.',
            aksi: () =>
                router.delete(userDestroy(g.id).url, { preserveScroll: true }),
        };
    }

    function ubahAktif(g: G): void {
        const kirim = (): void => {
            router.patch(
                update(g.id).url,
                { is_active: !g.is_active },
                { preserveScroll: true },
            );
        };

        if (!g.is_active) {
            kirim();

            return;
        }

        konfirmasi = {
            judul: `Nonaktifkan ${g.name}?`,
            pesan: 'Akunnya tidak bisa dipakai absen sampai diaktifkan kembali. Data absensinya tidak dihapus.',
            label: 'Nonaktifkan',
            aksi: kirim,
        };
    }
</script>

<AppHead title="Guru" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Guru</h3>
                <p class="text-muted-foreground">
                    {gurus.length} akun terdaftar.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    onclick={() => (dialogImpor = true)}
                    data-test="buka-impor"
                >
                    <Upload class="size-4" aria-hidden="true" />
                    Impor Excel
                </Button>
                <Button
                    onclick={() => (dialogTambah = true)}
                    data-test="buka-tambah"
                >
                    <UserPlus class="size-4" aria-hidden="true" />
                    Tambah guru
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
                            {hasilImpor.akun.length} akun dibuatkan email dan password.
                            Password hanya tampil sekali — unduh atau catat sebelum
                            meninggalkan halaman ini.
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
                                    {#each hasilImpor.akun as a (a.email)}
                                        <tr class="border-t border-border/60">
                                            <td class="px-2 py-1">{a.nama}</td>
                                            <td class="px-2 py-1 font-mono"
                                                >{a.email}</td
                                            >
                                            <td class="px-2 py-1 font-mono"
                                                >{a.password}</td
                                            >
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <Button
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

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1">
                <Label for="cari-guru" class="sr-only">Cari guru</Label>
                <Search
                    class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="cari-guru"
                    class="pl-11"
                    placeholder="Cari nama, NIP, email"
                    bind:value={cari}
                    oninput={() => ulangDariAwal()}
                />
            </div>
            <select
                aria-label="Jumlah baris"
                class="h-12 rounded-2xl border border-input bg-background px-3"
                value={perHalaman}
                onchange={(e) => ulangDariAwal(Number(e.currentTarget.value))}
            >
                <option value={10}>10 baris</option>
                <option value={15}>15 baris</option>
                <option value={20}>20 baris</option>
                <option value={0}>Semua</option>
            </select>
        </div>

        {#if terfilter.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                {gurus.length === 0
                    ? 'Belum ada akun guru.'
                    : `Tidak ada guru cocok dengan "${cari}".`}
            </p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="px-2 py-2 font-medium">Nama</th>
                            <th class="px-2 py-2 font-medium">Email</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2 font-medium">Perangkat</th>
                            <th class="px-2 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each terlihat as g (g.id)}
                            <tr class="border-t border-border/60">
                                <td class="px-2 py-3">
                                    <p class="font-semibold">{g.name}</p>
                                    {#if g.nip}
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            NIP {g.nip}
                                        </p>
                                    {/if}
                                </td>
                                <td class="px-2 py-3 text-muted-foreground"
                                    >{g.email}</td
                                >
                                <td class="px-2 py-3">
                                    <Badge
                                        variant={g.is_active
                                            ? 'secondary'
                                            : 'destructive'}
                                        >{g.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}</Badge
                                    >
                                </td>
                                <td class="px-2 py-3">
                                    <button
                                        type="button"
                                        onclick={() => (guruPerangkat = g)}
                                        class="flex items-center gap-1.5 text-left underline-offset-2 hover:underline"
                                    >
                                        <Smartphone
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {perangkatAktif(g)
                                            ? '1 aktif'
                                            : 'Belum ada'}
                                        {#if menunggu(g) > 0}
                                            <Badge variant="outline"
                                                >{menunggu(g)} menunggu</Badge
                                            >
                                        {/if}
                                    </button>
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex justify-end gap-1">
                                        <TombolIkon
                                            ikon={CalendarClock}
                                            label={`Jadwal ${g.name}`}
                                            onclick={() =>
                                                router.visit(
                                                    toUrl(jadwalGuruIndex()),
                                                )}
                                        />
                                        <TombolIkon
                                            ikon={Pencil}
                                            nada="biru"
                                            label={`Ubah data ${g.name}`}
                                            onclick={() => mulaiUbah(g)}
                                        />
                                        <TombolIkon
                                            ikon={KeyRound}
                                            nada="kuning"
                                            label={`Reset password ${g.name}`}
                                            onclick={() => resetPassword(g)}
                                        />
                                        <TombolIkon
                                            ikon={Power}
                                            nada={g.is_active
                                                ? 'netral'
                                                : 'hijau'}
                                            label={`${g.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${g.name}`}
                                            onclick={() => ubahAktif(g)}
                                        />
                                        <TombolIkon
                                            ikon={Trash2}
                                            nada="merah"
                                            label={`Hapus akun ${g.name}`}
                                            onclick={() => hapus(g)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">
                    Menampilkan {terlihat.length} dari {terfilter.length} guru
                </p>
                {#if sisa > 0}
                    <Button
                        variant="outline"
                        size="sm"
                        onclick={() => (tampil += perHalaman)}
                        >Next ({Math.min(sisa, perHalaman)})</Button
                    >
                {/if}
            </div>
        {/if}
    </section>
</div>

<Dialog bind:open={dialogTambah}>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        <DialogTitle class="text-xl font-bold">Tambah guru</DialogTitle>
        <p class="mt-1 mb-4 text-sm text-muted-foreground">
            Akun langsung aktif dan terverifikasi. Password awal diberikan ke
            guru untuk diganti sendiri.
        </p>

        <form
            class="grid gap-3"
            onsubmit={(e) => {
                e.preventDefault();
                form.submit(store(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        dialogTambah = false;
                    },
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="guru-nama">Nama lengkap</Label>
                <Input id="guru-nama" bind:value={form.name} />
                {#if form.errors.name}<p class="text-xs text-destructive">
                        {form.errors.name}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="guru-nip"
                    >NIP <span class="text-muted-foreground">(opsional)</span
                    ></Label
                >
                <Input id="guru-nip" bind:value={form.nip} />
                {#if form.errors.nip}<p class="text-xs text-destructive">
                        {form.errors.nip}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="guru-email">Email</Label>
                <Input id="guru-email" type="email" bind:value={form.email} />
                {#if form.errors.email}<p class="text-xs text-destructive">
                        {form.errors.email}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="guru-password">Password awal</Label>
                <Input
                    id="guru-password"
                    type="password"
                    autocomplete="new-password"
                    bind:value={form.password}
                />
                {#if form.errors.password}<p class="text-xs text-destructive">
                        {form.errors.password}
                    </p>{/if}
            </div>
            <div class="mt-2 flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => (dialogTambah = false)}>Batal</Button
                >
                <Button type="submit" disabled={form.processing}
                    >Buat akun</Button
                >
            </div>
        </form>
    </DialogContent>
</Dialog>

<Dialog bind:open={dialogImpor}>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        <DialogTitle class="text-xl font-bold">Impor dari Excel</DialogTitle>
        <p class="mt-1 mb-4 text-sm text-muted-foreground">
            Unduh templatenya, isi di Excel, simpan sebagai CSV, lalu unggah di
            sini. Yang wajib hanya kolom <b>nama</b> — email dan password dibuatkan
            otomatis kalau dikosongkan, dan daftarnya tampil setelah impor.
        </p>

        <form
            class="grid gap-3"
            onsubmit={(e) => {
                e.preventDefault();
                impor.submit(guruImpor(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        impor.reset();
                        dialogImpor = false;
                    },
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="berkas-impor">Berkas CSV</Label>
                <input
                    id="berkas-impor"
                    type="file"
                    accept=".csv,text/csv"
                    class="h-12 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary file:px-3 file:py-1 file:text-primary-foreground"
                    onchange={(e) => {
                        impor.berkas = e.currentTarget.files?.item(0) ?? null;
                    }}
                />
                {#if impor.errors.berkas}<p class="text-xs text-destructive">
                        {impor.errors.berkas}
                    </p>{/if}
            </div>
            <div class="mt-2 flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onclick={() => {
                        window.location.href = guruTemplate().url;
                    }}
                >
                    <Download class="size-4" aria-hidden="true" />
                    Template
                </Button>
                <Button
                    type="submit"
                    disabled={impor.processing || impor.berkas === null}
                >
                    <Upload class="size-4" aria-hidden="true" />
                    Impor
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>

<Dialog
    open={guruPerangkat !== null}
    onOpenChange={(nilai) => {
        if (!nilai) {
            guruPerangkat = null;
        }
    }}
>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        {#if guruPerangkat}
            <DialogTitle class="text-xl font-bold"
                >Perangkat {guruPerangkat.name}</DialogTitle
            >
            <p class="mt-1 mb-4 text-sm text-muted-foreground">
                Menyetujui satu HP mencabut HP lain milik guru ini.
            </p>

            {#if guruPerangkat.perangkats.length === 0}
                <p class="text-sm text-muted-foreground">
                    Belum ada HP terdaftar. Guru mendaftar sendiri saat pertama
                    membuka aplikasi.
                </p>
            {:else}
                <ul class="grid gap-2">
                    {#each guruPerangkat.perangkats as p (p.id)}
                        <li
                            class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm">{p.label}</p>
                                <p class="text-xs text-muted-foreground">
                                    {labelStatus[p.status] ??
                                        p.status}{p.terdaftar
                                        ? ` · ${p.terdaftar}`
                                        : ''}
                                </p>
                            </div>
                            {#if p.status === 'active'}
                                <TombolIkon
                                    ikon={ShieldOff}
                                    nada="merah"
                                    label={`Cabut ${p.label}`}
                                    onclick={() =>
                                        ubahStatusPerangkat(p, 'revoked')}
                                />
                            {:else}
                                <TombolIkon
                                    ikon={Check}
                                    nada="hijau"
                                    label={`Setujui ${p.label}`}
                                    onclick={() =>
                                        ubahStatusPerangkat(p, 'active')}
                                />
                            {/if}
                        </li>
                    {/each}
                </ul>
            {/if}

            <div class="mt-4 flex justify-end">
                <Button variant="outline" onclick={() => (guruPerangkat = null)}
                    >Tutup</Button
                >
            </div>
        {/if}
    </DialogContent>
</Dialog>

<KonfirmasiDialog bind:permintaan={konfirmasi} />

<Dialog
    open={diubah !== null}
    onOpenChange={(nilai) => {
        if (!nilai) {
            diubah = null;
        }
    }}
>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        {#if diubah}
            {@const g = diubah}
            <DialogTitle class="text-xl font-bold">Ubah {g.name}</DialogTitle>
            <p class="mt-1 mb-4 text-sm text-muted-foreground">
                Role dan kantor diatur di menu Akun staf.
            </p>

            <form
                class="grid gap-3"
                onsubmit={(e) => {
                    e.preventDefault();
                    ubahForm.submit(userUpdate(g.id), {
                        preserveScroll: true,
                        onSuccess: () => (diubah = null),
                    });
                }}
            >
                <div class="grid gap-1.5">
                    <Label for="ubah-guru-nama">Nama lengkap</Label>
                    <Input id="ubah-guru-nama" bind:value={ubahForm.name} />
                    {#if ubahForm.errors.name}<p
                            class="text-xs text-destructive"
                        >
                            {ubahForm.errors.name}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="ubah-guru-nip"
                        >NIP <span class="text-muted-foreground"
                            >(opsional)</span
                        ></Label
                    >
                    <Input id="ubah-guru-nip" bind:value={ubahForm.nip} />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ubah-guru-email">Email</Label>
                    <Input
                        id="ubah-guru-email"
                        type="email"
                        bind:value={ubahForm.email}
                    />
                    {#if ubahForm.errors.email}<p
                            class="text-xs text-destructive"
                        >
                            {ubahForm.errors.email}
                        </p>{/if}
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => (diubah = null)}>Batal</Button
                    >
                    <Button type="submit" disabled={ubahForm.processing}
                        >Simpan perubahan</Button
                    >
                </div>
            </form>
        {/if}
    </DialogContent>
</Dialog>

<Dialog
    open={passwordTampil !== null}
    onOpenChange={(nilai) => {
        if (!nilai) {
            passwordDitutup = true;
        }
    }}
>
    <DialogContent class="max-w-md">
        {#if passwordTampil}
            <DialogTitle class="text-xl font-bold">Password baru</DialogTitle>
            <p class="mt-1 text-sm text-muted-foreground">
                Password {passwordTampil.nama} sudah diganti. Ini satu-satunya kesempatan
                melihatnya.
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
                    <dd class="font-mono text-base font-bold">
                        {passwordTampil.password}
                    </dd>
                </div>
            </dl>

            <div class="mt-5 flex justify-end">
                <Button onclick={() => (passwordDitutup = true)}
                    >Sudah dicatat</Button
                >
            </div>
        {/if}
    </DialogContent>
</Dialog>
