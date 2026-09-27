export type DialogContext = {
    open: () => boolean;
    setOpen: (value: boolean) => void;
    /** Id untuk DialogTitle, dirujuk aria-labelledby milik DialogContent. */
    titleId: string;
};

export const DIALOG_CONTEXT = Symbol('dialog');
