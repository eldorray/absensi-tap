<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import {
        SidebarGroup,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavIcon, NavItem } from '@/types';

    let {
        label,
        icon,
        items = [],
    }: {
        label: string;
        icon?: NavIcon;
        items: NavItem[];
    } = $props();

    const url = currentUrlState();

    const adaYangAktif = $derived(
        items.some((item) => url.isCurrentUrl(item.href, url.currentUrl)),
    );

    // Dibuka sendiri kalau salah satu isinya sedang dibuka, supaya halaman yang
    // aktif tidak tersembunyi di dalam kelompok yang tertutup.
    let dibukaManual = $state<boolean | null>(null);
    const terbuka = $derived(dibukaManual ?? adaYangAktif);
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarMenu>
        <SidebarMenuItem>
            <!--
                asChild dengan tombol sendiri, bukan onclick lewat props:
                Tooltip.Trigger dari bits-ui menyuntikkan onclick-nya sendiri
                sesudah props kita, jadi handler toggle-nya tertimpa dan
                kelompoknya tidak bisa ditutup.
            -->
            <SidebarMenuButton
                asChild
                isActive={adaYangAktif && !terbuka}
                tooltip={label}
            >
                {#snippet children(props)}
                    <button
                        {...props}
                        type="button"
                        class={props.class}
                        aria-expanded={terbuka}
                        onclick={() => (dibukaManual = !terbuka)}
                    >
                        {#if icon}
                            {@const Icon = icon}
                            <Icon class="size-4 shrink-0" />
                        {/if}
                        <span class="flex-1">{label}</span>
                        <ChevronRight
                            class="size-4 shrink-0 transition-transform duration-200 {terbuka
                                ? 'rotate-90'
                                : ''}"
                            aria-hidden="true"
                        />
                    </button>
                {/snippet}
            </SidebarMenuButton>

            {#if terbuka}
                <ul
                    class="mt-1 ml-4 grid gap-1 border-l border-sidebar-border pl-2 group-data-[collapsible=icon]:hidden"
                >
                    {#each items as item (toUrl(item.href))}
                        <li>
                            <SidebarMenuButton
                                asChild
                                isActive={url.isCurrentUrl(
                                    item.href,
                                    url.currentUrl,
                                )}
                            >
                                {#snippet children(props)}
                                    <Link
                                        {...props}
                                        href={toUrl(item.href)}
                                        class={props.class}
                                    >
                                        {#if item.icon}
                                            <item.icon
                                                class="size-4 shrink-0"
                                            />
                                        {/if}
                                        <span>{item.title}</span>
                                    </Link>
                                {/snippet}
                            </SidebarMenuButton>
                        </li>
                    {/each}
                </ul>
            {/if}
        </SidebarMenuItem>
    </SidebarMenu>
</SidebarGroup>
