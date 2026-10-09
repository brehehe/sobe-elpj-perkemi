import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { cn, formatCurrency, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Building2,
    ChevronRight,
    CircleDollarSign,
    FileStack,
    Filter,
    Landmark,
    RotateCcw,
    Search,
    ShieldCheck,
    WalletCards,
    type LucideIcon,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

type PortfolioSummary = {
    activity_count: number;
    total_lpjs: number;
    verified_lpjs: number;
    open_findings: number;
    budget: number;
    actual: number;
    variance: number;
    verified_rate: number;
};

type UnitSummary = {
    id: string;
    parent_id: string | null;
    code: string;
    name: string;
    type: string;
};

type UnitPortfolio = UnitSummary & Omit<PortfolioSummary, 'verified_rate'> & {
    is_active: boolean;
    subtree_unit_count: number;
    has_children: boolean;
};

type PortfolioRow = {
    id: string;
    status: string;
    current_version: number | null;
    updated_at: string | null;
    owner: { id: string; name: string };
    activity: {
        id: string;
        code: string;
        title: string;
        starts_at: string | null;
        ends_at: string | null;
        unit: UnitSummary;
    };
    budget: number;
    actual: number;
    variance: number;
    transactions_count: number;
    evidence_count: number;
    verification: { completed: number; total: number };
    open_findings: number;
};

type PortfolioFilters = {
    q: string;
    status: string;
    sort: string;
    unit: string | null;
};

type PortfolioProps = {
    summary: PortfolioSummary;
    scope: {
        can_view_across_units: boolean;
        selected_unit: UnitSummary | null;
        breadcrumbs: UnitSummary[];
        label: string;
    };
    units: UnitPortfolio[];
    portfolio: Paginated<PortfolioRow>;
    filters: PortfolioFilters;
    statusOptions: Array<{ value: string; label: string }>;
};

type MetricProps = {
    icon: LucideIcon;
    label: string;
    value: string;
    detail: string;
    tone?: string;
};

function Metric({ icon: Icon, label, value, detail, tone = 'text-primary-700' }: MetricProps) {
    return (
        <div className="min-w-0 px-5 py-5 sm:px-6">
            <dt className="text-[10px] font-bold tracking-[0.08em] text-muted-600 uppercase">{label}</dt>
            <dd>
                <span className="mt-2 flex items-start justify-between gap-3">
                    <span className="min-w-0 truncate text-xl font-black tracking-[-0.03em] text-ink-950 tabular sm:text-2xl">{value}</span>
                    <span className={cn('grid size-9 shrink-0 place-items-center rounded-lg bg-slate-50', tone)}>
                        <Icon aria-hidden="true" className="size-[18px]" />
                    </span>
                </span>
                <span className="mt-2 block text-[11px] leading-5 text-muted-600">{detail}</span>
            </dd>
        </div>
    );
}

function portfolioUrl(unit: string | null, filters: PortfolioFilters): string {
    const query = new URLSearchParams();

    if (unit) query.set('unit', unit);
    if (filters.q) query.set('q', filters.q);
    if (filters.status) query.set('status', filters.status);
    if (filters.sort !== 'updated_desc') query.set('sort', filters.sort);

    const suffix = query.toString();

    return suffix ? `/portfolio?${suffix}` : '/portfolio';
}

export default function PortfolioIndex({ summary, scope, units, portfolio, filters, statusOptions }: PortfolioProps) {
    const [search, setSearch] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [sort, setSort] = useState(filters.sort);
    const varianceExceeded = summary.variance < 0;

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/portfolio', {
            q: search || undefined,
            status: status || undefined,
            sort: sort === 'updated_desc' ? undefined : sort,
            unit: filters.unit || undefined,
        }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Portfolio LPJ" />
            <PageHeader
                eyebrow="Kendali nasional → unit → kegiatan"
                title={scope.selected_unit ? `Portfolio LPJ · ${scope.label}` : 'Portfolio LPJ Nasional'}
                description="Telusuri akuntabilitas dari hierarki organisasi menuju kegiatan, transaksi, evidence, status verifikasi, serta temuan pada satu ruang kendali."
                actions={<Link href="/lpjs" className="btn-secondary">Daftar e-LPJ<ArrowRight aria-hidden="true" className="size-4" /></Link>}
            />

            {scope.can_view_across_units && (
                <nav aria-label="Hierarki portfolio" className="mt-5 flex min-h-11 flex-wrap items-center gap-1.5 text-xs">
                    <Link
                        href={portfolioUrl(null, filters)}
                        aria-current={!scope.selected_unit ? 'page' : undefined}
                        className={cn('rounded-lg px-2.5 py-2 font-semibold transition hover:bg-blue-50 hover:text-primary-700', !scope.selected_unit ? 'bg-blue-50 text-primary-700' : 'text-slate-600')}
                    >
                        Nasional
                    </Link>
                    {scope.breadcrumbs.map((unit, index) => (
                        <span key={unit.id} className="flex items-center gap-1.5">
                            <ChevronRight aria-hidden="true" className="size-3.5 text-slate-400" />
                            <Link
                                href={portfolioUrl(unit.id, filters)}
                                aria-current={index === scope.breadcrumbs.length - 1 ? 'page' : undefined}
                                className={cn('rounded-lg px-2.5 py-2 font-semibold transition hover:bg-blue-50 hover:text-primary-700', index === scope.breadcrumbs.length - 1 ? 'bg-blue-50 text-primary-700' : 'text-slate-600')}
                            >
                                {unit.name}
                            </Link>
                        </span>
                    ))}
                </nav>
            )}

            <section aria-labelledby="portfolio-summary" className="panel mt-5 overflow-hidden">
                <div className="flex flex-col gap-2 border-b border-line-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 id="portfolio-summary" className="text-sm font-bold text-ink-950">Ringkasan lingkup {scope.label}</h2>
                        <p className="mt-1 text-[11px] text-muted-600">Menghitung unit terpilih beserta seluruh turunannya.</p>
                    </div>
                    <span className={cn('inline-flex w-fit items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-bold', varianceExceeded ? 'border-red-200 bg-red-50 text-danger-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700')}>
                        {varianceExceeded ? <AlertTriangle aria-hidden="true" className="size-3.5" /> : <ShieldCheck aria-hidden="true" className="size-3.5" />}
                        {varianceExceeded ? `Melebihi pagu ${formatCurrency(Math.abs(summary.variance))}` : `Sisa pagu ${formatCurrency(summary.variance)}`}
                    </span>
                </div>
                <dl className="grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-5">
                    <Metric icon={FileStack} label="Total LPJ" value={String(summary.total_lpjs)} detail={`${summary.activity_count} kegiatan dalam lingkup`} />
                    <Metric icon={ShieldCheck} label="Terverifikasi" value={`${summary.verified_rate}%`} detail={`${summary.verified_lpjs} LPJ selesai verifikasi`} tone="text-emerald-700" />
                    <Metric icon={WalletCards} label="Total pagu" value={formatCurrency(summary.budget)} detail="Pagu seluruh kegiatan" tone="text-violet-700" />
                    <Metric icon={CircleDollarSign} label="Realisasi" value={formatCurrency(summary.actual)} detail="Berdasarkan transaksi sumber" tone="text-blue-700" />
                    <Metric icon={AlertTriangle} label="Temuan terbuka" value={String(summary.open_findings)} detail="OPEN atau CAPA diajukan" tone={summary.open_findings ? 'text-danger-600' : 'text-emerald-700'} />
                </dl>
            </section>

            {units.length > 0 && (
                <section aria-labelledby="portfolio-units" className="mt-6">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 id="portfolio-units" className="text-base font-bold text-ink-950">Turunan organisasi</h2>
                            <p className="mt-1 text-xs text-muted-600">Pilih unit untuk mempersempit portfolio dan melanjutkan drill-down.</p>
                        </div>
                        <span className="text-xs font-semibold text-slate-600">{units.length} unit langsung</span>
                    </div>
                    <ul className="panel mt-3 divide-y divide-line-200 overflow-hidden">
                        {units.map((unit) => (
                            <li key={unit.id}>
                                <Link href={portfolioUrl(unit.id, filters)} className="group grid min-h-24 gap-4 px-5 py-4 transition hover:bg-blue-50/50 sm:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(100px,.45fr))_44px] sm:items-center">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-navy-950 text-white"><Building2 aria-hidden="true" className="size-[18px]" /></span>
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <b className="truncate text-sm text-ink-950">{unit.name}</b>
                                                {!unit.is_active && <StatusBadge status="INACTIVE" />}
                                            </div>
                                            <p className="mt-1 truncate text-[11px] text-muted-600">{unit.code} · {unit.type} · {unit.subtree_unit_count} unit dalam lingkup</p>
                                        </div>
                                    </div>
                                    <div><span className="text-[10px] font-bold text-muted-600 uppercase">LPJ</span><b className="mt-1 block text-sm tabular">{unit.total_lpjs}</b></div>
                                    <div><span className="text-[10px] font-bold text-muted-600 uppercase">Pagu</span><b className="mt-1 block text-xs tabular">{formatCurrency(unit.budget)}</b></div>
                                    <div><span className="text-[10px] font-bold text-muted-600 uppercase">Temuan</span><b className={cn('mt-1 block text-sm tabular', unit.open_findings ? 'text-red-700' : 'text-emerald-700')}>{unit.open_findings}</b></div>
                                    <span className="hidden size-10 place-items-center rounded-lg text-primary-700 transition group-hover:bg-white sm:grid"><ChevronRight aria-hidden="true" className="size-4" /></span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section aria-labelledby="portfolio-register" className="mt-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="portfolio-register" className="text-base font-bold text-ink-950">Register LPJ</h2>
                        <p className="mt-1 text-xs text-muted-600">{portfolio.total} LPJ sesuai filter pada lingkup {scope.label}.</p>
                    </div>
                </div>

                <form onSubmit={submitFilters} className="panel mt-3 grid gap-4 p-4 md:grid-cols-[minmax(240px,1fr)_220px_220px_auto] md:items-end">
                    <div>
                        <label className="label" htmlFor="portfolio-search">Cari LPJ atau kegiatan</label>
                        <div className="relative">
                            <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input id="portfolio-search" className="field pl-9" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Activity-ID, LPJ-ID, atau judul" />
                        </div>
                    </div>
                    <div>
                        <label className="label" htmlFor="portfolio-status">Status LPJ</label>
                        <select id="portfolio-status" className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                            <option value="">Semua status</option>
                            {statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="portfolio-sort">Urutkan</label>
                        <select id="portfolio-sort" className="field" value={sort} onChange={(event) => setSort(event.target.value)}>
                            <option value="updated_desc">Pembaruan terbaru</option>
                            <option value="budget_desc">Pagu terbesar</option>
                            <option value="actual_desc">Realisasi terbesar</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" className="btn-primary flex-1 md:flex-none"><Filter aria-hidden="true" className="size-4" />Terapkan</button>
                        {(filters.q || filters.status || filters.sort !== 'updated_desc') && (
                            <Link href={filters.unit ? `/portfolio?unit=${encodeURIComponent(filters.unit)}` : '/portfolio'} aria-label="Reset filter portfolio" className="btn-secondary px-3"><RotateCcw aria-hidden="true" className="size-4" /></Link>
                        )}
                    </div>
                </form>

                <div className="panel mt-3 overflow-hidden">
                    {portfolio.data.length > 0 ? (
                        <div className="overflow-x-auto" role="region" aria-label="Register portfolio LPJ yang dapat digulir" tabIndex={0}>
                            <table className="w-full min-w-[1180px] text-left text-xs">
                                <thead className="bg-slate-50 text-[10px] font-bold text-slate-500 uppercase">
                                    <tr>
                                        <th className="px-5 py-3">LPJ / Kegiatan</th>
                                        <th className="px-4 py-3">Unit</th>
                                        <th className="px-4 py-3 text-right">Pagu / Realisasi</th>
                                        <th className="px-4 py-3">Sumber</th>
                                        <th className="px-4 py-3">Verifikasi</th>
                                        <th className="px-4 py-3">Temuan</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3"><span className="sr-only">Aksi</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {portfolio.data.map((lpj) => {
                                        const progress = lpj.verification.total > 0 ? Math.round((lpj.verification.completed / lpj.verification.total) * 100) : 0;
                                        const overBudget = lpj.variance < 0;

                                        return (
                                            <tr key={lpj.id} className="border-t border-line-200 align-top transition hover:bg-slate-50/70">
                                                <td className="max-w-80 px-5 py-4">
                                                    <Link href={`/lpjs/${lpj.id}`} className="font-bold text-primary-700 hover:underline">{lpj.activity.code}</Link>
                                                    <b className="mt-1 block truncate text-slate-800">{lpj.activity.title}</b>
                                                    <span className="mt-1 block text-[10px] text-muted-600">v{lpj.current_version || '—'} · {lpj.owner.name} · {formatDate(lpj.updated_at, true)}</span>
                                                </td>
                                                <td className="max-w-56 px-4 py-4">
                                                    <b className="block truncate text-slate-700">{lpj.activity.unit.name}</b>
                                                    <span className="mt-1 block text-[10px] text-muted-600">{lpj.activity.unit.code} · {lpj.activity.unit.type}</span>
                                                </td>
                                                <td className="px-4 py-4 text-right tabular">
                                                    <b className="block text-slate-800">{formatCurrency(lpj.budget)}</b>
                                                    <span className="mt-1 block text-muted-600">{formatCurrency(lpj.actual)}</span>
                                                    <span className={cn('mt-1 block text-[10px] font-semibold', overBudget ? 'text-red-700' : 'text-emerald-700')}>{overBudget ? 'Lebih ' : 'Sisa '}{formatCurrency(Math.abs(lpj.variance))}</span>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className="block font-semibold text-slate-700">{lpj.transactions_count} transaksi</span>
                                                    <span className="mt-1 block text-[10px] text-muted-600">{lpj.evidence_count} evidence</span>
                                                </td>
                                                <td className="min-w-36 px-4 py-4">
                                                    <div className="flex items-center justify-between gap-2"><b className="text-slate-700">{lpj.verification.completed}/{lpj.verification.total}</b><span className="text-[10px] text-muted-600">{progress}%</span></div>
                                                    <div role="progressbar" aria-label={`Progres verifikasi ${lpj.activity.code}`} aria-valuemin={0} aria-valuemax={100} aria-valuenow={progress} className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><span className="block h-full rounded-full bg-primary-600" style={{ width: `${progress}%` }} /></div>
                                                </td>
                                                <td className="px-4 py-4">
                                                    <span className={cn('inline-flex min-h-7 items-center rounded-lg px-2.5 font-bold', lpj.open_findings ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700')}>{lpj.open_findings ? `${lpj.open_findings} terbuka` : 'Nihil'}</span>
                                                </td>
                                                <td className="px-4 py-4"><StatusBadge status={lpj.status} /></td>
                                                <td className="px-4 py-4"><Link href={`/lpjs/${lpj.id}`} aria-label={`Buka LPJ ${lpj.activity.code}`} className="grid size-10 place-items-center rounded-lg text-primary-700 hover:bg-blue-50"><ArrowRight aria-hidden="true" className="size-4" /></Link></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="Portfolio tidak ditemukan" description="Ubah filter atau pilih lingkup unit lain untuk melihat LPJ yang tersedia." />
                    )}
                    <Pagination links={portfolio.links} />
                </div>
            </section>

            <aside className="mt-6 flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-xs leading-5 text-blue-950">
                <Landmark aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-primary-700" />
                <p><b>Sumber data terkendali.</b> Nilai realisasi berasal dari transaksi sumber, sedangkan status verifikasi dan temuan dihitung dari versi LPJ aktif. Buka LPJ untuk menelusuri snapshot, decision pack, dan audit trail lengkap.</p>
            </aside>
        </>
    );
}
