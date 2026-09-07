<script lang="ts">
    import type { NavIcon } from '@/types';

    type Nada = 'netral' | 'biru' | 'kuning' | 'hijau' | 'merah';

    let {
        ikon,
        label,
        nada = 'netral',
        disabled = false,
        onclick,
    }: {
        ikon: NavIcon;
        /** Dipakai sebagai tooltip sekaligus nama untuk pembaca layar. */
        label: string;
        nada?: Nada;
        disabled?: boolean;
        onclick: () => void;
    } = $props();

    // Warna baru muncul saat hover: deretan ikon berwarna-warni dalam satu
    // tabel jadi ramai, tapi warnanya tetap perlu ada saat tombol disentuh
    // supaya aksi merusak terbaca beda dari yang aman.
    const warna: Record<Nada, string> = {
        netral: 'hover:bg-muted hover:text-foreground',
        biru: 'hover:bg-blue-500/10 hover:text-blue-600 dark:hover:text-blue-400',
        kuning: 'hover:bg-amber-500/10 hover:text-amber-600 dark:hover:text-amber-400',
        hijau: 'hover:bg-emerald-500/10 hover:text-emerald-600 dark:hover:text-emerald-400',
        merah: 'hover:bg-destructive/10 hover:text-destructive',
    };

    const Ikon = $derived(ikon);
</script>

<button
    type="button"
    {onclick}
    {disabled}
    title={label}
    aria-label={label}
    class="grid size-9 shrink-0 place-items-center rounded-full text-muted-foreground transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-ring/45 focus-visible:outline-none active:scale-95 disabled:pointer-events-none disabled:opacity-40 {warna[
        nada
    ]}"
>
    <Ikon class="size-4" />
</button>
