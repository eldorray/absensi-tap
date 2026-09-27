<script module lang="ts">
    import { index as izinIndex } from '@/routes/orang-tua/izin';

    export const layout = {
        title: 'Izin Anak',
        breadcrumbs: [{ title: 'Izin Anak', href: izinIndex() }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import CalendarDays from 'lucide-svelte/icons/calendar-days';
    import FileHeart from 'lucide-svelte/icons/file-heart';
    import Paperclip from 'lucide-svelte/icons/paperclip';
    import Send from 'lucide-svelte/icons/send';
    import { untrack } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { lampiran, store } from '@/routes/orang-tua/izin';

    type Anak = { id: number; nama: string; nis: string };
    type IzinBaris = {
        id: number;
        siswa: string;
        nis: string;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        status: string;
        catatan_review: string | null;
        ada_lampiran: boolean;
    };

    let {
        anak,
        izins,
        tanggalMinimum,
        tanggalMaximum,
    }: {
        anak: Anak[];
        izins: IzinBaris[];
        tanggalMinimum: string;
        tanggalMaximum: string | null;
    } = $props();

    const siswaPertama = untrack(() => anak[0]?.id ?? 0);
    const form = useForm({
        siswa_id: siswaPertama,
        tipe: 'izin',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        lampiran: null as File | null,
    });

    let inputLampiran = $state<HTMLInputElement | null>(null);

    const labelStatus: Record<string, string> = {
        pending: 'Menunggu review',
        disetujui: 'Disetujui',
        ditolak: 'Ditolak',
    };

    const warnaStatus: Record<string, 'default' | 'secondary' | 'destructive'> =
        {
            pending: 'secondary',
            disetujui: 'default',
            ditolak: 'destructive',
        };

    function kirim(event: SubmitEvent): void {
        event.preventDefault();

        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                const siswaId = form.siswa_id;
                form.reset();
                form.siswa_id = siswaId;
                form.tipe = 'izin';

                if (inputLampiran) {
                    inputLampiran.value = '';
                }
            },
        });
    }
</script>

<AppHead title="Izin Anak" />

