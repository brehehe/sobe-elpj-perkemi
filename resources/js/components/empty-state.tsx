import { FileQuestion } from 'lucide-react';

export function EmptyState({ title = 'Belum ada data', description = 'Data akan tampil setelah proses pertama selesai.' }: { title?: string; description?: string }) {
    return (
        <div className="flex min-h-48 flex-col items-center justify-center px-6 py-10 text-center">
            <span className="grid size-11 place-items-center rounded-xl bg-slate-100 text-slate-500"><FileQuestion className="size-5" /></span>
            <h3 className="mt-3 text-sm font-bold text-slate-800">{title}</h3>
            <p className="mt-1 max-w-md text-xs leading-5 text-muted-600">{description}</p>
        </div>
    );
}
