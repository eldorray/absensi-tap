<?php

test('dialog bisa ditutup dengan Escape, menjebak fokus, dan mengembalikannya', function () {
    $jebakan = file_get_contents(resource_path('js/components/ui/dialog/focus-trap.ts'));

    expect($jebakan)
        ->toContain("event.key === 'Escape'")
        ->toContain("event.key !== 'Tab'")
        ->toContain('event.shiftKey')
        // Fokus dikembalikan ke elemen pemicu saat dialog ditutup.
        ->toContain('previouslyFocused.focus(');
});

test('konten dialog punya nama, tombol tutup 44px, dan jarak tepi di layar kecil', function () {
    $konten = file_get_contents(resource_path('js/components/ui/dialog/DialogContent.svelte'));
    $judul = file_get_contents(resource_path('js/components/ui/dialog/DialogTitle.svelte'));

    expect($konten)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby={titleId}')
        ->toContain('use:focusTrap={{ onEscape: close }}')
        ->toContain('aria-label="Tutup"')
        ->toContain('size-11')
        ->toContain('w-[calc(100%-2rem)]')
        ->not->toContain('aria-label="Close"');

    // Judul memakai id dari konteks dan tidak lagi mengabaikan prop class.
    expect($judul)
        ->toContain('id={context?.titleId}')
        ->toContain('className');
});

test('sheet juga bisa ditutup dengan Escape dan berlabel bahasa Indonesia', function () {
    expect(file_get_contents(resource_path('js/components/ui/sheet/SheetContent.svelte')))
        ->toContain('use:focusTrap={{ onEscape: close }}')
        ->toContain('aria-label="Tutup"')
        ->not->toContain('Close');
});

test('tombol ikon punya area sentuh 44px dan tooltip saat fokus keyboard', function () {
    expect(file_get_contents(resource_path('js/components/TombolIkon.svelte')))
        ->toContain('size-11')
        ->not->toContain('size-9')
        ->toContain('group-focus-visible:block')
        ->toContain('aria-label={label}');
});
