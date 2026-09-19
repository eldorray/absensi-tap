<script module lang="ts">
    export const layout = { title: 'Periksa Absensi Siswa' };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Search from 'lucide-svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { bukaFinalisasi, draft, finalisasi } from '@/routes/absensi-siswa';
    type Status = 'hadir' | 'sakit' | 'izin' | 'alpa' | 'terlambat';
    type Siswa = {
        id: number;
        nis: string;
        nama: string;
        status: Status;
        catatan: string | null;
        jam_datang: string | null;
    };
    let {
        kelas,
        tanggal,
        siswa: awal,
        sesi,
    }: {
        kelas: {
            id: number;
            nama: string;
            kantor: string | null;
            tahun_ajaran: string;
        };
        tanggal: string;
        siswa: Siswa[];
        sesi: {
            status: string;
            catatan: string | null;
            read_only: boolean;
            dapat_dibuka: boolean;
        };
    } = $props();
    let siswa = $state(awal.map((item) => ({ ...item })));
    const lampau = tanggal !== new Date().toLocaleDateString('sv-SE');
    let cari = $state('');
    let filter = $state('semua');
    let dipilih = $state<Siswa | null>(null);
    let konfirmasi = $state(false);
    let konfirmasiBuka = $state(false);
    let processing = $state(false);
    const pilihan: { value: Status; label: string }[] = [
        { value: 'hadir', label: 'Hadir' },
        { value: 'sakit', label: 'Sakit' },
        { value: 'izin', label: 'Izin' },
        { value: 'alpa', label: 'Alpa' },
        { value: 'terlambat', label: 'Terlambat' },
    ];
    const tampil = $derived(
        siswa.filter(
            (x) =>
                (filter === 'semua' || x.status === filter) &&
                `${x.nama} ${x.nis}`.toLowerCase().includes(cari.toLowerCase()),
        ),
    );
    const ringkasan = $derived(
        Object.fromEntries(
            pilihan.map((p) => [
                p.value,
                siswa.filter((x) => x.status === p.value).length,
            ]),
        ) as Record<Status, number>,
    );
    function payload() {
        return {
            tanggal,
            catatan: sesi.catatan,
            absensis: siswa
                .filter((x) => x.status !== 'hadir' || x.catatan)
                .map((x) => ({
                    siswa_id: x.id,
                    status: x.status,
                    catatan: x.catatan,
                    jam_datang: x.jam_datang,
                })),
        };
    }
    function buka() {
        processing = true;
        router.put(
            toUrl(bukaFinalisasi(kelas.id)),
            {},
            {
                preserveScroll: true,
                onFinish: () => (processing = false),
                onSuccess: () => (konfirmasiBuka = false),
            },
        );
    }
    function simpan(final = false) {
        processing = true;
        router.put(
            toUrl(final ? finalisasi(kelas.id) : draft(kelas.id)),
            payload(),
            {
                preserveScroll: true,
                onFinish: () => (processing = false),
                onSuccess: () => {
                    konfirmasi = false;
                    dipilih = null;
                },
            },
        );
    }
</script>

<AppHead title={`Absensi ${kelas.nama}`} />
<div
    class="mx-auto flex w-full max-w-lg flex-col gap-4 px-4 py-5 pb-44 sm:px-6"
