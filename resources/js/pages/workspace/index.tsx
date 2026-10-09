import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

type Row = { id: string; primary: string; secondary: string; status: string; meta: string };

export default function WorkspaceIndex({ module, title, description, rows }: { module: string; title: string; description: string; rows: Row[] }) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => rows.filter((row) => `${row.primary} ${row.secondary} ${row.status} ${row.meta}`.toLowerCase().includes(query.toLowerCase())), [query, rows]);

    return <>
        <Head title={title} />
        <PageHeader eyebrow="Workspace terhubung" title={title} description={description} />
        <section className="panel mt-6 overflow-hidden">
            <div className="flex flex-col gap-4 border-b border-line-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div><h2 className="text-sm font-bold">Data operasional</h2><p className="mt-1 text-xs text-muted-600">{rows.length} record terbaru · dibatasi sesuai unit dan kewenangan.</p></div>
                <label className="relative block sm:w-72"><span className="sr-only">Cari dalam {title}</span><Search aria-hidden="true" className="pointer-events-none absolute top-3.5 left-3 size-4 text-slate-400" /><input className="field pl-10" type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder={`Cari ${title.toLowerCase()}…`} /></label>
            </div>
            {filtered.length ? <div className="overflow-x-auto" role="region" aria-label={`Daftar ${title} yang dapat digulir`} tabIndex={0}><table className="w-full min-w-[760px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-600"><tr><th className="px-5 py-3">Record</th><th className="px-4 py-3">Keterangan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Metadata</th></tr></thead><tbody>{filtered.map((row) => <tr key={row.id} className="border-t border-line-200 hover:bg-slate-50/70"><td className="px-5 py-4 font-bold text-ink-950">{row.primary}</td><td className="max-w-xl px-4 py-4 text-xs leading-5 text-muted-600">{row.secondary}</td><td className="px-4 py-4"><StatusBadge status={row.status} /></td><td className="px-4 py-4 text-right text-xs font-semibold text-slate-500">{row.meta}</td></tr>)}</tbody></table></div> : <EmptyState title="Tidak ada hasil" description={query ? 'Ubah kata kunci pencarian.' : 'Belum ada record yang dapat ditampilkan.'} />}
        </section>
        {['participants', 'finance', 'evidence', 'evaluations'].includes(module) && <div className="mt-4 flex justify-end"><Link href="/activities" className="btn-secondary">Kelola melalui Activity-ID<ArrowRight className="size-4" /></Link></div>}
    </>;
}
