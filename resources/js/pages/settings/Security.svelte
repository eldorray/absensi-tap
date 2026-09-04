<script module lang="ts">
    import { edit } from '@/routes/security';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, page } from '@inertiajs/svelte';
    import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import ManagePasskeys from '@/components/ManagePasskeys.svelte';
    import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.svelte';
    import ManageTwoFactor from '@/components/ManageTwoFactor.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    const canManageTwoFactor = $derived(Boolean(page.props.canManageTwoFactor));
    const requiresConfirmation = $derived(
        Boolean(page.props.requiresConfirmation),
    );
    const twoFactorEnabled = $derived(Boolean(page.props.twoFactorEnabled));
    const canManagePasskeys = $derived(Boolean(page.props.canManagePasskeys));
    const passkeys = $derived(
        (Array.isArray(page.props.passkeys)
            ? page.props.passkeys
            : []) as ManagePasskeysProps['passkeys'],
    );

    type Perangkat = {
        id: number;
        label: string;
        status: string;
        terdaftar: string | null;
    };

    let {
        passwordRules,
        perangkats,
    }: { passwordRules: string; perangkats: Perangkat[] } = $props();
</script>

<AppHead title="Security settings" />

<h1 class="sr-only">Security settings</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Update password"
        description="Ensure your account is using a long, random password to stay secure"
    />

    <Form
        {...SecurityController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
        resetOnSuccess
        resetOnError={['password', 'password_confirmation', 'current_password']}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Current password"
                />
                <InputError message={errors.current_password} />
            </div>

            <div class="grid gap-2">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="New password"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        {/snippet}
    </Form>
</div>

<ManageTwoFactor
    {canManageTwoFactor}
    {requiresConfirmation}
    {twoFactorEnabled}
/>

<ManagePasskeys {canManagePasskeys} {passkeys} />

<div class="space-y-6">
    <Heading
        variant="small"
        title="Perangkat absensi"
        description="HP yang terikat ke akunmu untuk absen"
    />

    <ul
        class="divide-y divide-border overflow-hidden rounded-lg border border-border"
    >
        {#each perangkats as perangkat (perangkat.id)}
            <li class="flex items-center justify-between gap-3 p-4 text-sm">
                <span>
                    <span class="font-medium">{perangkat.label}</span>
                    <span class="block text-muted-foreground">
                        Terdaftar {perangkat.terdaftar ?? '-'}
                    </span>
                </span>
                <Badge
                    variant={perangkat.status === 'active'
                        ? 'default'
                        : 'outline'}
                >
                    {perangkat.status}
                </Badge>
            </li>
        {/each}
    </ul>

    <p class="text-sm text-muted-foreground">
        Ganti HP? Buka aplikasi dari HP baru sekali, lalu minta TU
        menyetujuinya. Satu guru hanya boleh punya satu HP aktif.
    </p>
</div>
