import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { cn, formatCurrency, formatDate } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    Bot,
    ChartNoAxesCombined,
    Check,
    Clock3,
    Database,
    Filter,
    Gauge,
    Info,
    LoaderCircle,
    Scale,
    ShieldCheck,
    Users,
    Waypoints,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Unit = { id: string; parent_id: string | null; code: string; name: string; type: string };
type TypeOption = { value: string; label: string; count: number };
type MetricValue = number | null;

type CohortRow = {
    id: string;
    code: string;
    title: string;
    url: string;
    starts_at: string | null;
    duration_days: number;
    unit: Unit;
    lpj: { id: string | null; url: string | null; status: string | null; version: number | null; checksum: string | null };
    participants: number;
    outcome_index: number | null;
    completion_percent: number | null;
    budget: number;
    actual: number;
    budget_accuracy: number | null;
    evidence_quality: number;
    findings_count: number;
    lpj_sla_days: number | null;
    cost_per_participant: number | null;
    data_quality: number;
    formula_version: string | null;
};

type TrendPoint = {
    code: string;
    label: string;
    starts_at: string | null;
    outcome_index: number | null;
    cost_per_participant: number | null;
    cost_index: number | null;
};

type Scenario = {
    id: string;
    title: string;
    summary: string;
    target_participants: { minimum: number; maximum: number };
    duration_days: number;
    budget_reference: { minimum: number; maximum: number };
    risk: string;
    controls: string[];
    assumptions: string[];
};

type ActivityIntelligenceProps = {
    mode: 'ANALYSIS' | 'NEXT_ACTIVITY';
    scope: { can_view_across_units: boolean; selected_unit: Unit | null; label: string; unit_count: number };
    filters: { type: string; unit: string | null; period: string };
    options: { types: TypeOption[]; periods: Array<{ value: string; label: string }>; units: Unit[] };
    eligibility: {
        is_sufficient: boolean;
        comparable_count: number;
        analyzed_count: number;
        minimum_count: number;
        missing_count: number;
        data_quality: number;
        confidence: number | null;
        checks: Array<{ label: string; passed: boolean; detail: string }>;
        blocking_reasons: string[];
    };
    metrics: {
        median_participants: MetricValue;
        median_duration_days: MetricValue;
        outcome_index: MetricValue;
        budget_accuracy: MetricValue;
        evidence_quality: MetricValue;
        median_lpj_sla_days: MetricValue;
        median_cost_per_participant: MetricValue;
    };
    trend: TrendPoint[];
    cohort: CohortRow[];
    insights: Array<{ tone: 'positive' | 'warning' | 'neutral'; label: string; title: string; detail: string }>;
    scenarios: Scenario[];
    methodology: {
        rule_version: string;
        source_statuses: string[];
        period_label: string;
        applied_comparable_factors: string[];
        desired_comparable_factors: string[];
        normalization: string[];
        sample_size: number;
        causality_notice: string;
    };
    limitations: string[];
    capabilities: { can_request_ai: boolean; can_review_ai: boolean; must_select_unit_to_request: boolean };
};

function valueLabel(value: MetricValue, suffix = ''): string {
    return value === null ? '—' : `${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value)}${suffix}`;
}

function pageUrl(path: string, filters: ActivityIntelligenceProps['filters'], type = filters.type): string {
    const query = new URLSearchParams({ type, period: filters.period });
    if (filters.unit) query.set('unit', filters.unit);

    return `${path}?${query.toString()}`;
}

function Metric({ icon: Icon, label, value, detail }: { icon: LucideIcon; label: string; value: string; detail: string }) {
    return (
        <div className="min-w-0 px-4 py-4 sm:px-5">
            <dt className="flex items-center gap-2 text-[10px] font-bold tracking-[0.08em] text-muted-600 uppercase">
                <Icon aria-hidden="true" className="size-3.5 text-primary-600" />{label}
            </dt>
            <dd>
                <span className="mt-2 block text-xl font-black tracking-[-0.03em] text-ink-950 tabular sm:text-2xl">{value}</span>
                <span className="mt-1 block text-[11px] leading-5 text-muted-600">{detail}</span>
            </dd>
        </div>
    );
}

