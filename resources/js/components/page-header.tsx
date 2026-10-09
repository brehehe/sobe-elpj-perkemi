import type { ReactNode } from 'react';

export function PageHeader({ title, description, eyebrow, actions }: { title: string; description?: string; eyebrow?: string; actions?: ReactNode }) {
    return (
        <header className="flex flex-col gap-4 border-b border-line-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {eyebrow && <p className="mb-2 text-xs font-semibold text-primary-600">{eyebrow}</p>}
                <h1 className="text-2xl font-bold tracking-[-0.03em] text-ink-950 sm:text-[30px]">{title}</h1>
                {description && <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-600">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </header>
    );
}
