<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog';
    import { Label } from '@/components/ui/label';
</script>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Hapus akun"
        description="Hapus akun beserta semua datanya"
    />
    <div
        class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
    >
        <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
            <p class="font-medium">Peringatan</p>
            <p class="text-sm">
                Hati-hati, tindakan ini tidak bisa dibatalkan.
            </p>
        </div>
        <Dialog>
            <DialogTrigger>
                <Button variant="destructive" data-test="delete-user-button"
                    >Hapus akun</Button
                >
            </DialogTrigger>
            <DialogContent>
                <Form
                    {...ProfileController.destroy.form()}
                    class="space-y-6"
                    options={{ preserveScroll: true }}
                >
                    {#snippet children({ errors, processing })}
                        <div class="space-y-3">
                            <DialogTitle
                                >Yakin ingin menghapus akun?</DialogTitle
                            >
                            <DialogDescription>
                                Setelah akun dihapus, semua data di dalamnya
                                ikut terhapus permanen. Masukkan password untuk
                                mengonfirmasi penghapusan akun.
                            </DialogDescription>
                        </div>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Password</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                placeholder="Password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose>
                                <Button variant="secondary">Batal</Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="confirm-delete-user-button"
                            >
                                Hapus akun
                            </Button>
                        </DialogFooter>
                    {/snippet}
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</div>
