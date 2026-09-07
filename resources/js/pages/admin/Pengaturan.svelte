<script module lang="ts">
    import { edit } from '@/routes/admin/pengaturan';
    export const layout = {
        breadcrumbs: [{ title: 'Pengaturan', href: edit() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import LocateFixed from 'lucide-svelte/icons/locate-fixed';
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
    import { update as aplikasiUpdate } from '@/routes/admin/aplikasi';
    import {
        destroy as liburDestroy,
        store as liburStore,
    } from '@/routes/admin/hari-libur';
    import { update as jadwalUpdate } from '@/routes/admin/jadwal';
    import {
        destroy as lokasiDestroy,
        store as lokasiStore,
        update as lokasiUpdate,
    } from '@/routes/admin/lokasi';
    import { update as aturanUpdate } from '@/routes/admin/pengaturan-absensi';

    type K = { id: number; nama: string };
    type A = {
        nama: string;
        logo_url: string | null;
        favicon_url: string | null;
    };
    type L = {
        id: number;
        kantor_id: number | null;
        kantor: K | null;
        nama: string;
        latitude: number;
        longitude: number;
        radius_meter: number;
        is_active: boolean;
    };
    type J = {
        day_of_week: number;
        jam_masuk: string;
        jam_pulang: string;
        is_hari_kerja: boolean;
    };
    type P = {
        toleransi_menit: number;
        buka_masuk_menit: number;
        tutup_masuk_menit: number;
        buka_pulang_menit: number;
    };
    type H = { id: number; tanggal: string; nama: string };

    let {
        aplikasi,
        lokasis,
        kantors,
        jadwals,
        pengaturan,
        hariLiburs,
    }: {
        aplikasi: A;
        lokasis: L[];
        kantors: K[];
        jadwals: J[];
        pengaturan: P;
        hariLiburs: H[];
    } = $props();

    const lokasi = useForm<{
        kantor_id: number | null;
        nama: string;
        latitude: string;
        longitude: string;
        radius_meter: number;
        is_active: boolean;
    }>({
        kantor_id: null,
        nama: '',
        latitude: '',
        longitude: '',
        radius_meter: 100,
        is_active: true,
    });
    const jadwal = useForm({
        jadwals: jadwals.map((j) => ({
            ...j,
            jam_masuk: j.jam_masuk.slice(0, 5),
            jam_pulang: j.jam_pulang.slice(0, 5),
        })),
    });
    const identitas = useForm<{
        nama: string;
        logo: File | null;
        favicon: File | null;
    }>({
        nama: aplikasi.nama,
        logo: null,
        favicon: null,
    });
    const aturan = useForm({ ...pengaturan });
    const libur = useForm({ tanggal: '', nama: '' });

    const hari = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
    ];

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    /**
     * Jam "HH:MM" digeser sekian menit, dibungkus dalam satu hari.
     *
     * Dipakai untuk menerjemahkan kolom menit jadi jam nyata, supaya admin
     * melihat akibat angkanya tanpa menghitung sendiri.
     */
    function geser(jam: string, menit: number): string {
        const [h, m] = jam.split(':').map(Number);

        if (Number.isNaN(h) || Number.isNaN(m)) {
            return '--:--';
        }

        const total = (((h * 60 + m + menit) % 1440) + 1440) % 1440;

        return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
    }

    function gps(): void {
        navigator.geolocation.getCurrentPosition((p) => {
            lokasi.latitude = p.coords.latitude.toFixed(7);
            lokasi.longitude = p.coords.longitude.toFixed(7);
        });
    }

    let lokasiDiubah = $state<number | null>(null);

    const ubahLokasi = useForm<{
        kantor_id: number | null;
        nama: string;
        latitude: string;
        longitude: string;
        radius_meter: number;
        is_active: boolean;
    }>({
        kantor_id: null,
        nama: '',
        latitude: '',
        longitude: '',
        radius_meter: 100,
        is_active: true,
    });

    function mulaiUbah(l: L): void {
        ubahLokasi.kantor_id = l.kantor_id;
        ubahLokasi.nama = l.nama;
        ubahLokasi.latitude = String(l.latitude);
        ubahLokasi.longitude = String(l.longitude);
        ubahLokasi.radius_meter = l.radius_meter;
        ubahLokasi.is_active = l.is_active;
        ubahLokasi.clearErrors();
        lokasiDiubah = l.id;
    }

    let konfirmasi = $state<Konfirmasi | null>(null);

    function hapusLokasi(l: L): void {
        konfirmasi = {
            judul: `Hapus lokasi ${l.nama}?`,
            pesan: 'Guru tidak bisa absen dari titik ini lagi. Untuk mematikannya sementara, pakai tombol Ubah lalu hilangkan centang "Lokasi aktif".',
            aksi: () =>
                router.delete(lokasiDestroy(l.id).url, {
                    preserveScroll: true,
                }),
        };
    }

    function hapusLibur(h: H): void {
        konfirmasi = {
            judul: `Hapus hari libur ${h.nama}?`,
            pesan: 'Tanggal itu kembali dihitung sebagai hari kerja di rekap.',
            aksi: () =>
                router.delete(liburDestroy(h.id).url, { preserveScroll: true }),
        };
    }
</script>

<AppHead title="Pengaturan" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain">
        <div>
            <h3>Identitas aplikasi</h3>
            <p class="text-muted-foreground">
                Nama dipakai di judul halaman dan header guru. Logo tampil di
                sidebar admin, favicon di tab peramban.
            </p>
        </div>

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(e) => {
                e.preventDefault();
                identitas.submit(aplikasiUpdate(), {
                    preserveScroll: true,
                    onSuccess: () => {
                        identitas.logo = null;
                        identitas.favicon = null;
                    },
                });
            }}
        >
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="aplikasi-nama">Nama aplikasi</Label>
                <Input id="aplikasi-nama" bind:value={identitas.nama} />
                {#if identitas.errors.nama}<p class="text-xs text-destructive">
                        {identitas.errors.nama}
                    </p>{/if}
            </div>

            <div class="grid gap-1.5">
                <Label for="aplikasi-logo">Logo aplikasi</Label>
                {#if aplikasi.logo_url}
                    <img
                        src={aplikasi.logo_url}
                        alt="Logo aplikasi sekarang"
                        class="h-12 w-auto max-w-40 self-start rounded-xl bg-background object-contain p-1"
                    />
                {/if}
                <input
                    id="aplikasi-logo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="h-12 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary file:px-3 file:py-1 file:text-primary-foreground"
                    onchange={(e) => {
                        identitas.logo = e.currentTarget.files?.item(0) ?? null;
                    }}
                />
                <p class="text-xs text-muted-foreground">
                    PNG, JPG, atau WEBP. Maksimal 512 KB.
                </p>
                {#if identitas.errors.logo}<p class="text-xs text-destructive">
                        {identitas.errors.logo}
                    </p>{/if}
            </div>

            <div class="grid gap-1.5">
                <Label for="aplikasi-favicon">Favicon</Label>
                {#if aplikasi.favicon_url}
                    <img
                        src={aplikasi.favicon_url}
                        alt="Favicon sekarang"
                        class="size-8 self-start rounded-lg bg-background object-contain p-0.5"
                    />
                {/if}
                <input
                    id="aplikasi-favicon"
                    type="file"
                    accept="image/png,image/webp,image/x-icon"
                    class="h-12 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary file:px-3 file:py-1 file:text-primary-foreground"
                    onchange={(e) => {
                        identitas.favicon =
                            e.currentTarget.files?.item(0) ?? null;
                    }}
                />
                <p class="text-xs text-muted-foreground">
                    PNG, ICO, atau WEBP. Maksimal 128 KB.
                </p>
                {#if identitas.errors.favicon}<p
                        class="text-xs text-destructive"
                    >
                        {identitas.errors.favicon}
                    </p>{/if}
            </div>

            <div class="sm:col-span-2">
                <Button type="submit" disabled={identitas.processing}
                    >Simpan identitas</Button
                >
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Lokasi absen</h3>
            <p class="text-muted-foreground">
                Guru hanya bisa tap di dalam radius salah satu lokasi aktif.
            </p>
        </div>

        {#if lokasis.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada lokasi. Guru belum bisa absen sampai satu lokasi
                ditambahkan.
            </p>
        {:else}
            <ul class="grid gap-2">
                {#each lokasis as l (l.id)}
                    <li class="rounded-2xl border border-border px-4 py-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold">{l.nama}</p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {l.latitude}, {l.longitude}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {l.kantor
                                        ? l.kantor.nama
                                        : 'Dipakai semua kantor'}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="secondary"
                                    >{l.radius_meter} m</Badge
                                >
                                <Badge
                                    variant={l.is_active
                                        ? 'default'
                                        : 'outline'}
                                    >{l.is_active ? 'Aktif' : 'Nonaktif'}</Badge
                                >
                                <TombolIkon
                                    ikon={lokasiDiubah === l.id ? X : Pencil}
                                    nada="biru"
                                    label={lokasiDiubah === l.id
                                        ? 'Tutup form'
                                        : `Ubah lokasi ${l.nama}`}
                                    onclick={() =>
                                        lokasiDiubah === l.id
                                            ? (lokasiDiubah = null)
                                            : mulaiUbah(l)}
                                />
                                <TombolIkon
                                    ikon={Trash2}
                                    nada="merah"
                                    label={`Hapus lokasi ${l.nama}`}
                                    onclick={() => hapusLokasi(l)}
                                />
                            </div>
                        </div>

                        {#if lokasiDiubah === l.id}
                            <form
                                class="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2"
                                onsubmit={(e) => {
                                    e.preventDefault();
                                    ubahLokasi.submit(lokasiUpdate(l.id), {
                                        preserveScroll: true,
                                        onSuccess: () => (lokasiDiubah = null),
                                    });
                                }}
                            >
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-kantor-${l.id}`}
                                        >Kantor</Label
                                    >
                                    <select
                                        id={`ubah-kantor-${l.id}`}
                                        class="h-12 rounded-2xl border border-input bg-background px-3"
                                        bind:value={ubahLokasi.kantor_id}
                                    >
                                        <option value={null}
                                            >Semua kantor</option
                                        >
                                        {#each kantors as k (k.id)}
                                            <option value={k.id}
                                                >{k.nama}</option
                                            >
                                        {/each}
                                    </select>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-nama-${l.id}`}
                                        >Nama lokasi</Label
                                    >
                                    <Input
                                        id={`ubah-nama-${l.id}`}
                                        bind:value={ubahLokasi.nama}
                                    />
                                    {#if ubahLokasi.errors.nama}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubahLokasi.errors.nama}
                                        </p>{/if}
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-radius-${l.id}`}
                                        >Radius (meter)</Label
                                    >
                                    <Input
                                        id={`ubah-radius-${l.id}`}
                                        type="number"
                                        min="20"
                                        max="1000"
                                        bind:value={ubahLokasi.radius_meter}
                                    />
                                    {#if ubahLokasi.errors.radius_meter}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubahLokasi.errors.radius_meter}
                                        </p>{/if}
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-lat-${l.id}`}
                                        >Latitude</Label
                                    >
                                    <Input
                                        id={`ubah-lat-${l.id}`}
                                        inputmode="decimal"
                                        bind:value={ubahLokasi.latitude}
                                    />
                                    {#if ubahLokasi.errors.latitude}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubahLokasi.errors.latitude}
                                        </p>{/if}
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for={`ubah-lng-${l.id}`}
                                        >Longitude</Label
                                    >
                                    <Input
                                        id={`ubah-lng-${l.id}`}
                                        inputmode="decimal"
                                        bind:value={ubahLokasi.longitude}
                                    />
                                    {#if ubahLokasi.errors.longitude}<p
                                            class="text-xs text-destructive"
                                        >
                                            {ubahLokasi.errors.longitude}
                                        </p>{/if}
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id={`ubah-aktif-${l.id}`}
                                            bind:checked={ubahLokasi.is_active}
                                        />
                                        <Label for={`ubah-aktif-${l.id}`}
                                            >Lokasi aktif</Label
                                        >
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={ubahLokasi.processing}
                                        >Simpan perubahan</Button
                                    >
                                </div>
                            </form>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(e) => {
                e.preventDefault();
                lokasi.submit(lokasiStore(), {
                    onSuccess: () => lokasi.reset(),
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="lokasi-kantor">Kantor</Label>
                <select
                    id="lokasi-kantor"
                    class="h-12 rounded-2xl border border-input bg-background px-3"
                    bind:value={lokasi.kantor_id}
                >
                    <option value={null}>Semua kantor</option>
                    {#each kantors as k (k.id)}
                        <option value={k.id}>{k.nama}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="lokasi-nama">Nama lokasi</Label>
                <Input
                    id="lokasi-nama"
                    placeholder="Gerbang utama"
                    bind:value={lokasi.nama}
                />
                {#if lokasi.errors.nama}<p class="text-xs text-destructive">
                        {lokasi.errors.nama}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="lokasi-radius">Radius (meter)</Label>
                <Input
                    id="lokasi-radius"
                    type="number"
                    min="10"
                    max="1000"
                    bind:value={lokasi.radius_meter}
                />
                {#if lokasi.errors.radius_meter}<p
                        class="text-xs text-destructive"
                    >
                        {lokasi.errors.radius_meter}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="lokasi-lat">Latitude</Label>
                <Input
                    id="lokasi-lat"
                    inputmode="decimal"
                    placeholder="-6.1753924"
                    bind:value={lokasi.latitude}
                />
                {#if lokasi.errors.latitude}<p class="text-xs text-destructive">
                        {lokasi.errors.latitude}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="lokasi-lng">Longitude</Label>
                <Input
                    id="lokasi-lng"
                    inputmode="decimal"
                    placeholder="106.8271528"
                    bind:value={lokasi.longitude}
                />
                {#if lokasi.errors.longitude}<p
                        class="text-xs text-destructive"
                    >
                        {lokasi.errors.longitude}
                    </p>{/if}
            </div>
            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <Button type="button" variant="outline" onclick={gps}>
                    <LocateFixed class="size-4" aria-hidden="true" />
                    Pakai lokasi saya
                </Button>
                <Button type="submit" disabled={lokasi.processing}
                    >Tambah lokasi</Button
                >
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Aturan jam absen</h3>
            <p class="text-muted-foreground">
                Berlaku untuk semua guru. Yang berbeda tiap guru hanya jam masuk
                dan pulangnya, diatur di halaman Jadwal guru.
            </p>
        </div>

        <form
            class="grid gap-3 sm:grid-cols-2"
            onsubmit={(e) => {
                e.preventDefault();
                aturan.submit(aturanUpdate(), {
                    preserveScroll: true,
                    onSuccess: () => aturan.defaults(),
                });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="toleransi">Toleransi telat (menit)</Label>
                <Input
                    id="toleransi"
                    type="number"
                    min="0"
                    max="120"
                    bind:value={aturan.toleransi_menit}
                />
                <p class="text-xs text-muted-foreground">
                    Tap sesudah jam masuk + {aturan.toleransi_menit} menit dihitung
                    terlambat.
                </p>
                {#if aturan.errors.toleransi_menit}<p
                        class="text-xs text-destructive"
                    >
                        {aturan.errors.toleransi_menit}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="buka-masuk">Buka absen masuk (menit sebelum)</Label>
                <Input
                    id="buka-masuk"
                    type="number"
                    min="0"
                    max="720"
                    bind:value={aturan.buka_masuk_menit}
                />
                {#if aturan.errors.buka_masuk_menit}<p
                        class="text-xs text-destructive"
                    >
                        {aturan.errors.buka_masuk_menit}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="tutup-masuk"
                    >Tutup absen masuk (menit sesudah)</Label
                >
                <Input
                    id="tutup-masuk"
                    type="number"
                    min="0"
                    max="1440"
                    bind:value={aturan.tutup_masuk_menit}
                />
                {#if aturan.errors.tutup_masuk_menit}<p
                        class="text-xs text-destructive"
                    >
                        {aturan.errors.tutup_masuk_menit}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="buka-pulang"
                    >Buka absen pulang (menit sebelum)</Label
                >
                <Input
                    id="buka-pulang"
                    type="number"
                    min="0"
                    max="720"
                    bind:value={aturan.buka_pulang_menit}
                />
                {#if aturan.errors.buka_pulang_menit}<p
                        class="text-xs text-destructive"
                    >
                        {aturan.errors.buka_pulang_menit}
                    </p>{/if}
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2"
            >
                <p class="text-xs text-muted-foreground">
                    {aturan.isDirty
                        ? 'Ada perubahan yang belum disimpan.'
                        : 'Semua perubahan tersimpan.'}
                </p>
                <Button type="submit" disabled={aturan.processing}
                    >Simpan aturan</Button
                >
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Jadwal default sekolah</h3>
            <p class="text-muted-foreground">
                Dipakai setiap guru yang belum punya jadwal sendiri.
            </p>
        </div>

        <form
            class="grid gap-3"
            onsubmit={(e) => {
                e.preventDefault();
                jadwal.submit(jadwalUpdate(), {
                    preserveScroll: true,
                    onSuccess: () => jadwal.defaults(),
                });
            }}
        >
            {#each jadwal.jadwals as j, i (j.day_of_week)}
                <div
                    class="grid gap-3 rounded-2xl border border-border px-4 py-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id={`kerja-${j.day_of_week}`}
                                bind:checked={jadwal.jadwals[i].is_hari_kerja}
                            />
                            <Label
                                for={`kerja-${j.day_of_week}`}
                                class="text-base font-semibold"
                                >{hari[j.day_of_week]}</Label
                            >
                        </div>
                        {#if !j.is_hari_kerja}
                            <Badge variant="outline">Bukan hari kerja</Badge>
                        {/if}
                    </div>

                    {#if j.is_hari_kerja}
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for={`masuk-${j.day_of_week}`}
                                    >Jam masuk</Label
                                >
                                <Input
                                    id={`masuk-${j.day_of_week}`}
                                    type="time"
                                    bind:value={jadwal.jadwals[i].jam_masuk}
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for={`pulang-${j.day_of_week}`}
                                    >Jam pulang</Label
                                >
                                <Input
                                    id={`pulang-${j.day_of_week}`}
                                    type="time"
                                    bind:value={jadwal.jadwals[i].jam_pulang}
                                />
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Absen masuk {geser(
                                j.jam_masuk,
                                -aturan.buka_masuk_menit,
                            )}–{geser(j.jam_masuk, aturan.tutup_masuk_menit)} · terlambat
                            setelah {geser(j.jam_masuk, aturan.toleransi_menit)} ·
                            absen pulang buka {geser(
                                j.jam_pulang,
                                -aturan.buka_pulang_menit,
                            )}
                        </p>
                    {/if}
                </div>
            {/each}

            {#if jadwal.hasErrors}
                <p class="text-sm text-destructive">
                    Ada isian jadwal yang belum benar. Periksa jamnya.
                </p>
            {/if}

            <div
                class="sticky bottom-3 flex items-center justify-between gap-3 rounded-2xl border border-border bg-background/90 px-4 py-3 backdrop-blur-md"
            >
                <p class="text-xs text-muted-foreground">
                    {jadwal.isDirty
                        ? 'Ada perubahan yang belum disimpan.'
                        : 'Semua perubahan tersimpan.'}
                </p>
                <Button type="submit" disabled={jadwal.processing}
                    >Simpan jadwal default</Button
                >
            </div>
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <div>
            <h3>Hari libur</h3>
            <p class="text-muted-foreground">
                Tanggal di sini dihitung libur pada rekap, menimpa jadwal kerja.
            </p>
        </div>

        {#if hariLiburs.length === 0}
            <p
                class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada hari libur.
            </p>
        {:else}
            <ul class="grid gap-2">
                {#each hariLiburs as h (h.id)}
                    <li
                        class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold">{h.nama}</p>
                            <p class="text-xs text-muted-foreground">
                                {tanggalPanjang.format(new Date(h.tanggal))}
                            </p>
                        </div>
                        <TombolIkon
                            ikon={Trash2}
                            nada="merah"
                            label={`Hapus hari libur ${h.nama}`}
                            onclick={() => hapusLibur(h)}
                        />
                    </li>
                {/each}
            </ul>
        {/if}

        <form
            class="grid gap-3 sm:grid-cols-[auto_1fr_auto] sm:items-end"
            onsubmit={(e) => {
                e.preventDefault();
                libur.submit(liburStore(), { onSuccess: () => libur.reset() });
            }}
        >
            <div class="grid gap-1.5">
                <Label for="libur-tanggal">Tanggal</Label>
                <Input
                    id="libur-tanggal"
                    type="date"
                    bind:value={libur.tanggal}
                />
                {#if libur.errors.tanggal}<p class="text-xs text-destructive">
                        {libur.errors.tanggal}
                    </p>{/if}
            </div>
            <div class="grid gap-1.5">
                <Label for="libur-nama">Keterangan</Label>
                <Input
                    id="libur-nama"
                    placeholder="HUT Kemerdekaan"
                    bind:value={libur.nama}
                />
                {#if libur.errors.nama}<p class="text-xs text-destructive">
                        {libur.errors.nama}
                    </p>{/if}
            </div>
            <Button type="submit" disabled={libur.processing}>Tambah</Button>
        </form>
    </section>
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />
