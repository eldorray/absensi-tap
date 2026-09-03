<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="flex min-h-svh flex-col items-center justify-center bg-muted px-5 py-10 sm:px-8"
>
    <div class="w-full max-w-[26rem]">
        <Link
            href={home()}
            class="mx-auto mb-8 flex w-fit items-center gap-3 text-[0.9375rem] font-semibold"
        >
            <span
                class="flex size-10 items-center justify-center rounded-[13px] bg-primary text-primary-foreground"
            >
                <AppLogoIcon class="size-5 fill-current" />
            </span>
            <span class="sr-only">Back to home</span>
        </Link>

        <div class="rounded-[32px] bg-card px-6 py-9 sm:px-9">
            <h1 class="auth-title">{title}</h1>
            {#if description}
                <p
                    class="mt-3 text-[0.9375rem] leading-relaxed text-muted-foreground"
                >
                    {description}
                </p>
            {/if}

            <div class="mt-8">
                {@render children?.()}
            </div>
        </div>
    </div>
</div>

<style>
    .auth-title {
        font-size: clamp(1.625rem, 5vw, 2rem);
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -0.03em;
        text-wrap: balance;
    }
</style>
