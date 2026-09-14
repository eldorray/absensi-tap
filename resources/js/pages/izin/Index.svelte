<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Izin', href: '/izin' }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { store as ajukanIzin } from '@/actions/App/Http/Controllers/IzinController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type IzinBaris = {
        id: number;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        status: string;
        catatan_review: string | null;
        ada_lampiran: boolean;
    };

    let { izins }: { izins: IzinBaris[] } = $props();

    const form = useForm({
        tipe: 'izin',
        tanggal_mulai: '',
        tanggal_selesai: '',
        alasan: '',
        lampiran: null as File | null,
    });

    const warnaStatus: Record<
        string,
        'default' | 'secondary' | 'outline' | 'destructive'
    > = {
        pending: 'secondary',
        disetujui: 'default',
        ditolak: 'destructive',
    };

    function kirim(event: SubmitEvent): void {
        event.preventDefault();

        form.post(ajukanIzin.url(), {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
</script>

<AppHead title="Izin" />

<div
    class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <section class="g-tile g-tone-plain">
        <h3>Ajukan izin</h3>

        <form class="grid gap-4" onsubmit={kirim}>
            <div class="grid gap-2">
                <Label for="tipe">Jenis</Label>
                <select
                    id="tipe"
                    bind:value={form.tipe}
                    class="h-10 rounded-md border border-input bg-background px-3 text-base"
                >
                    <option value="izin">Izin</option>
                    <option value="sakit">Sakit</option>
                    <option value="cuti">Cuti</option>
                </select>
                <InputError message={form.errors.tipe} />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="mulai">Tanggal mulai</Label>
                    <Input
                        id="mulai"
                        type="date"
                        bind:value={form.tanggal_mulai}
                    />
                    <InputError message={form.errors.tanggal_mulai} />
                </div>
                <div class="grid gap-2">
                    <Label for="selesai">Tanggal selesai</Label>
                    <Input
                        id="selesai"
                        type="date"
                        bind:value={form.tanggal_selesai}
                    />
                    <InputError message={form.errors.tanggal_selesai} />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="alasan">Alasan</Label>
                <textarea
                    id="alasan"
                    rows="3"
                    bind:value={form.alasan}
                    class="rounded-md border border-input bg-background p-3 text-base"
                ></textarea>
                <InputError message={form.errors.alasan} />
            </div>

            <div class="grid gap-2">
                <Label for="lampiran"
                    >Lampiran (opsional, PDF/JPG/PNG, maks 2 MB)</Label
                >
                <input
                    id="lampiran"
                    type="file"
                    accept="application/pdf,image/jpeg,image/png"
                    onchange={(event) => {
                        form.lampiran = event.currentTarget.files?.[0] ?? null;
                    }}
                    class="text-sm"
                />
                <InputError message={form.errors.lampiran} />
            </div>

            <Button type="submit" disabled={form.processing}
                >Kirim pengajuan</Button
            >
        </form>
    </section>

    <section class="g-tile g-tone-plain">
        <h3>Pengajuan saya</h3>

        {#if izins.length === 0}
            <p>Belum ada pengajuan izin.</p>
        {:else}
            <ul class="divide-y divide-border">
                {#each izins as izin (izin.id)}
                    <li class="grid gap-1 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium capitalize"
                                >{izin.tipe}</span
                            >
                            <Badge
                                variant={warnaStatus[izin.status] ??
                                    'secondary'}
                            >
                                {izin.status}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {izin.tanggal_mulai} – {izin.tanggal_selesai}
                        </p>
                        <p class="text-sm">{izin.alasan}</p>
                        {#if izin.catatan_review}
                            <p class="text-sm text-muted-foreground">
                                Catatan TU: {izin.catatan_review}
                            </p>
                        {/if}
                        {#if izin.ada_lampiran}
                            <a
                                href={`/izin/${izin.id}/lampiran`}
                                class="text-sm font-medium text-primary"
                            >
                                Unduh lampiran
                            </a>
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>
</div>