function TrendChart({ title, description, points, valueKey, suffix = '' }: {
    title: string;
    description: string;
    points: TrendPoint[];
    valueKey: 'outcome_index' | 'cost_index';
    suffix?: string;
}) {
    const values = points.map((point) => point[valueKey]).filter((value): value is number => value !== null);
    const maximum = Math.max(100, ...values);

    return (
        <section className="panel min-w-0 p-5" aria-labelledby={`chart-${valueKey}`}>
            <h3 id={`chart-${valueKey}`} className="text-sm font-bold text-ink-950">{title}</h3>
            <p className="mt-1 text-[11px] leading-5 text-muted-600">{description}</p>
            {values.length > 0 ? (
                <ol className="mt-5 flex min-h-52 items-end gap-2 border-b border-slate-300 px-1" aria-label={`${title}. Nilai tersedia pada setiap batang dan matriks di bawah.`}>
                    {points.map((point) => {
                        const value = point[valueKey];
                        const height = value === null ? 0 : Math.max(8, Math.round((value / maximum) * 100));
                        return (
                            <li key={point.code} className="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                                <span className="text-[10px] font-bold text-slate-700 tabular">{value === null ? '—' : `${value}${suffix}`}</span>
                                <span
                                    aria-hidden="true"
                                    className={cn('w-full max-w-10 rounded-t-sm border', valueKey === 'outcome_index' ? 'border-blue-600 bg-blue-500' : 'border-amber-600 bg-amber-400')}
                                    style={{ height: `${height * 1.45}px` }}
                                />
                                <span className="max-w-16 truncate pb-2 text-[9px] text-muted-600" title={`${point.code} · ${point.label}`}>{point.label}</span>
                            </li>
                        );
                    })}
                </ol>
            ) : <EmptyState title="Belum ada nilai tren" description="Nilai akan tampil setelah cohort memiliki data outcome dan transaksi yang dapat dinormalisasi." />}
        </section>
    );
}

