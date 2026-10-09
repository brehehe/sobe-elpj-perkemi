import { AlertTriangle, LoaderCircle, X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    processing?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Lanjutkan',
    cancelLabel = 'Batal',
    destructive = false,
    processing = false,
    onConfirm,
    onClose,
}: ConfirmDialogProps) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const cancelRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        const dialog = dialogRef.current;
        if (!dialog) return;

        if (open && !dialog.open) {
            dialog.showModal();
            requestAnimationFrame(() => cancelRef.current?.focus());
        } else if (!open && dialog.open) {
            dialog.close();
        }
    }, [open]);

    return (
        <dialog
            ref={dialogRef}
            aria-labelledby="confirm-dialog-title"
            aria-describedby="confirm-dialog-description"
            onCancel={(event) => { event.preventDefault(); if (!processing) onClose(); }}
            onClose={() => { if (open && !processing) onClose(); }}
            className="m-auto w-[min(92vw,30rem)] rounded-xl border border-line-200 bg-white p-0 text-ink-950 shadow-2xl backdrop:bg-navy-950/55"
        >
            <div className="flex items-start gap-4 border-b border-line-200 p-5">
                <span className={`grid size-10 shrink-0 place-items-center rounded-lg ${destructive ? 'bg-red-50 text-danger-600' : 'bg-amber-50 text-amber-700'}`}>
                    <AlertTriangle aria-hidden="true" className="size-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 id="confirm-dialog-title" className="text-base font-bold">{title}</h2>
                    <div id="confirm-dialog-description" className="mt-2 text-sm leading-6 text-muted-600">{description}</div>
                </div>
                <button type="button" aria-label="Tutup konfirmasi" disabled={processing} onClick={onClose} className="grid size-11 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-50">
                    <X aria-hidden="true" className="size-5" />
                </button>
            </div>
            <div className="flex flex-col-reverse gap-3 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
                <button ref={cancelRef} type="button" disabled={processing} onClick={onClose} className="btn-secondary">{cancelLabel}</button>
                <button type="button" disabled={processing} onClick={onConfirm} className={destructive ? 'btn-danger' : 'btn-primary'}>
                    {processing && <LoaderCircle aria-hidden="true" className="size-4 animate-spin" />}{confirmLabel}
                </button>
            </div>
        </dialog>
    );
}
