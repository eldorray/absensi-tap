<script module lang="ts">
    export const layout = {
        title: 'Konfirmasi password',
        description:
            'Ini area yang dilindungi. Masukkan password dulu untuk melanjutkan.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import {
        index as confirmOptions,
        store as confirmStore,
    } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasskeyVerify from '@/components/PasskeyVerify.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { store } from '@/routes/password/confirm';
</script>

<AppHead title="Konfirmasi password" />

<PasskeyVerify
    routes={{
        options: confirmOptions(),
        submit: confirmStore(),
    }}
    label="Konfirmasi dengan passkey"
    loadingLabel="Mengonfirmasi…"
    separator="Atau konfirmasi dengan password"
/>

<Form {...store.form()} resetOnSuccess>
    {#snippet children({ errors, processing })}
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />
                <InputError message={errors.password} />
            </div>

            <div class="flex items-center">
                <Button
                    type="submit"
                    class="w-full"
                    disabled={processing}
                    data-test="confirm-password-button"
                >
                    {#if processing}<Spinner />{/if}
                    Konfirmasi password
                </Button>
            </div>
        </div>
    {/snippet}
</Form>
