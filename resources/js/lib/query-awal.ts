import { page } from '@inertiajs/svelte';

/**
 * Nilai query string halaman saat dibuka, untuk filter awal daftar yang
 * disaring di browser. Dipakai tautan dashboard, mis. ?saring=tanpa_unit.
 */
export function queryAwal(nama: string): string | null {
    return new URLSearchParams(page.url.split('?')[1] ?? '').get(nama);
}
