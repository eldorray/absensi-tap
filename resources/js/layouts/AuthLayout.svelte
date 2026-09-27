<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Toaster } from '@/components/ui/sonner';
    import AuthLayout from '@/layouts/auth/AuthSimpleLayout.svelte';

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

<AuthLayout {title} {description}>
    {@render children?.()}
</AuthLayout>
<!-- Toast juga dibutuhkan di halaman masuk: sesi habis (419) dan batas
     percobaan dilaporkan lewat toast. -->
<Toaster />