function ScenarioSection({ scenarios, eligibility, capabilities, filters }: Pick<ActivityIntelligenceProps, 'scenarios' | 'eligibility' | 'capabilities' | 'filters'>) {
    const [requesting, setRequesting] = useState(false);
    const [requestError, setRequestError] = useState<string | null>(null);

    const requestHumanReview = () => {
        setRequestError(null);
        router.post('/activity-intelligence/recommendations', {
            type: filters.type,
            unit: filters.unit || undefined,
            period: filters.period,
        }, {
            preserveScroll: true,
            onStart: () => setRequesting(true),
            onFinish: () => setRequesting(false),
            onError: (errors) => setRequestError(String(errors.recommendation || Object.values(errors)[0] || 'Paket belum dapat dicatat.')),
        });
    };

    return (
        <section aria-labelledby="next-scenarios" className="mt-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-[10px] font-bold tracking-[0.1em] text-primary-600 uppercase">Kegiatan berikutnya</p>
                    <h2 id="next-scenarios" className="mt-1 text-lg font-bold text-ink-950">Pilihan skenario berbasis baseline terverifikasi</h2>
                    <p className="mt-1 max-w-3xl text-xs leading-5 text-muted-600">Baseline terverifikasi ini belum menjadi draft. Pengayaan AI diproses melalui antrean dan tetap wajib melalui review manusia sebelum dapat dikonversi ke e-Planning.</p>
                </div>
                {capabilities.can_request_ai && eligibility.is_sufficient
                    ? <button type="button" onClick={requestHumanReview} disabled={requesting} className="btn-primary">{requesting ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <ShieldCheck aria-hidden="true" className="size-4" />}{requesting ? 'Mengantrekan…' : 'Proses AI & review manusia'}</button>
                    : capabilities.can_review_ai && <Link href="/intelligence" className="btn-secondary">Buka Intelligence<ArrowRight aria-hidden="true" className="size-4" /></Link>}
            </div>

            {requestError && <p role="alert" className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-800">{requestError}</p>}
            {capabilities.must_select_unit_to_request && <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-950">Pilih satu unit organisasi untuk mengikat owner, otorisasi reviewer, dan lineage paket rekomendasi.</p>}

            {eligibility.is_sufficient ? (
                <div className="mt-3 grid gap-4 xl:grid-cols-3">
                    {scenarios.map((scenario) => (
                        <article key={scenario.id} className="panel flex min-w-0 flex-col overflow-hidden">
                            <div className="border-b border-line-200 bg-slate-50/80 px-5 py-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="text-sm font-bold text-ink-950">{scenario.title}</h3>
                                    <span className="rounded-md border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700">Risk {scenario.risk}</span>
                                </div>
                                <p className="mt-2 text-xs leading-5 text-muted-600">{scenario.summary}</p>
                            </div>
                            <dl className="grid grid-cols-2 border-b border-line-200">
                                <div className="border-r border-line-200 p-4"><dt className="text-[10px] font-bold text-muted-600 uppercase">Target peserta</dt><dd className="mt-1 text-base font-black tabular">{scenario.target_participants.minimum}–{scenario.target_participants.maximum}</dd></div>
                                <div className="p-4"><dt className="text-[10px] font-bold text-muted-600 uppercase">Durasi</dt><dd className="mt-1 text-base font-black tabular">{scenario.duration_days} hari</dd></div>
                            </dl>
                            <div className="flex-1 p-5">
                                <p className="text-[10px] font-bold text-muted-600 uppercase">Referensi anggaran</p>
                                <p className="mt-1 text-sm font-bold tabular">{formatCurrency(scenario.budget_reference.minimum)}–{formatCurrency(scenario.budget_reference.maximum)}</p>
                                <p className="mt-4 text-[10px] font-bold text-muted-600 uppercase">Kontrol yang disarankan</p>
                                <ul className="mt-2 grid gap-2">
                                    {scenario.controls.map((control) => <li key={control} className="flex gap-2 text-xs leading-5 text-slate-700"><Check aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-emerald-700" />{control}</li>)}
                                </ul>
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="panel mt-3 border-amber-200 bg-amber-50/60">
                    <EmptyState title="Data comparable belum cukup" description={eligibility.missing_count > 0 ? `Tambahkan ${eligibility.missing_count} LPJ terverifikasi dengan jenis yang sama. Skenario sengaja tidak dibuat sebelum batas minimum ${eligibility.minimum_count} kegiatan terpenuhi.` : `Perbaiki pemeriksaan kualitas berikut: ${eligibility.blocking_reasons.join(', ')}. Skenario tidak dibuat sebelum seluruh pemeriksaan lulus.`} />
                </div>
            )}
        </section>
    );
}

function WorkflowStrip() {
    const steps = ['Verified LPJ', 'Quality check', 'Comparable group', 'Skenario', 'Human review', 'Draft e-Planning'];
    return (
        <ol className="panel mt-5 grid overflow-hidden sm:grid-cols-3 xl:grid-cols-6" aria-label="Alur rekomendasi kegiatan berikutnya">
            {steps.map((step, index) => (
                <li key={step} className="relative flex min-h-16 items-center gap-3 border-b border-line-200 px-4 py-3 last:border-b-0 sm:border-r sm:[&:nth-child(3)]:border-r-0 xl:border-b-0 xl:[&:nth-child(3)]:border-r xl:last:border-r-0">
                    <span className="grid size-7 shrink-0 place-items-center rounded-full bg-navy-950 text-[10px] font-black text-white">{index + 1}</span>
                    <span className="text-[11px] font-bold leading-4 text-slate-700">{step}</span>
                </li>
            ))}
        </ol>
    );
}

export default function ActivityIntelligenceIndex(props: ActivityIntelligenceProps) {
    const { mode, scope, filters, options, eligibility, metrics, trend, cohort, insights, scenarios, methodology, limitations, capabilities } = props;
    const routePath = mode === 'NEXT_ACTIVITY' ? '/next-activity' : '/activity-intelligence';
    const [unit, setUnit] = useState(filters.unit ?? '');
    const [period, setPeriod] = useState(filters.period);
    const selectedType = options.types.find((type) => type.value === filters.type);
    const scenariosFirst = mode === 'NEXT_ACTIVITY';

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(routePath, { type: filters.type, unit: unit || undefined, period }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const metricCards: Array<{ icon: LucideIcon; label: string; value: string; detail: string }> = [
        { icon: Database, label: 'Kegiatan sejenis', value: String(eligibility.comparable_count), detail: `${eligibility.analyzed_count} record dianalisis` },
        { icon: Users, label: 'Median peserta', value: valueLabel(metrics.median_participants), detail: 'Peserta aktual per kegiatan' },
        { icon: Gauge, label: 'Outcome index', value: valueLabel(metrics.outcome_index), detail: 'Median evaluasi diterima, maks. 100' },
        { icon: Scale, label: 'Budget accuracy', value: valueLabel(metrics.budget_accuracy, '%'), detail: 'Rata-rata kedekatan realisasi ke pagu' },
        { icon: ShieldCheck, label: 'Evidence quality', value: valueLabel(metrics.evidence_quality, '%'), detail: 'Cakupan evidence terdaftar' },
        { icon: Clock3, label: 'Median LPJ SLA', value: valueLabel(metrics.median_lpj_sla_days, ' hari'), detail: 'Submit hingga PB verified/arsip' },
    ];

    const scenarioBlock = <ScenarioSection scenarios={scenarios} eligibility={eligibility} capabilities={capabilities} filters={filters} />;

    return (
        <>
            <Head title={mode === 'NEXT_ACTIVITY' ? 'Kegiatan Berikutnya' : 'Analisis Jenis Kegiatan'} />
            <PageHeader
                eyebrow="Verified LPJ → organizational learning"
                title={mode === 'NEXT_ACTIVITY' ? 'Analisis Kegiatan Berikutnya' : 'Analisis Kegiatan Sama & Sejenis'}
                description={mode === 'NEXT_ACTIVITY'
                    ? 'Bangun opsi perencanaan dari baseline LPJ terverifikasi, lengkap dengan asumsi, confidence, limitations, dan kendali manusia.'
                    : 'Bandingkan kegiatan dalam taxonomy yang sama menggunakan data terverifikasi, normalisasi yang transparan, dan pola yang dapat ditelusuri.'}
                actions={<Link href={mode === 'NEXT_ACTIVITY' ? pageUrl('/activity-intelligence', filters) : pageUrl('/next-activity', filters)} className="btn-primary">{mode === 'NEXT_ACTIVITY' ? <ChartNoAxesCombined aria-hidden="true" className="size-4" /> : <Waypoints aria-hidden="true" className="size-4" />}{mode === 'NEXT_ACTIVITY' ? 'Lihat analisis cohort' : 'Kegiatan berikutnya'}</Link>}
            />

            <nav aria-label="Mode analisis" className="mt-5 flex w-fit rounded-lg border border-line-200 bg-white p-1">
                <Link href={pageUrl('/activity-intelligence', filters)} aria-current={mode === 'ANALYSIS' ? 'page' : undefined} className={cn('flex min-h-10 items-center gap-2 rounded-md px-3 text-xs font-bold transition', mode === 'ANALYSIS' ? 'bg-navy-950 text-white' : 'text-slate-600 hover:bg-slate-50')}><ChartNoAxesCombined aria-hidden="true" className="size-4" />Analisis cohort</Link>
                <Link href={pageUrl('/next-activity', filters)} aria-current={mode === 'NEXT_ACTIVITY' ? 'page' : undefined} className={cn('flex min-h-10 items-center gap-2 rounded-md px-3 text-xs font-bold transition', mode === 'NEXT_ACTIVITY' ? 'bg-navy-950 text-white' : 'text-slate-600 hover:bg-slate-50')}><Waypoints aria-hidden="true" className="size-4" />Kegiatan berikutnya</Link>
            </nav>

            {mode === 'NEXT_ACTIVITY' && <WorkflowStrip />}

            <section aria-labelledby="cohort-filter" className="panel mt-5 overflow-hidden">
                <div className="border-b border-line-200 bg-slate-50/70 px-5 py-4">
                    <h2 id="cohort-filter" className="flex items-center gap-2 text-sm font-bold text-ink-950"><Filter aria-hidden="true" className="size-4 text-primary-600" />Cohort dan lingkup</h2>
                    <p className="mt-1 text-[11px] text-muted-600">Perubahan filter selalu menghitung ulang eligibility dari sumber yang memenuhi syarat.</p>
                </div>
                <div className="flex gap-2 overflow-x-auto border-b border-line-200 px-4 py-3" aria-label="Jenis kegiatan">
                    {options.types.map((type) => (
                        <Link key={type.value} href={pageUrl(routePath, filters, type.value)} aria-current={type.value === filters.type ? 'page' : undefined} className={cn('flex min-h-10 shrink-0 items-center gap-2 rounded-lg border px-3 text-xs font-bold transition', type.value === filters.type ? 'border-primary-600 bg-blue-50 text-primary-700' : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400')}>
                            {type.label}<span className="rounded bg-white px-1.5 py-0.5 text-[10px] tabular">{type.count}</span>
                        </Link>
                    ))}
                </div>
                <form onSubmit={submitFilters} className="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end">
                    <div>
                        <label className="label" htmlFor="analytics-unit">Lingkup organisasi</label>
                        {scope.can_view_across_units ? (
                            <select id="analytics-unit" className="field" value={unit} onChange={(event) => setUnit(event.target.value)}>
                                <option value="">Seluruh unit</option>
                                {options.units.map((option) => <option key={option.id} value={option.id}>{option.name} · {option.code}</option>)}
                            </select>
                        ) : <div className="field flex items-center bg-slate-50 text-slate-700">{scope.label}</div>}
                    </div>
                    <div>
                        <label className="label" htmlFor="analytics-period">Periode sumber</label>
                        <select id="analytics-period" className="field" value={period} onChange={(event) => setPeriod(event.target.value)}>
                            {options.periods.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </div>
                    <button type="submit" className="btn-secondary"><Filter aria-hidden="true" className="size-4" />Terapkan</button>
                </form>
            </section>

            <section aria-labelledby="eligibility-title" className={cn('mt-5 overflow-hidden rounded-xl border', eligibility.is_sufficient ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/60')}>
                <div className="grid gap-5 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_minmax(420px,1.2fr)] lg:items-center">
                    <div className="flex gap-3">
                        <span className={cn('grid size-11 shrink-0 place-items-center rounded-lg', eligibility.is_sufficient ? 'bg-emerald-700 text-white' : 'bg-amber-500 text-amber-950')}>
                            {eligibility.is_sufficient ? <BadgeCheck aria-hidden="true" className="size-5" /> : <AlertTriangle aria-hidden="true" className="size-5" />}
                        </span>
                        <div>
                            <h2 id="eligibility-title" className="text-sm font-bold text-ink-950">{eligibility.is_sufficient ? 'Cohort layak dianalisis' : 'Data comparable belum cukup'}</h2>
                            <p className="mt-1 text-xs leading-5 text-slate-700">{eligibility.is_sufficient ? `Confidence ${eligibility.confidence}% · kualitas data ${eligibility.data_quality}% · ${selectedType?.label}` : eligibility.missing_count > 0 ? `Masih perlu ${eligibility.missing_count} LPJ terverifikasi ${selectedType?.label ?? ''} pada scope dan periode ini.` : `Pemeriksaan yang belum lulus: ${eligibility.blocking_reasons.join(', ')}.`}</p>
                            <p className="mt-1 text-[10px] font-semibold text-slate-600">{methodology.rule_version} · {methodology.period_label}</p>
                        </div>
                    </div>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {eligibility.checks.map((check) => <li key={check.label} className="flex min-h-12 items-center gap-2 rounded-lg border border-white/80 bg-white/80 px-3 py-2"><span className={cn('grid size-5 shrink-0 place-items-center rounded-full', check.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800')}>{check.passed ? <Check aria-hidden="true" className="size-3" /> : <X aria-hidden="true" className="size-3" />}</span><span><b className="block text-[11px] text-slate-800">{check.label}</b><small className="text-[10px] text-muted-600">{check.detail}</small></span></li>)}
                    </ul>
                </div>
            </section>

            <section aria-labelledby="cohort-metrics" className="panel mt-5 overflow-hidden">
                <h2 id="cohort-metrics" className="sr-only">Ringkasan metrik cohort</h2>
                <dl className="grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-3 xl:grid-cols-6">
                    {metricCards.map((metric) => <Metric key={metric.label} {...metric} />)}
                </dl>
            </section>

            {scenariosFirst && scenarioBlock}

            <section aria-labelledby="historical-trend" className="mt-6">
                <div>
                    <h2 id="historical-trend" className="text-lg font-bold text-ink-950">Tren historis {selectedType?.label}</h2>
                    <p className="mt-1 text-xs text-muted-600">Setiap batang memiliki nilai teks; matriks pembanding di bawah menjadi sumber detail yang dapat dibaca tanpa grafik.</p>
                </div>
                <div className="mt-3 grid gap-4 xl:grid-cols-2">
                    <TrendChart title="Outcome index" description="Skor evaluasi diterima pada skala 0–100." points={trend} valueKey="outcome_index" />
                    <TrendChart title="Indeks biaya per peserta" description="100 = median cohort; nilai lebih rendah berarti biaya relatif lebih rendah." points={trend} valueKey="cost_index" />
                </div>
            </section>

            <section aria-labelledby="comparative-matrix" className="panel mt-6 overflow-hidden">
                <div className="flex flex-col gap-2 border-b border-line-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 id="comparative-matrix" className="text-sm font-bold text-ink-950">Comparative matrix · {selectedType?.label}</h2><p className="mt-1 text-[11px] text-muted-600">Nilai berasal dari activity, transaksi, evidence, evaluasi diterima, dan snapshot LPJ.</p></div>
                    <span className="text-[10px] font-bold text-slate-500">{cohort.length} dari {eligibility.comparable_count} record</span>
                </div>
                {cohort.length ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-[1040px] w-full text-left text-xs">
                            <thead className="border-b border-line-200 bg-white text-[10px] tracking-wide text-muted-600 uppercase">
                                <tr><th className="px-5 py-3">Kegiatan / sumber</th><th className="px-3 py-3">Peserta</th><th className="px-3 py-3">Outcome</th><th className="px-3 py-3">Budget</th><th className="px-3 py-3">Evidence</th><th className="px-3 py-3">Temuan</th><th className="px-3 py-3">LPJ SLA</th><th className="px-3 py-3">Kualitas</th></tr>
                            </thead>
                            <tbody className="divide-y divide-line-200">
                                {cohort.map((row) => (
                                    <tr key={row.id} className="align-top transition hover:bg-blue-50/40">
                                        <td className="px-5 py-4"><Link href={row.url} className="font-bold text-primary-700 hover:underline">{row.code}</Link><span className="mt-1 block max-w-sm text-[11px] text-slate-700">{row.title}</span><span className="mt-1 block text-[10px] text-muted-600">{row.unit.name} · {formatDate(row.starts_at)}</span>{row.lpj.url && <Link href={row.lpj.url} className="mt-1 inline-block font-mono text-[9px] text-slate-500 hover:text-primary-700">LPJ v{row.lpj.version ?? '—'} · {row.lpj.checksum?.slice(0, 10) ?? 'tanpa checksum'}</Link>}</td>
                                        <td className="px-3 py-4 font-bold tabular">{row.participants}</td>
                                        <td className="px-3 py-4 tabular">{row.outcome_index ?? '—'}</td>
                                        <td className="px-3 py-4"><b className="block tabular">{row.budget_accuracy === null ? '—' : `${row.budget_accuracy}%`}</b><span className="mt-1 block text-[10px] text-muted-600">{formatCurrency(row.actual)} / {formatCurrency(row.budget)}</span></td>
                                        <td className="px-3 py-4 tabular">{row.evidence_quality}%</td>
                                        <td className="px-3 py-4 tabular">{row.findings_count}</td>
                                        <td className="px-3 py-4 tabular">{row.lpj_sla_days === null ? '—' : `${row.lpj_sla_days} hari`}</td>
                                        <td className="px-3 py-4"><span className={cn('inline-flex rounded-md border px-2 py-1 text-[10px] font-bold tabular', row.data_quality >= 80 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800')}>{row.data_quality}%</span></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : <EmptyState title="Belum ada LPJ eligible" description="Pilih jenis, unit, atau periode lain. Hanya LPJ PB_VERIFIED dan ARCHIVED yang boleh masuk cohort." />}
            </section>

            <section aria-labelledby="pattern-analysis" className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(320px,.6fr)]">
                <div>
                    <h2 id="pattern-analysis" className="text-lg font-bold text-ink-950">Pola yang dapat ditindaklanjuti</h2>
                    <p className="mt-1 text-xs text-muted-600">Ringkasan deskriptif dari sumber terpilih, bukan kesimpulan kausal.</p>
                    {insights.length ? <div className="mt-3 grid gap-3 sm:grid-cols-2">{insights.map((insight) => <article key={`${insight.label}-${insight.title}`} className={cn('rounded-xl border p-4', insight.tone === 'positive' ? 'border-emerald-200 bg-emerald-50/60' : insight.tone === 'warning' ? 'border-amber-200 bg-amber-50/60' : 'border-line-200 bg-white')}><p className="text-[10px] font-bold tracking-wide text-muted-600 uppercase">{insight.label}</p><h3 className="mt-2 text-sm font-bold text-ink-950">{insight.title}</h3><p className="mt-2 text-xs leading-5 text-slate-600">{insight.detail}</p></article>)}</div> : <div className="panel mt-3"><EmptyState title="Pola belum terbentuk" description="Diperlukan evaluasi atau temuan yang cukup untuk merangkum pola." /></div>}
                </div>
                <aside className="panel overflow-hidden" aria-labelledby="method-title">
                    <div className="border-b border-line-200 bg-navy-950 px-5 py-4 text-white"><h2 id="method-title" className="flex items-center gap-2 text-sm font-bold"><Info aria-hidden="true" className="size-4" />Metode & batasan</h2><p className="mt-1 text-[10px] text-slate-300">{methodology.rule_version}</p></div>
                    <div className="p-5">
                        <p className="text-[10px] font-bold text-muted-600 uppercase">Faktor diterapkan</p><ul className="mt-2 grid gap-2">{methodology.applied_comparable_factors.map((item) => <li key={item} className="flex gap-2 text-xs text-slate-700"><Check aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-emerald-700" />{item}</li>)}</ul>
                        <p className="mt-4 text-[10px] font-bold text-muted-600 uppercase">Normalisasi</p><p className="mt-2 text-xs leading-5 text-slate-700">{methodology.normalization.join(' · ')}</p>
                        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] leading-5 text-amber-950">{methodology.causality_notice}</p>
                        <details className="mt-4 border-t border-line-200 pt-3"><summary className="min-h-10 cursor-pointer py-2 text-xs font-bold text-primary-700">Lihat semua limitations</summary><ul className="mt-2 grid gap-2">{limitations.map((item) => <li key={item} className="flex gap-2 text-[11px] leading-5 text-slate-600"><AlertTriangle aria-hidden="true" className="mt-0.5 size-3.5 shrink-0 text-amber-700" />{item}</li>)}</ul></details>
                    </div>
                </aside>
            </section>

            {!scenariosFirst && scenarioBlock}

            <section className="mt-6 rounded-xl border border-blue-200 bg-blue-50/60 p-5" aria-labelledby="human-control">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-600 text-white"><Bot aria-hidden="true" className="size-5" /></span><div><h2 id="human-control" className="text-sm font-bold text-ink-950">AI tidak mengesahkan LPJ atau planning</h2><p className="mt-1 max-w-3xl text-xs leading-5 text-slate-700">Gunakan Intelligence untuk output terstruktur dengan provenance. Reviewer manusia wajib menerima, memodifikasi, atau menolak sebelum output dapat dibuat sebagai draft e-Planning.</p></div></div>
                    {(capabilities.can_request_ai || capabilities.can_review_ai) && <Link href="/intelligence" className="btn-primary shrink-0">Lanjut ke review manusia<ArrowRight aria-hidden="true" className="size-4" /></Link>}
                </div>
            </section>
        </>
    );
}
