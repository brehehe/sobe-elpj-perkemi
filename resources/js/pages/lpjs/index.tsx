import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { LpjSummary, Paginated, SharedProps, UnitSummary } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, BadgeCheck, FileText, Filter, LoaderCircle, RotateCcw, Search, Workflow } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';

type LpjFilters = { q: string; status: string; unit: string | null; sort: string };
type LpjOption = { value: string; label: string };
type LpjIndexProps = {
    summary: { total: number; draft: number; in_process: number; finalized: number; filtered: number };
    scope: { can_view_all_units: boolean; label: string };
    filters: LpjFilters;
    options: { statuses: LpjOption[]; units: UnitSummary[] };
    lpjs: Paginated<LpjSummary>;
};

function Metric({ label, value, detail, icon }: { label: string; value: number; detail: string; icon: ReactNode }) {
    return <div className="px-5 py-4 sm:px-6">
        <dt className="flex items-center gap-2 text-[10px] font-bold tracking-wide text-muted-600 uppercase">{icon}{label}</dt>
        <dd className="mt-2">
            <span className="tabular block text-2xl font-bold tracking-tight text-ink-950">{value}</span>
            <span className="mt-1 block text-[11px] text-muted-600">{detail}</span>
        </dd>
    </div>;
}

export default function LpjsIndex({ summary, scope, filters, options, lpjs }: LpjIndexProps) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [form, setForm] = useState<LpjFilters>(filters);
    const [filtering, setFiltering] = useState(false);
    const activeFilterCount = Object.entries(filters).filter(([key, value]) => value && !(key === 'sort' && value === 'latest')).length;

    const update = (key: keyof LpjFilters, value: string) => setForm((current) => ({ ...current, [key]: value || (key === 'unit' ? null : '') }));
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/lpjs', Object.fromEntries(Object.entries(form).filter(([, value]) => value)), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => setFiltering(true),
            onFinish: () => setFiltering(false),
        });
    };

    return <>
        <Head title="e-LPJ" />
        <PageHeader eyebrow="Pertanggungjawaban" title="e-LPJ" description={`Kendali LPJ untuk ${scope.label}. Setiap pengajuan membentuk snapshot immutable; koreksi berikutnya menghasilkan versi baru tanpa menimpa histori.`} />

        <section aria-labelledby="lpj-summary" className="panel mt-6 overflow-hidden">
            <div className="border-b border-line-200 bg-slate-50/70 px-5 py-4 sm:px-6">
                <h2 id="lpj-summary" className="text-sm font-bold text-ink-950">Ringkasan {scope.label}</h2>
                <p className="mt-1 text-[11px] text-muted-600">Distribusi status seluruh LPJ dalam cakupan akses Anda.</p>
            </div>
            <dl className="grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                <Metric icon={<FileText aria-hidden="true" className="size-4 text-primary-600" />} label="Total LPJ" value={summary.total} detail="Seluruh status dalam cakupan" />
                <Metric icon={<FileText aria-hidden="true" className="size-4 text-amber-600" />} label="Draf & revisi" value={summary.draft} detail="Belum membentuk snapshot baru" />
                <Metric icon={<Workflow aria-hidden="true" className="size-4 text-violet-600" />} label="Dalam proses" value={summary.in_process} detail="Verifikasi hingga persetujuan" />
                <Metric icon={<BadgeCheck aria-hidden="true" className="size-4 text-emerald-600" />} label="Keputusan & final" value={summary.finalized} detail="Ditolak, PB, atau diarsipkan" />
            </dl>
        </section>

        <section aria-labelledby="lpj-filters" className="panel mt-5 p-4 sm:p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="lpj-filters" className="text-sm font-bold text-ink-950">Cari dan saring LPJ</h2>
                    <p className="mt-1 text-[11px] text-muted-600">Cari berdasarkan Activity-ID, kegiatan, pemilik, status, atau unit organisasi.</p>
                </div>
                {activeFilterCount > 0 && <span className="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[11px] font-bold text-primary-700">{activeFilterCount} filter aktif</span>}
            </div>

            {Object.keys(errors).length > 0 && <div role="alert" className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-800">
                <b>Filter belum dapat diterapkan.</b>
                <ul className="mt-1 list-disc pl-4">{Object.values(errors).map((message) => <li key={message}>{message}</li>)}</ul>
            </div>}

            <form onSubmit={applyFilters} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div className="md:col-span-2">
                    <label className="label" htmlFor="lpj-search">Cari LPJ</label>
                    <div className="relative">
                        <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input id="lpj-search" className="field pl-9" value={form.q} onChange={(event) => update('q', event.target.value)} placeholder="Activity-ID, judul kegiatan, atau pemilik" />
                    </div>
                </div>
                <div>
                    <label className="label" htmlFor="lpj-status">Status</label>
                    <select id="lpj-status" className="field" value={form.status} onChange={(event) => update('status', event.target.value)}>
                        <option value="">Semua status</option>
                        {options.statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                    </select>
                </div>
                {scope.can_view_all_units && <div>
                    <label className="label" htmlFor="lpj-unit">Unit organisasi</label>
                    <select id="lpj-unit" className="field" value={form.unit || ''} onChange={(event) => update('unit', event.target.value)}>
                        <option value="">Seluruh unit</option>
                        {options.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} · {unit.name}</option>)}
                    </select>
                </div>}
                <div>
                    <label className="label" htmlFor="lpj-sort">Urutkan</label>
                    <select id="lpj-sort" className="field" value={form.sort} onChange={(event) => update('sort', event.target.value)}>
                        <option value="latest">Pembaruan terbaru</option>
                        <option value="oldest">Pembaruan terlama</option>
                        <option value="amount_desc">Nilai terbesar</option>
                        <option value="amount_asc">Nilai terkecil</option>
                    </select>
                </div>
                <div className="flex items-end gap-2 xl:col-span-3">
                    <button type="submit" className="btn-primary" disabled={filtering}>
                        {filtering ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Filter aria-hidden="true" className="size-4" />}
                        {filtering ? 'Menerapkan…' : 'Terapkan filter'}
                    </button>
                    <Link href="/lpjs" className="btn-secondary" preserveScroll><RotateCcw aria-hidden="true" className="size-4" />Reset</Link>
                </div>
            </form>
        </section>

        <section aria-labelledby="lpj-results" className="panel mt-5 overflow-hidden">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line-200 px-5 py-4">
                <div><h2 id="lpj-results" className="text-sm font-bold text-ink-950">Daftar e-LPJ</h2><p className="mt-1 text-[11px] text-muted-600">Menampilkan {lpjs.from ?? 0}–{lpjs.to ?? 0} dari {summary.filtered} hasil.</p></div>
                <span className="text-[11px] font-semibold text-muted-600">Halaman {lpjs.current_page} dari {lpjs.last_page}</span>
            </div>
            {lpjs.data.length ? <div role="region" aria-label="Daftar e-LPJ yang dapat digulir horizontal" tabIndex={0} className="overflow-x-auto [contain:paint] focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600">
                <table className="w-full min-w-[1040px] text-left text-xs">
                    <thead className="bg-slate-50 text-[10px] font-bold text-slate-600 uppercase"><tr><th className="px-5 py-3">Activity-ID</th><th className="px-4 py-3">Kegiatan</th>{scope.can_view_all_units && <th className="px-4 py-3">Unit</th>}<th className="px-4 py-3">Pemilik</th><th className="px-4 py-3">Versi</th><th className="px-4 py-3">Nilai</th><th className="px-4 py-3">Verifikasi</th><th className="px-4 py-3">Status</th><th className="px-4 py-3"><span className="sr-only">Aksi</span></th></tr></thead>
                    <tbody>{lpjs.data.map((lpj) => {
                        const checks = lpj.current_version_record?.verifications || [];
                        const passed = checks.filter((item) => item.status === 'VERIFIED').length;
                        return <tr key={lpj.id} className="border-t border-slate-100 hover:bg-slate-50/70">
                            <td className="tabular px-5 py-4 font-bold text-primary-700">{lpj.activity.code}</td>
                            <td className="max-w-72 px-4 py-4"><b className="block truncate text-slate-800">{lpj.activity.title}</b><small className="mt-1 block text-[10px] text-muted-600">{lpj.activity.starts_at ? formatDate(lpj.activity.starts_at) : 'Periode belum tersedia'}</small></td>
                            {scope.can_view_all_units && <td className="px-4 py-4"><b className="block text-[11px]">{lpj.activity.unit?.code || '—'}</b><span className="block max-w-40 truncate text-[10px] text-muted-600">{lpj.activity.unit?.name || 'Unit tidak tersedia'}</span></td>}
                            <td className="px-4 py-4">{lpj.owner.name}</td>
                            <td className="tabular px-4 py-4">{lpj.current_version ? `v${lpj.current_version}` : 'Draf'}</td>
                            <td className="tabular px-4 py-4 font-semibold">{formatCurrency(lpj.total_amount)}</td>
                            <td className="px-4 py-4">{lpj.current_version ? <><span className="font-bold text-slate-700">{passed}/{checks.length}</span><span className="ml-1 text-[10px] text-muted-600">lulus</span></> : <span className="text-[10px] text-muted-600">Belum diajukan</span>}</td>
                            <td className="px-4 py-4"><StatusBadge status={lpj.status} /></td>
                            <td className="px-4 py-4"><Link href={`/lpjs/${lpj.id}`} aria-label={`Buka LPJ ${lpj.activity.code}`} className="grid size-11 place-items-center rounded-lg text-primary-700 hover:bg-blue-50"><ArrowRight aria-hidden="true" className="size-4" /></Link></td>
                        </tr>;
                    })}</tbody>
                </table>
            </div> : <EmptyState title={activeFilterCount ? 'LPJ tidak ditemukan' : 'Belum ada LPJ'} description={activeFilterCount ? 'Ubah atau reset filter untuk melihat LPJ lain dalam cakupan akses Anda.' : 'Draf LPJ otomatis dibuat bersama kegiatan baru.'} />}
            <Pagination links={lpjs.links} />
        </section>
    </>;
}