<div
    class="mx-auto flex w-full min-w-0 max-w-lg flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <section class="g-tile g-tone-green overflow-hidden">
        <div class="flex items-start gap-3">
            <span
                class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground"
            >
                <FileHeart class="size-6" aria-hidden="true" />
            </span>
            <div>
                <p
                    class="text-xs font-bold tracking-[0.12em] text-primary uppercase"
                >
                    Pemberitahuan sekolah
                </p>
                <h1 class="mt-1 text-xl font-black">Ajukan izin anak</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Kirim pemberitahuan izin atau sakit untuk anak yang telah
                    ditautkan ke akun Anda.
                </p>
            </div>
        </div>
    </section>

    {#if anak.length === 0}
        <section class="g-tile g-tone-plain text-center">
            <FileHeart
                class="mx-auto mb-3 size-9 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="font-bold">Belum ada anak tertaut</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Hubungi admin sekolah untuk menautkan akun Anda dengan data
                siswa.
            </p>
        </section>
    {:else}
        <section class="g-tile g-tone-plain min-w-0 max-w-full">
            <form class="grid min-w-0 gap-4" onsubmit={kirim}>
                <div class="grid min-w-0 gap-1.5">
                    <Label for="izin-siswa">Pilih anak</Label>
                    <select
                        id="izin-siswa"
                        bind:value={form.siswa_id}
                        class="w-full min-w-0 max-w-full h-12 truncate rounded-2xl border border-input bg-background px-4 text-base"
                    >
                        {#each anak as siswa (siswa.id)}
                            <option value={siswa.id}>
                                {siswa.nama} · NIS {siswa.nis}
                            </option>
                        {/each}
                    </select>
                    <InputError message={form.errors.siswa_id} />
                </div>

                <fieldset class="grid min-w-0 gap-2">
                    <legend class="text-sm font-medium">Jenis pengajuan</legend>
                    <div class="grid min-w-0 grid-cols-2 gap-2">
                        {#each [['izin', 'Izin'], ['sakit', 'Sakit']] as pilihan (pilihan[0])}
                            <label
                                class="flex min-h-12 cursor-pointer items-center justify-center rounded-2xl border px-4 font-bold transition-colors {form.tipe ===
                                pilihan[0]
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-input bg-background text-muted-foreground'}"
                            >
                                <input
                                    type="radio"
                                    class="sr-only"
                                    name="tipe"
                                    value={pilihan[0]}
                                    bind:group={form.tipe}
                                />
                                {pilihan[1]}
                            </label>
                        {/each}
                    </div>
                    <InputError message={form.errors.tipe} />
                </fieldset>

                <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="izin-mulai">Tanggal mulai</Label>
                        <Input
                            id="izin-mulai"
                            type="date"
                            min={tanggalMinimum}
                            max={tanggalMaximum ?? undefined}
                            class="h-12 min-w-0 max-w-full rounded-2xl"
                            bind:value={form.tanggal_mulai}
                        />
                        <InputError message={form.errors.tanggal_mulai} />
                    </div>
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="izin-selesai">Tanggal selesai</Label>
                        <Input
                            id="izin-selesai"
                            type="date"
                            min={form.tanggal_mulai || tanggalMinimum}
                            max={tanggalMaximum ?? undefined}
                            class="h-12 min-w-0 max-w-full rounded-2xl"
                            bind:value={form.tanggal_selesai}
                        />
                        <InputError message={form.errors.tanggal_selesai} />
                    </div>
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="izin-alasan">Alasan</Label>
                    <textarea
                        id="izin-alasan"
                        rows="4"
                        maxlength="1000"
                        placeholder="Jelaskan alasan izin atau kondisi sakit anak"
                        bind:value={form.alasan}
                        class="w-full min-w-0 max-w-full resize-none rounded-2xl border border-input bg-background px-4 py-3 text-base"
                    ></textarea>
                    <div class="flex justify-between gap-3">
                        <InputError message={form.errors.alasan} />
                        <span class="ml-auto text-xs text-muted-foreground">
                            {form.alasan.length}/1000
                        </span>
                    </div>
                </div>

                <div class="grid min-w-0 gap-1.5">
                    <Label for="izin-lampiran">
                        Lampiran <span class="text-muted-foreground"
                            >(opsional)</span
                        >
                    </Label>
                    <label
                        for="izin-lampiran"
                        class="flex min-h-14 w-full min-w-0 max-w-full cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-input bg-muted/35 px-4 text-sm"
                    >
                        <Paperclip
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1 truncate">
                            {form.lampiran?.name ??
                                'Pilih PDF, JPG, atau PNG · maks. 2 MB'}
                        </span>
                    </label>
                    <input
                        id="izin-lampiran"
                        bind:this={inputLampiran}
                        type="file"
                        class="sr-only"
                        accept="application/pdf,image/jpeg,image/png"
                        onchange={(event) => {
                            form.lampiran =
                                event.currentTarget.files?.item(0) ?? null;
                        }}
                    />
                    <InputError message={form.errors.lampiran} />
                </div>

                <Button
                    type="submit"
                    class="min-h-12 rounded-2xl"
                    disabled={form.processing}
                >
                    <Send class="size-4" aria-hidden="true" />
                    {form.processing ? 'Mengirim…' : 'Kirim pengajuan'}
                </Button>
            </form>
        </section>
    {/if}

    <section class="g-tile g-tone-plain">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-black">Riwayat pengajuan</h2>
                <p class="text-sm text-muted-foreground">
                    Status terbaru dari admin sekolah.
                </p>
            </div>
            <Badge variant="outline">{izins.length}</Badge>
        </div>

        {#if izins.length === 0}
            <div class="rounded-2xl bg-muted/55 px-4 py-5 text-center">
                <CalendarDays
                    class="mx-auto mb-2 size-7 text-muted-foreground"
                    aria-hidden="true"
                />
                <p class="font-semibold">Belum ada pengajuan</p>
            </div>
        {:else}
            <ul class="grid gap-3">
                {#each izins as izin (izin.id)}
                    <li
                        class="rounded-2xl border border-border bg-background p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold">{izin.siswa}</p>
                                <p class="text-xs text-muted-foreground">
                                    NIS {izin.nis}
                                </p>
                            </div>
                            <Badge
                                variant={warnaStatus[izin.status] ??
                                    'secondary'}
                            >
                                {labelStatus[izin.status] ?? izin.status}
                            </Badge>
                        </div>
                        <p class="mt-3 text-sm font-semibold capitalize">
                            {izin.tipe} · {izin.tanggal_mulai} – {izin.tanggal_selesai}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {izin.alasan}
                        </p>
                        {#if izin.catatan_review}
                            <p
                                class="mt-3 rounded-xl bg-muted px-3 py-2 text-sm"
                            >
                                Catatan admin: {izin.catatan_review}
                            </p>
                        {/if}
                        {#if izin.ada_lampiran}
                            <a
                                href={lampiran.url(izin.id)}
                                class="mt-3 inline-flex min-h-10 items-center gap-2 font-semibold text-primary"
                            >
                                <Paperclip class="size-4" aria-hidden="true" />
                                Unduh lampiran
                            </a>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
