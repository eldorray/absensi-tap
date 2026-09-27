<script lang="ts">
    import { Editor } from '@tiptap/core';
    import StarterKit from '@tiptap/starter-kit';
    import Bold from 'lucide-svelte/icons/bold';
    import Heading2 from 'lucide-svelte/icons/heading-2';
    import Italic from 'lucide-svelte/icons/italic';
    import Link2 from 'lucide-svelte/icons/link-2';
    import List from 'lucide-svelte/icons/list';
    import ListOrdered from 'lucide-svelte/icons/list-ordered';
    import Quote from 'lucide-svelte/icons/quote';
    import Redo2 from 'lucide-svelte/icons/redo-2';
    import Strikethrough from 'lucide-svelte/icons/strikethrough';
    import Underline from 'lucide-svelte/icons/underline';
    import Undo2 from 'lucide-svelte/icons/undo-2';
    import { onDestroy, onMount } from 'svelte';
    import type { NavIcon } from '@/types';

    /**
     * Editor isi berformat berbasis Tiptap (MIT). HTML-nya tetap dibersihkan
     * ulang di server (App\Support\IsiKaya); toolbar di sini hanya membuat tag
     * yang lolos pembersih itu.
     */
    let {
        value = $bindable(''),
        labelledby,
        invalid = false,
    }: {
        value?: string;
        labelledby?: string;
        invalid?: boolean;
    } = $props();

    let wadah = $state<HTMLDivElement | null>(null);
    let editor = $state.raw<Editor | null>(null);
    // Dinaikkan tiap transaksi supaya status aktif tombol ikut diperbarui.
    let versi = $state(0);

    onMount(() => {
        editor = new Editor({
            element: wadah,
            extensions: [
                StarterKit.configure({
                    heading: { levels: [2, 3] },
                    code: false,
                    codeBlock: false,
                    horizontalRule: false,
                    link: { openOnClick: false, autolink: true },
                }),
            ],
            content: value,
            editorProps: {
                attributes: {
                    class: 'isi-kaya min-h-32 px-4 py-3 text-[0.9375rem] focus:outline-none',
                    role: 'textbox',
                    'aria-multiline': 'true',
                    ...(labelledby ? { 'aria-labelledby': labelledby } : {}),
                },
            },
            onUpdate: ({ editor: e }) => {
                value = e.isEmpty ? '' : e.getHTML();
            },
            onTransaction: () => {
                versi++;
            },
        });
    });

    onDestroy(() => editor?.destroy());

    // Nilai diubah dari luar (mulai ubah, reset sesudah simpan): isi ulang editor.
    $effect(() => {
        const sekarang = value;

        if (!editor) {
            return;
        }

        const html = editor.isEmpty ? '' : editor.getHTML();

        if (sekarang !== html) {
            editor.commands.setContent(sekarang, { emitUpdate: false });
        }
    });

    type Tombol = {
        label: string;
        ikon: NavIcon;
        aktif?: () => boolean;
        jalankan: () => void;
    };

    function aturTautan(): void {
        if (!editor) {
            return;
        }

        const lama = editor.getAttributes('link').href as string | undefined;
        const url = window.prompt(
            'Alamat tautan (kosongkan untuk menghapus)',
            lama ?? 'https://',
        );

        if (url === null) {
            return;
        }

        if (url.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    }

    const kelompok: Tombol[][] = [
        [
            {
                label: 'Tebal',
                ikon: Bold,
                aktif: () => !!editor?.isActive('bold'),
                jalankan: () => editor?.chain().focus().toggleBold().run(),
            },
            {
                label: 'Miring',
                ikon: Italic,
                aktif: () => !!editor?.isActive('italic'),
                jalankan: () => editor?.chain().focus().toggleItalic().run(),
            },
            {
                label: 'Garis bawah',
                ikon: Underline,
                aktif: () => !!editor?.isActive('underline'),
                jalankan: () => editor?.chain().focus().toggleUnderline().run(),
            },
            {
                label: 'Coret',
                ikon: Strikethrough,
                aktif: () => !!editor?.isActive('strike'),
                jalankan: () => editor?.chain().focus().toggleStrike().run(),
            },
        ],
        [
            {
                label: 'Subjudul',
                ikon: Heading2,
                aktif: () => !!editor?.isActive('heading', { level: 2 }),
                jalankan: () =>
                    editor?.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                label: 'Daftar berpoin',
                ikon: List,
                aktif: () => !!editor?.isActive('bulletList'),
                jalankan: () =>
                    editor?.chain().focus().toggleBulletList().run(),
            },
            {
                label: 'Daftar bernomor',
                ikon: ListOrdered,
                aktif: () => !!editor?.isActive('orderedList'),
                jalankan: () =>
                    editor?.chain().focus().toggleOrderedList().run(),
            },
            {
                label: 'Kutipan',
                ikon: Quote,
                aktif: () => !!editor?.isActive('blockquote'),
                jalankan: () =>
                    editor?.chain().focus().toggleBlockquote().run(),
            },
            {
                label: 'Tautan',
                ikon: Link2,
                aktif: () => !!editor?.isActive('link'),
                jalankan: aturTautan,
            },
        ],
        [
            {
                label: 'Urungkan',
                ikon: Undo2,
                jalankan: () => editor?.chain().focus().undo().run(),
            },
            {
                label: 'Ulangi',
                ikon: Redo2,
                jalankan: () => editor?.chain().focus().redo().run(),
            },
        ],
    ];
</script>

<div
    class="overflow-hidden rounded-2xl border bg-background transition-[border-color,box-shadow] focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/45 {invalid
        ? 'border-destructive'
        : 'border-input hover:border-foreground/45'}"
>
    <div
        class="flex flex-wrap items-center gap-0.5 border-b border-border/70 bg-muted/40 px-1.5 py-1"
        role="toolbar"
        aria-label="Format isi"
    >
        {#each kelompok as tombols, i (i)}
            {#if i > 0}
                <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
            {/if}
            {#each tombols as tombol (tombol.label)}
                {@const aktif = versi >= 0 && (tombol.aktif?.() ?? false)}
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-lg transition-colors {aktif
                        ? 'bg-primary/15 text-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'}"
                    aria-label={tombol.label}
                    title={tombol.label}
                    aria-pressed={tombol.aktif ? aktif : undefined}
                    onmousedown={(e) => e.preventDefault()}
                    onclick={tombol.jalankan}
                >
                    <tombol.ikon class="size-4" />
                </button>
            {/each}
        {/each}
    </div>
    <div bind:this={wadah}></div>
</div>
