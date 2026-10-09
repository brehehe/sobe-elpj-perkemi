import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowDownUp,
    CheckCircle2,
    Clock3,
    DatabaseZap,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
    Timer,
    UserRoundCog,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Summary = {
    total_runs: number;
    succeeded_runs: number;
    failed_runs: number;
    backlog: number;
    dead_letter: number;
};

type Provider = {
    key: string;
    name: string;
    description: string;
    health: string;
    observed: boolean;
    last_successful_at: string | null;
    last_attempted_at: string | null;
    average_duration_ms: number | null;
    backlog: number;
    records_succeeded: number;
    records_failed: number;
    error_category: string | null;
    retry_status: string;
    contract_version: string;
    owner: { id: string; name: string } | null;
    latest_status: string | null;
    dead_letter_count: number;
};

type IntegrationRun = {
    id: string;
    provider: string;
    idempotency_key: string;
    status: string;
    retry_status: string;
    attempts: number;
    records_received: number;
    records_succeeded: number;
    records_failed: number;
    duration_ms: number | null;
    error_category: string | null;
    last_error: string | null;
    contract_version: string;
    correlation_id: string | null;
    last_attempted_at: string | null;
    completed_at: string | null;
    next_retry_at: string | null;
    owner: { id: string; name: string } | null;
    triggered_by: { id: string; name: string } | null;
    unit: { id: string; code: string; name: string } | null;
    can_retry: boolean;
};

type FilterOption = { value: string; label: string };

const healthLabels: Record<string, string> = {
    OPERATIONAL: 'Operasional',
    DEGRADED: 'Terdegradasi',
    SYNCING: 'Sedang sinkron',
    STALE: 'Kadaluwarsa',
    NOT_OBSERVED: 'Belum teramati',
};

function duration(value: number | null): string {
    if (value === null) return '—';
    if (value < 1000) return `${value} ms`;
    return `${(value / 1000).toFixed(1)} dtk`;
}

function MetricCard({ icon: Icon, label, value, detail, danger = false }: { icon: typeof Activity; label: string; value: number; detail: string; danger?: boolean }) {
    return <article className="panel flex min-h-28 items-start gap-4 p-4 sm:p-5">
        <span className={`grid size-10 shrink-0 place-items-center rounded-xl ${danger ? 'bg-red-50 text-danger-600' : 'bg-blue-50 text-primary-700'}`}><Icon aria-hidden="true" className="size-5" /></span>
        <div><p className="text-xs font-semibold text-muted-600">{label}</p><strong className="mt-1 block text-2xl tabular-nums text-ink-950">{value}</strong><p className="mt-1 text-[11px] leading-4 text-muted-600">{detail}</p></div>
    </article>;
}

function ProviderCard({ provider }: { provider: Provider }) {
    return <article className="panel overflow-hidden">
        <div className="flex items-start justify-between gap-3 border-b border-line-200 p-4 sm:p-5">
            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="text-sm font-bold text-ink-950">{provider.name}</h3><StatusBadge status={provider.health} /></div><p className="mt-2 text-xs leading-5 text-muted-600">{provider.description}</p></div>
            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><DatabaseZap aria-hidden="true" className="size-4" /></span>
        </div>
        <dl className="grid grid-cols-2 gap-x-4 gap-y-4 p-4 text-xs sm:p-5">
            <Datum label="Sinkron sukses terakhir" value={formatDate(provider.last_successful_at, true)} />
            <Datum label="Percobaan terakhir" value={formatDate(provider.last_attempted_at, true)} />
            <Datum label="Latensi rata-rata" value={duration(provider.average_duration_ms)} />
            <Datum label="Backlog" value={`${provider.backlog} run`} />
            <Datum label="Record sukses / gagal" value={`${provider.records_succeeded} / ${provider.records_failed}`} />
            <Datum label="Kategori error" value={provider.error_category?.replaceAll('_', ' ') || '—'} />
            <Datum label="Status retry" value={provider.observed ? provider.retry_status.replaceAll('_', ' ') : '—'} />
            <Datum label="Versi kontrak" value={provider.contract_version} mono />
        </dl>
        <div className="mx-4 mb-4 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-xs sm:mx-5 sm:mb-5"><UserRoundCog aria-hidden="true" className="size-4 text-slate-500" /><div><p className="text-[10px] font-semibold text-muted-600">Owner integrasi</p><p className="mt-0.5 font-bold text-slate-800">{provider.owner?.name || 'Belum ditetapkan'}</p></div></div>
    </article>;
}

