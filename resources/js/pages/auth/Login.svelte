<script module lang="ts">
    export const layout = {
        title: 'Masuk',
        description: 'Pakai email dan password dari TU sekolah.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
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

    let {
        status = '',
        canResetPassword,
    }: {
        status?: string;
        canResetPassword: boolean;
    } = $props();
</script>

<AppHead title="Masuk" />

{#if status}
    <p
        class="mb-5 rounded-2xl bg-[var(--g-green-c)] px-4 py-3 text-sm font-medium text-[var(--g-green-ink)]"
    >
        {status}
    </p>
{/if}

<Form {...store.form()} resetOnSuccess={['password']} class="grid gap-5">
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
    {/snippet}
</Form>