>
    <section class="g-tile g-tone-green">
        <div class="flex justify-between gap-3">
            <div>
                <p class="text-sm font-bold">
                    {tanggal} · {kelas.tahun_ajaran}
                </p>
                <h1 class="g-display text-3xl">Kelas {kelas.nama}</h1>
                <p>{kelas.kantor} · Status: {sesi.status.replace('_', ' ')}</p>
            </div>
            <span
                class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary h-fit"
                >{siswa.length} siswa</span
            >
        </div>
    </section>
    <div class="relative">
        <Search
            class="absolute left-4 top-3.5 size-5 text-muted-foreground"
        /><input
            bind:value={cari}
            placeholder="Cari nama atau NIS"
            class="min-h-12 w-full rounded-2xl border bg-background pl-12 pr-4"
        />
    </div>
    <div class="flex gap-2 overflow-x-auto pb-1">
        <button
            type="button"
            onclick={() => (filter = 'semua')}
            class="min-h-11 rounded-full border px-4 font-semibold"
            >Semua</button
        >{#each pilihan as p (p.value)}<button
                type="button"
                onclick={() => (filter = p.value)}
                class="min-h-11 rounded-full border px-4 font-semibold"
                >{p.label}</button
            >{/each}
    </div>
    <section class="overflow-hidden rounded-3xl border bg-card">
        {#each tampil as item (item.id)}<button
                type="button"
                disabled={sesi.read_only}
                onclick={() => (dipilih = item)}
                class="flex min-h-16 w-full items-center justify-between gap-3 border-b px-4 text-left last:border-0 disabled:opacity-80"
                ><div class="min-w-0">
                    <p class="truncate font-bold">{item.nama}</p>
                    <p class="font-mono text-xs text-muted-foreground">
                        NIS {item.nis}{item.catatan ? ` · ${item.catatan}` : ''}
                    </p>
                </div>
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold capitalize text-primary"
                    >{item.status}</span
                ></button
            >{:else}<p class="p-8 text-center text-muted-foreground">
                Siswa tidak ditemukan.
            </p>{/each}
    </section>
</div>
<div
    class="fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-30 mx-auto max-w-lg border-t bg-background/95 p-3 backdrop-blur"
>
    <div class="mb-3 grid grid-cols-5 gap-1 text-center">
        {#each pilihan as p (p.value)}<div>
                <strong class="block text-lg">{ringkasan[p.value]}</strong><span
                    class="text-[10px] text-muted-foreground">{p.label}</span
                >
            </div>{/each}
    </div>
    {#if sesi.read_only}<div class="grid gap-2">
            <p class="rounded-2xl bg-muted p-3 text-center font-bold">
                {lampau
                    ? 'Absensi hari lampau hanya dapat dilihat.'
                    : 'Absensi sudah final dan hanya dapat dilihat.'}
            </p>
            {#if sesi.dapat_dibuka}
                <button
                    type="button"
                    disabled={processing}
                    onclick={() => (konfirmasiBuka = true)}
                    class="min-h-12 rounded-2xl border font-bold"
                    >Batalkan finalisasi</button
                >
            {/if}
        </div>{:else}<div class="grid grid-cols-2 gap-2">
            <button
                type="submit"
                disabled={processing}
                onclick={() => simpan()}
                class="min-h-12 rounded-2xl border font-bold"
                >Simpan draft</button
            ><button
                type="button"
                disabled={processing}
                onclick={() => (konfirmasi = true)}
                class="min-h-12 rounded-2xl bg-primary font-bold text-primary-foreground"
                >Finalisasi Absensi</button
            >
        </div>{/if}
</div>
{#if dipilih}<button
        type="button"
        aria-label="Tutup pilihan status"
        class="fixed inset-0 z-40 bg-black/30"
        onclick={() => (dipilih = null)}
    ></button>
    <section
        class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-lg rounded-t-3xl bg-background p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
    >
        <h2 class="text-xl font-bold">{dipilih.nama}</h2>
        <div class="my-4 grid grid-cols-2 gap-2">
            {#each pilihan as p (p.value)}<button
                    type="button"
                    onclick={() => dipilih && (dipilih.status = p.value)}
                    class="min-h-12 rounded-2xl border font-bold {dipilih.status ===
                    p.value
                        ? 'bg-primary text-primary-foreground'
                        : ''}">{p.label}</button
                >{/each}
        </div>
        {#if dipilih.status !== 'hadir'}<label class="text-sm font-bold"
                >Catatan opsional<textarea
                    bind:value={dipilih.catatan}
                    class="mt-1 min-h-20 w-full rounded-2xl border bg-background p-3"
                ></textarea></label
            >{/if}<button
            type="button"
            onclick={() => (dipilih = null)}
            class="mt-3 min-h-12 w-full rounded-2xl bg-primary font-bold text-primary-foreground"
            >Selesai</button
        >
    </section>{/if}
{#if konfirmasi}<button
        type="button"
        class="fixed inset-0 z-40 bg-black/30"
        onclick={() => (konfirmasi = false)}
        aria-label="Batal finalisasi"
    ></button>
    <section
        role="dialog"
        aria-modal="true"
        class="fixed inset-x-4 top-1/2 z-50 mx-auto max-w-md -translate-y-1/2 rounded-3xl bg-background p-5 shadow-xl"
    >
        <h2 class="text-xl font-bold">Finalisasi {kelas.nama}?</h2>
        <p class="my-2 text-sm text-muted-foreground">
            {tanggal} · Total {siswa.length} siswa
        </p>
        <div class="my-4 grid grid-cols-5 gap-1 text-center">
            {#each pilihan as p (p.value)}<div>
                    <strong class="block">{ringkasan[p.value]}</strong><span
                        class="text-[10px]">{p.label}</span
                    >
                </div>{/each}
        </div>
        <p class="rounded-2xl bg-muted p-3 text-sm font-semibold">
            Hasil akan dapat dilihat orang tua dan tidak dapat diubah lagi oleh
            guru.
        </p>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <button
                type="button"
                onclick={() => (konfirmasi = false)}
                class="min-h-12 rounded-2xl border font-bold">Batal</button
            ><button
                type="submit"
                disabled={processing}
                onclick={() => simpan(true)}
                class="min-h-12 rounded-2xl bg-primary font-bold text-primary-foreground"
                >Ya, finalisasi</button
            >
        </div>
    </section>{/if}
{#if konfirmasiBuka}<button
        type="button"
        class="fixed inset-0 z-40 bg-black/30"
        onclick={() => (konfirmasiBuka = false)}
        aria-label="Batal membuka finalisasi"
    ></button>
    <section
        role="dialog"
        aria-modal="true"
        class="fixed inset-x-4 top-1/2 z-50 mx-auto max-w-md -translate-y-1/2 rounded-3xl bg-background p-5 shadow-xl"
    >
        <h2 class="text-xl font-bold">Batalkan finalisasi?</h2>
        <p class="my-2 text-sm text-muted-foreground">
            {kelas.nama} · {tanggal}
        </p>
        <p class="rounded-2xl bg-muted p-3 text-sm font-semibold">
            Absensi kembali jadi draft dan bisa diperbaiki. Nama Anda dan waktu
            pembatalan dicatat. Hanya berlaku untuk absensi hari ini.
        </p>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <button
                type="button"
                onclick={() => (konfirmasiBuka = false)}
                class="min-h-12 rounded-2xl border font-bold">Batal</button
            ><button
                type="submit"
                disabled={processing}
                onclick={buka}
                class="min-h-12 rounded-2xl bg-primary font-bold text-primary-foreground"
                >Ya, buka kembali</button
            >
        </div>
    </section>{/if}
