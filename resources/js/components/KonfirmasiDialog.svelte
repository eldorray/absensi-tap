<script lang="ts">
    import TriangleAlert from 'lucide-svelte/icons/triangle-alert';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

    /**
     * Satu permintaan konfirmasi. Null berarti dialognya tertutup.
     */
    export type Konfirmasi = {
        judul: string;
        pesan: string;
        /** Tulisan tombol pelaksana, mis. "Hapus" atau "Cabut". */
        label?: string;
        /** false untuk tindakan yang tidak merusak, tombolnya jadi biasa. */
        destruktif?: boolean;
        aksi: () => void;
    };

    let { permintaan = $bindable(null) }: { permintaan?: Konfirmasi | null } =
        $props();

    const destruktif = $derived(permintaan?.destruktif ?? true);

    function jalankan(): void {
        const aksi = permintaan?.aksi;
        permintaan = null;
        aksi?.();
    }
</script>

<Dialog
    open={permintaan !== null}
    onOpenChange={(nilai) => {
        if (!nilai) {
            permintaan = null;
        }
    }}
>
    <DialogContent class="max-w-md">
        {#if permintaan}
            <div class="flex items-start gap-3">
                {#if destruktif}
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-2xl bg-destructive/10 text-destructive"
                    >
                        <TriangleAlert class="size-5" aria-hidden="true" />
                    </span>
                {/if}
                <div class="min-w-0">
                    <DialogTitle class="text-lg font-bold"
                        >{permintaan.judul}</DialogTitle
                    >
                    <p class="mt-1 text-sm text-muted-foreground">
                        {permintaan.pesan}
                    </p>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <Button variant="outline" onclick={() => (permintaan = null)}
                    >Batal</Button
                >
                <Button
                    variant={destruktif ? 'destructive' : 'default'}
                    onclick={jalankan}
                    data-test="konfirmasi-lanjut"
                >
                    {permintaan.label ?? 'Hapus'}
                </Button>
            </div>
        {/if}
    </DialogContent>
</Dialog>
