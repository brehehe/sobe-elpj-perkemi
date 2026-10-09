import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types';

function paginationLabel(label: string, index: number, total: number): string {
    if (index === 0) return 'Sebelumnya';
    if (index === total - 1) return 'Berikutnya';

    return label.replace(/<[^>]*>/g, '');
}

export function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;

    return (
        <nav aria-label="Paginasi" className="flex flex-wrap items-center justify-end gap-1 border-t border-line-200 px-4 py-3">
            {links.map((link, index) => {
                const label = paginationLabel(link.label, index, links.length);

                return link.url ? (
                    <Link
                        key={`${label}-${index}`}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={`grid min-h-11 min-w-11 place-items-center rounded-lg px-3 text-xs font-semibold ${link.active ? 'bg-primary-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}
                    >
                        {label}
                    </Link>
                ) : (
                    <span key={`${label}-${index}`} aria-disabled="true" className="grid min-h-11 min-w-11 place-items-center px-3 text-xs font-semibold text-slate-600">
                        {label}
                    </span>
                );
            })}
        </nav>
    );
}
