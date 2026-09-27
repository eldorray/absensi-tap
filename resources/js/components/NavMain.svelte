<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    let {
        items = [],
        label = 'Platform',
    }: {
        items: NavItem[];
        label?: string;
    } = $props();

    const url = currentUrlState();
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>{label}</SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (toUrl(item.href))}
            <SidebarMenuItem>
                <SidebarMenuButton
                    asChild
                    isActive={url.isCurrentUrl(item.href, url.currentUrl)}
                    tooltip={item.title}
                >
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(item.href)}
                            class={props.class}
                        >
                            {#if item.icon}
                                <item.icon class="size-4 shrink-0" />
                            {/if}
                            <span>{item.title}</span>
                            {#if item.badge}
                                <span
                                    class="ml-auto min-w-5 rounded-full bg-primary px-1.5 text-center text-[0.6875rem] leading-5 font-bold text-primary-foreground"
                                    aria-label="{item.badge} menunggu"
                                    >{item.badge}</span
                                >
                            {/if}
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>
