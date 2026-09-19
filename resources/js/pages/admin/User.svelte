<script module lang="ts">
    import { index } from '@/routes/admin/user';
    export const layout = {
        breadcrumbs: [{ title: 'Akun staf', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Power from 'lucide-svelte/icons/power';
    import Search from 'lucide-svelte/icons/search';
    import Trash2 from 'lucide-svelte/icons/trash-2';
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
    import {
        destroy as userDestroy,
        resetPassword as userResetPassword,
        store,
        update,
    } from '@/routes/admin/user';

    type K = { id: number; nama: string };
    type R = { value: string; label: string };
    type U = {
        id: number;
        name: string;
        nip: string | null;
        email: string;
        role: string;
        kantor_id: number | null;
        kantor: string | null;
        is_active: boolean;
    };

    type PasswordBaru = { nama: string; email: string; password: string };

    let {
        users,
        kantors,
        roles,
        passwordBaru = null,
    }: {
        users: U[];
        kantors: K[];
        roles: R[];
        passwordBaru?: PasswordBaru | null;
    } = $props();

    const form = useForm<{
        name: string;
        nip: string;
        email: string;
        password: string;
        role: string;
        kantor_id: number | null;
    }>({
        name: '',
        nip: '',
        email: '',
        password: '',
        role: 'guru',
        kantor_id: null,
    });

    const ubahForm = useForm<{
        name: string;
        nip: string;
        email: string;
        role: string;
        kantor_id: number | null;
    }>({ name: '', nip: '', email: '', role: 'guru', kantor_id: null });

    let dialogTambah = $state(false);
    let diubah = $state<U | null>(null);
    // Dialog password ditutup lewat state sendiri, tapi nilainya ikut prop:
    // reset berikutnya mengirim password baru lewat kunjungan baru.
    let passwordDitutup = $state(false);
    const passwordTampil = $derived(passwordDitutup ? null : passwordBaru);
    let cari = $state('');

    const terfilter = $derived(
        users.filter((u) =>
            `${u.name} ${u.nip ?? ''} ${u.email}`
                .toLowerCase()
                .includes(cari.trim().toLowerCase()),
        ),
    );

    const labelRole = $derived(
        Object.fromEntries(roles.map((r) => [r.value, r.label])),
    );

    type Perubahan = {
        role?: string;
        kantor_id?: number | null;
        is_active?: boolean;
    };

    function ubah(u: U, data: Perubahan): void {
        router.patch(update(u.id).url, data, { preserveScroll: true });
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function mulaiUbah(u: U): void {
        ubahForm.name = u.name;
        ubahForm.nip = u.nip ?? '';
        ubahForm.email = u.email;
        ubahForm.role = u.role;
        ubahForm.kantor_id = u.kantor_id;
        ubahForm.clearErrors();
        diubah = u;
    }

    function resetPassword(u: U): void {
        passwordDitutup = false;

        konfirmasi = {
            judul: `Reset password ${u.name}?`,
            pesan: 'Password lama langsung tidak berlaku. Password baru ditampilkan sekali setelah ini — catat sebelum menutup halaman.',
            label: 'Reset password',
            aksi: () =>
                router.post(
                    userResetPassword(u.id).url,
                    {},
                    { preserveScroll: true },
                ),
        };
    }

    function hapus(u: U): void {
        konfirmasi = {
            judul: `Hapus akun ${u.name}?`,
            pesan: 'Seluruh absensi, izin, jadwal khusus, dan perangkat miliknya ikut terhapus dan tidak bisa dikembalikan. Untuk sekadar menghentikan aksesnya, pakai tombol nonaktifkan.',
            aksi: () =>
                router.delete(userDestroy(u.id).url, { preserveScroll: true }),
        };
    }

    function ubahAktif(u: U): void {
        if (!u.is_active) {
            ubah(u, { is_active: true });

            return;
        }

        konfirmasi = {
            judul: `Nonaktifkan ${u.name}?`,
            pesan: 'Akun ini tidak bisa dipakai masuk sampai diaktifkan kembali. Datanya tidak dihapus.',
            label: 'Nonaktifkan',
            aksi: () => ubah(u, { is_active: false }),
        };
    }
</script>

<AppHead title="Akun staf" />

<div class="mx-auto flex w-full max-w-5xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Akun staf</h3>
                <p class="text-muted-foreground">
                    {users.length} akun admin dan guru. Akun orang tua dikelola dari
                    menu terpisah.
                </p>
            </div>
            <Button onclick={() => (dialogTambah = true)}>
                <UserPlus class="size-4" aria-hidden="true" />
                Tambah akun
            </Button>
        </div>

        <div class="relative">
            <Label for="cari-user" class="sr-only">Cari akun</Label>
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                id="cari-user"
                class="pl-11"
                placeholder="Cari nama, NIP, email"
                bind:value={cari}
            />
        </div>

        {#if terfilter.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Tidak ada akun cocok dengan "{cari}".
            </p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="px-2 py-2 font-medium">Nama</th>
                            <th class="px-2 py-2 font-medium">Role</th>
                            <th class="px-2 py-2 font-medium">Kantor</th>
                            <th class="px-2 py-2 font-medium">Status</th>
                            <th class="px-2 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each terfilter as u (u.id)}
                            <tr class="border-t border-border/60">
                                <td class="px-2 py-3">
                                    <p class="font-semibold">{u.name}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {u.nip
                                            ? `NIP ${u.nip} · `
                                            : ''}{u.email}
                                    </p>
                                </td>
                                <td class="px-2 py-3">
                                    <select
                                        aria-label={`Role ${u.name}`}
                                        class="h-10 rounded-xl border border-input bg-background px-2"
                                        value={u.role}
                                        onchange={(e) =>
                                            ubah(u, {
                                                role: e.currentTarget.value,
                                            })}
                                    >
                                        {#each roles as r (r.value)}
                                            <option value={r.value}
                                                >{r.label}</option
                                            >
                                        {/each}
                                    </select>
                                </td>
                                <td class="px-2 py-3">
                                    <select
                                        aria-label={`Kantor ${u.name}`}
                                        class="h-10 rounded-xl border border-input bg-background px-2"
                                        value={u.kantor_id ?? ''}
                                        onchange={(e) =>
                                            ubah(u, {
                                                kantor_id:
                                                    e.currentTarget.value === ''
                                                        ? null
                                                        : Number(
                                                              e.currentTarget
                                                                  .value,
                                                          ),
                                            })}
                                    >
                                        <option value="">Tanpa kantor</option>
                                        {#each kantors as k (k.id)}
                                            <option value={k.id}
                                                >{k.nama}</option
                                            >
                                        {/each}
                                    </select>
                                </td>
                                <td class="px-2 py-3">
                                    <Badge
                                        variant={u.is_active
                                            ? 'secondary'
                                            : 'destructive'}
                                        >{u.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}</Badge
                                    >
                                </td>
                                <td class="px-2 py-3">
                                    <div class="flex justify-end gap-1">
                                        <TombolIkon
                                            ikon={Pencil}
                                            nada="biru"
                                            label={`Ubah data ${u.name}`}
                                            onclick={() => mulaiUbah(u)}
                                        />
                                        <TombolIkon
                                            ikon={KeyRound}
                                            nada="kuning"
                                            label={`Reset password ${u.name}`}
                                            onclick={() => resetPassword(u)}
                                        />
                                        <TombolIkon
                                            ikon={Power}
                                            nada={u.is_active
                                                ? 'netral'
                                                : 'hijau'}
                                            label={`${u.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${u.name}`}
                                            onclick={() => ubahAktif(u)}
                                        />
                                        <TombolIkon
                                            ikon={Trash2}
                                            nada="merah"
                                            label={`Hapus akun ${u.name}`}
                                            onclick={() => hapus(u)}
                                        />
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-muted-foreground">
                Role {labelRole['admin'] ?? 'Admin'} terakhir yang aktif tidak bisa
                dicabut, dan akun sendiri tidak bisa menurunkan rolenya.
            </p>
        {/if}
    </section>
</div>

<Dialog bind:open={dialogTambah}>
    <DialogContent class="max-h-[85svh] overflow-y-auto">
        <DialogTitle class="text-xl font-bold">Tambah akun</DialogTitle>
        <p class="mt-1 mb-4 text-sm text-muted-foreground">
            Akun langsung aktif dan terverifikasi.
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
                <Label for="user-nama">Nama lengkap</Label>
                <Input id="user-nama" bind:value={form.name} />
                {#if form.errors.name}<p class="text-xs text-destructive">
                        {form.errors.name}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="user-nip"
                    >NIP <span class="text-muted-foreground">(opsional)</span
                    ></Label
                >
                <Input id="user-nip" bind:value={form.nip} />
            </div>
            <div class="grid gap-1.5">
                <Label for="user-email">Email</Label>
                <Input id="user-email" type="email" bind:value={form.email} />
                {#if form.errors.email}<p class="text-xs text-destructive">
                        {form.errors.email}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="user-password">Password awal</Label>
                <Input
                    id="user-password"
                    type="password"
                    autocomplete="new-password"
                    bind:value={form.password}
                />
                {#if form.errors.password}<p class="text-xs text-destructive">
                        {form.errors.password}
                    </p>{/if}
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="user-role">Role</Label>
                    <select
                        id="user-role"
                        class="h-12 rounded-2xl border border-input bg-background px-3"
                        bind:value={form.role}
                    >
                        {#each roles as r (r.value)}
                            <option value={r.value}>{r.label}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="user-kantor">Kantor</Label>
                    <select
                        id="user-kantor"
                        class="h-12 rounded-2xl border border-input bg-background px-3"
                        bind:value={form.kantor_id}
                    >
                        <option value={null}>Tanpa kantor</option>
                        {#each kantors as k (k.id)}
                            <option value={k.id}>{k.nama}</option>
                        {/each}
                    </select>
                </div>
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
            {@const u = diubah}
            <DialogTitle class="text-xl font-bold">Ubah {u.name}</DialogTitle>
            <p class="mt-1 mb-4 text-sm text-muted-foreground">
                Untuk mengganti passwordnya, pakai tombol kunci di daftar.
            </p>

            <form
                class="grid gap-3"
                onsubmit={(e) => {
                    e.preventDefault();
                    ubahForm.submit(update(u.id), {
                        preserveScroll: true,
                        onSuccess: () => (diubah = null),
                    });
                }}
            >
                <div class="grid gap-1.5">
                    <Label for="ubah-nama">Nama lengkap</Label>
                    <Input id="ubah-nama" bind:value={ubahForm.name} />
                    {#if ubahForm.errors.name}<p
                            class="text-xs text-destructive"
                        >
                            {ubahForm.errors.name}
                        </p>{/if}
                </div>
                <div class="grid gap-1.5">
                    <Label for="ubah-nip"
                        >NIP <span class="text-muted-foreground"
                            >(opsional)</span
                        ></Label
                    >
                    <Input id="ubah-nip" bind:value={ubahForm.nip} />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ubah-email">Email</Label>
                    <Input
                        id="ubah-email"
                        type="email"
                        bind:value={ubahForm.email}
                    />
                    {#if ubahForm.errors.email}<p
                            class="text-xs text-destructive"
                        >
                            {ubahForm.errors.email}
                        </p>{/if}
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="ubah-role">Role</Label>
                        <select
                            id="ubah-role"
                            class="h-12 rounded-2xl border border-input bg-background px-3"
                            bind:value={ubahForm.role}
                        >
                            {#each roles as r (r.value)}
                                <option value={r.value}>{r.label}</option>
                            {/each}
                        </select>
                        {#if ubahForm.errors.role}<p
                                class="text-xs text-destructive"
                            >
                                {ubahForm.errors.role}
                            </p>{/if}
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ubah-kantor">Kantor</Label>
                        <select
                            id="ubah-kantor"
                            class="h-12 rounded-2xl border border-input bg-background px-3"
                            bind:value={ubahForm.kantor_id}
                        >
                            <option value={null}>Tanpa kantor</option>
                            {#each kantors as k (k.id)}
                                <option value={k.id}>{k.nama}</option>
                            {/each}
                        </select>
                    </div>
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
                melihatnya — sesudah dialog ini ditutup, tidak ada cara memintanya
                lagi.
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