function Datum({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return <div className="min-w-0"><dt className="text-[10px] font-semibold text-muted-600">{label}</dt><dd className={`mt-1 break-words font-bold text-slate-800 ${mono ? 'font-mono text-[11px]' : ''}`}>{value}</dd></div>;
}

export default function IntegrationHub({ summary, providers, runs, filters, filterOptions }: {
    summary: Summary;
    providers: Provider[];
    runs: Paginated<IntegrationRun>;
    filters: { provider: string; status: string };
    filterOptions: { providers: FilterOption[]; statuses: FilterOption[] };
}) {
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const [provider, setProvider] = useState(filters.provider);
    const [status, setStatus] = useState(filters.status);
    const [refreshing, setRefreshing] = useState(false);
    const [retrying, setRetrying] = useState<string | null>(null);

    const filter = (event: FormEvent) => {
        event.preventDefault();
        router.get('/integrations', { provider: provider || undefined, status: status || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const refresh = () => {
        setRefreshing(true);
        router.reload({ only: ['summary', 'providers', 'runs'], onFinish: () => setRefreshing(false) });
    };

    const retry = (run: IntegrationRun) => {
        setRetrying(run.id);
        router.post(`/integration-runs/${run.id}/retries`, {}, { preserveScroll: true, onFinish: () => setRetrying(null) });
    };

    return <>
        <Head title="Integration Hub" />
        <PageHeader
            eyebrow="Monitoring dan pemulihan"
            title="Integration Hub"
            description="Pantau kesehatan sinkronisasi berdasarkan run aktual. Label operasional hanya muncul dari sinyal proses nyata—bukan status koneksi statis."
            actions={<button type="button" className="btn-secondary" onClick={refresh} disabled={refreshing}><RefreshCw aria-hidden="true" className={`size-4 ${refreshing ? 'animate-spin' : ''}`} />{refreshing ? 'Memuat…' : 'Muat ulang'}</button>}
        />

        <p className="sr-only" aria-live="polite">{refreshing ? 'Data integrasi sedang dimuat ulang.' : 'Data integrasi siap.'}</p>
        {errors?.retry && <div role="alert" className="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{errors.retry}</div>}

        <section aria-label="Ringkasan run integrasi" className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <MetricCard icon={ArrowDownUp} label="Total run" value={summary.total_runs} detail="Seluruh percobaan dalam scope" />
            <MetricCard icon={CheckCircle2} label="Berhasil" value={summary.succeeded_runs} detail="Run selesai tanpa error" />
            <MetricCard icon={AlertTriangle} label="Gagal" value={summary.failed_runs} detail="Failed, partial, atau dead-letter" danger={summary.failed_runs > 0} />
            <MetricCard icon={Clock3} label="Backlog" value={summary.backlog} detail="Menunggu, berjalan, atau retry" danger={summary.backlog > 0} />
            <MetricCard icon={ShieldAlert} label="Dead-letter" value={summary.dead_letter} detail="Butuh intervensi owner" danger={summary.dead_letter > 0} />
        </section>

        <section className="mt-7" aria-labelledby="provider-health-title">
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h2 id="provider-health-title" className="text-base font-bold text-ink-950">Kesehatan provider</h2><p className="mt-1 text-xs text-muted-600">Sistem dari prototype tetap tercatat sebagai katalog, namun tidak dianggap terhubung sebelum ada sinyal aktual.</p></div><span className="text-[11px] font-semibold text-muted-600">Ambang stale: 24 jam</span></div>
            <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-4">{providers.map((item) => <ProviderCard key={item.key} provider={item} />)}</div>
        </section>

        <section className="panel mt-7 overflow-hidden" aria-labelledby="run-history-title">
            <div className="border-b border-line-200 p-4 sm:p-5">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div><h2 id="run-history-title" className="text-base font-bold text-ink-950">Riwayat run</h2><p className="mt-1 text-xs text-muted-600">Jejak idempotency, durasi, kategori kegagalan, dan status retry per sinkronisasi.</p></div>
                    <form onSubmit={filter} className="grid gap-2 sm:grid-cols-[minmax(180px,1fr)_minmax(180px,1fr)_auto]">
                        <label><span className="sr-only">Filter provider</span><select className="field min-h-10 py-2 text-xs" value={provider} onChange={(event) => setProvider(event.target.value)}><option value="">Semua provider</option>{filterOptions.providers.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
                        <label><span className="sr-only">Filter status</span><select className="field min-h-10 py-2 text-xs" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Semua status</option>{filterOptions.statuses.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>
                        <button className="btn-secondary min-h-10 px-4 py-2 text-xs" type="submit">Terapkan</button>
                    </form>
                </div>
            </div>

            {runs.data.length > 0 ? <>
                <div className="overflow-x-auto" role="region" aria-label="Riwayat run integrasi yang dapat digulir" tabIndex={0}>
                    <table className="w-full min-w-[1180px] text-left text-xs">
                        <thead className="bg-slate-50 text-slate-600"><tr><th className="px-4 py-3">Provider / run</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Record</th><th className="px-4 py-3">Durasi</th><th className="px-4 py-3">Error / retry</th><th className="px-4 py-3">Owner / waktu</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody>{runs.data.map((run) => <tr key={run.id} className="border-t border-line-200 align-top hover:bg-slate-50/60">
                            <td className="px-4 py-4"><b className="block text-sm text-slate-800">{run.provider}</b><span className="mt-1 block max-w-64 break-all font-mono text-[10px] text-muted-600">{run.idempotency_key}</span><span className="mt-1 block text-[10px] text-muted-600">Kontrak {run.contract_version}</span></td>
                            <td className="px-4 py-4"><StatusBadge status={run.status} /><span className="mt-2 block text-[10px] text-muted-600">{run.attempts} percobaan</span></td>
                            <td className="px-4 py-4 tabular-nums"><b className="text-emerald-700">{run.records_succeeded}</b><span className="text-muted-600"> sukses</span><br /><b className="text-red-700">{run.records_failed}</b><span className="text-muted-600"> gagal</span></td>
                            <td className="px-4 py-4"><span className="inline-flex items-center gap-1.5 font-semibold"><Timer aria-hidden="true" className="size-3.5 text-slate-400" />{duration(run.duration_ms)}</span></td>
                            <td className="px-4 py-4"><div className="flex flex-wrap gap-1.5"><StatusBadge status={run.retry_status} />{run.error_category && <StatusBadge status={run.error_category} />}</div>{run.next_retry_at && <span className="mt-2 block text-[10px] text-muted-600">Retry {formatDate(run.next_retry_at, true)}</span>}{run.last_error && <span title={run.last_error} className="mt-1 block max-w-60 truncate text-[10px] text-red-700">{run.last_error}</span>}</td>
                            <td className="px-4 py-4"><b className="block text-slate-800">{run.owner?.name || 'Belum ditetapkan'}</b><span className="mt-1 block text-[10px] text-muted-600">{run.unit?.code || 'Lintas unit'} · {formatDate(run.last_attempted_at, true)}</span></td>
                            <td className="px-4 py-4 text-right">{run.can_retry ? <button type="button" className="btn-secondary ml-auto min-h-9 px-3 py-1 text-xs" onClick={() => retry(run)} disabled={retrying === run.id}><RotateCcw aria-hidden="true" className={`size-3.5 ${retrying === run.id ? 'animate-spin' : ''}`} />{retrying === run.id ? 'Mengantre…' : 'Retry'}</button> : <span className="text-[10px] text-muted-600">Tidak ada aksi</span>}</td>
                        </tr>)}</tbody>
                    </table>
                </div>
                <Pagination links={runs.links} />
            </> : <EmptyState title="Belum ada run pada filter ini" description="Ubah filter atau tunggu source system mengirim sinkronisasi pertama. Provider tidak akan ditandai operasional tanpa run aktual." />}
        </section>
    </>;
}
