export type FocusTrapOptions = {
    /** Dipanggil saat Escape ditekan dan jebakan ini yang paling atas. */
    onEscape: () => void;
};

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
].join(',');

const TEXT_ENTRY = 'input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), textarea, [contenteditable="true"]';

/**
 * Tumpukan jebakan yang aktif. Hanya yang paling atas yang menanggapi
 * Escape dan Tab, supaya dialog konfirmasi di atas dialog lain tidak ikut
 * menutup keduanya sekaligus.
 */
const stack: HTMLElement[] = [];

function focusablesIn(node: HTMLElement): HTMLElement[] {
    return Array.from(node.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
        (element) => element.getClientRects().length > 0,
    );
}

function initialTarget(node: HTMLElement): HTMLElement {
    const autofocus = node.querySelector<HTMLElement>('[autofocus]');

    if (autofocus) {
        return autofocus;
    }

    const first = focusablesIn(node)[0];

    // Di layar sentuh, memfokuskan kolom isian langsung memunculkan keyboard
    // dan menutupi separuh dialog; fokus ke kontainernya saja.
    const coarsePointer = window.matchMedia?.('(pointer: coarse)').matches;

    if (!first || (coarsePointer && first.matches(TEXT_ENTRY))) {
        return node;
    }

    return first;
}

/**
 * Action Svelte: memindahkan fokus ke dalam dialog saat dibuka, menahan
 * Tab/Shift+Tab di dalamnya, menutup dengan Escape, lalu mengembalikan
 * fokus ke elemen pemicu saat dialog ditutup.
 */
export function focusTrap(node: HTMLElement, options: FocusTrapOptions) {
    let current = options;
    const previouslyFocused =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;

    stack.push(node);

    if (!node.contains(document.activeElement)) {
        initialTarget(node).focus({ preventScroll: true });
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (stack[stack.length - 1] !== node) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            current.onEscape();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusables = focusablesIn(node);

        if (focusables.length === 0) {
            event.preventDefault();
            node.focus({ preventScroll: true });

            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        const active = document.activeElement;
        const outside = !node.contains(active);

        if (event.shiftKey && (outside || active === first || active === node)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (outside || active === last)) {
            event.preventDefault();
            first.focus();
        }
    }

    document.addEventListener('keydown', handleKeydown, true);

    return {
        update(next: FocusTrapOptions): void {
            current = next;
        },
        destroy(): void {
            document.removeEventListener('keydown', handleKeydown, true);

            const index = stack.lastIndexOf(node);

            if (index !== -1) {
                stack.splice(index, 1);
            }

            if (previouslyFocused?.isConnected) {
                previouslyFocused.focus({ preventScroll: true });
            }
        },
    };
}
