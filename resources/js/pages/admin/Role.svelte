<script module lang="ts">
    import { index } from '@/routes/admin/role';
    export const layout = {
        breadcrumbs: [{ title: 'Kelola role', href: index() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { index as userIndex } from '@/routes/admin/user';

    type Pemegang = {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
    };
    type R = {
        value: string;
        label: string;
        keterangan: string;
        akses: string[];
        jumlah: number;
        jumlah_aktif: number;
        pemegang: Pemegang[];
    };

    let { roles }: { roles: R[] } = $props();
</script>

<AppHead title="Kelola role" />

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-5 sm:px-6">
    <section class="g-tile g-tone-plain gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3>Kelola role</h3>
                <p class="text-muted-foreground">
                    Role dan aksesnya ditetapkan di kode supaya otorisasi tidak
                    bisa dilebarkan dari layar. Yang diatur di sini: siapa
                    memegang role apa.
                </p>
            </div>
            <Button
                variant="outline"
                onclick={() => router.visit(toUrl(userIndex()))}
                >Ubah role user</Button
            >
        </div>

        <div class="grid gap-3">
            {#each roles as r (r.value)}
                <article class="rounded-2xl border border-border px-4 py-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="size-4" aria-hidden="true" />
                            <p class="font-semibold">{r.label}</p>
                            <Badge variant="secondary"
                                >{r.jumlah_aktif} aktif dari {r.jumlah}</Badge
                            >
                        </div>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {r.keterangan}
                    </p>

                    <ul class="mt-3 grid gap-1">
                        {#each r.akses as akses (akses)}
                            <li class="flex items-start gap-2 text-sm">
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />
                                {akses}
                            </li>
                        {/each}
                    </ul>

                    {#if r.pemegang.length > 0}
                        <div class="mt-3 border-t border-border pt-3">
                            <p
                                class="text-xs font-bold tracking-[0.12em] text-muted-foreground uppercase"
                            >
                                Pemegang
                            </p>
                            <ul class="mt-2 grid gap-1">
                                {#each r.pemegang as p (p.id)}
                                    <li
                                        class="flex flex-wrap items-center gap-2 text-sm"
                                    >
                                        {p.name}
                                        <span class="text-muted-foreground"
                                            >{p.email}</span
                                        >
                                        {#if !p.is_active}
                                            <Badge variant="outline"
                                                >Nonaktif</Badge
                                            >
                                        {/if}
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/if}
                </article>
            {/each}
        </div>
    </section>
</div>
