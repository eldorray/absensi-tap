<script module lang="ts">
    export const layout = {
        title: 'Masuk',
        description: 'Pakai email dan password dari TU sekolah.',
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { store } from '@/routes/login';
    import { request } from '@/routes/password';
    import { home } from '@/routes';
    import { toUrl } from '@/lib/utils';

    let {
        status = '',
        canResetPassword,
    }: {
        status?: string;
        canResetPassword: boolean;
    } = $props();
</script>

<AppHead title="Masuk" />

<p class="akses"><ShieldCheck class="size-4" /> Akses khusus warga sekolah</p>

{#if status}
    <p
        class="mb-5 rounded-2xl bg-[var(--g-green-c)] px-4 py-3 text-sm font-medium text-[var(--g-green-ink)]"
    >
        {status}
    </p>
{/if}

<Form
    {...store.form()}
    resetOnSuccess={['password']}
    class="form-login mt-7 grid gap-5"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                inputmode="email"
                placeholder="nama@sekolah.sch.id"
            />
            <InputError message={errors.email} />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Password"
            />
            <InputError message={errors.password} />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <Label for="remember" class="flex items-center gap-2.5">
                <Checkbox id="remember" name="remember" />
                <span class="text-[0.9375rem]">Ingat saya di HP ini</span>
            </Label>
            {#if canResetPassword}
                <TextLink href={request()} class="text-sm">
                    Lupa password?
                </TextLink>
            {/if}
        </div>

        <Button
            type="submit"
            class="mt-1 min-h-14 w-full rounded-[1.35rem] text-base font-bold"
            disabled={processing}
            data-test="login-button"
        >
            {#if processing}<Spinner />{/if}
            Masuk
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Belum punya akun? Akun dibuatkan TU sekolah.
        </p>

        <Link href={toUrl(home())} class="kembali">
            <ArrowLeft class="size-4" /> Kembali ke halaman depan
        </Link>
    {/snippet}
</Form>

<style>
    .akses {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        background: var(--g-blue-c);
        padding: 0.5rem 0.75rem;
        color: var(--g-blue-ink);
        font-size: 0.75rem;
        font-weight: 700;
    }
    @media (max-width: 899px) {
        .akses {
            margin-top: 0.25rem;
            padding: 0;
            background: transparent;
            color: var(--g-blue-ink-2);
        }
    }
    :global(.form-login input) {
        min-height: 3.65rem;
        border-color: color-mix(in srgb, var(--g-blue) 22%, var(--g-line));
        border-radius: 1.1rem;
        background: var(--g-surface);
        padding-inline: 1rem;
    }
    :global(.form-login input:focus) {
        background: var(--g-bg);
    }
    :global(.form-login button[type='submit']) {
        min-height: 3.65rem;
        box-shadow: 0 12px 28px -14px
            color-mix(in srgb, var(--g-blue) 70%, transparent);
        transition: transform 0.2s var(--g-emphasized);
    }
    @media (display-mode: standalone) {
        :global(.form-login button[type='submit']) {
            min-height: 4rem;
        }
    }
    :global(.form-login button[type='submit']:hover) {
        transform: translateY(-1px);
    }
    :global(.form-login .kembali) {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 2.75rem;
        color: var(--g-ink-2);
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
    }
    :global(.form-login .kembali:hover) {
        color: var(--g-blue);
    }
</style>
