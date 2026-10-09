import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    Bot,
    BrainCircuit,
    CheckCircle2,
    CircleGauge,
    ClipboardPlus,
    FileCheck2,
    FileSearch,
    Filter,
    GitCompareArrows,
    Info,
    LoaderCircle,
    RotateCcw,
    Search,
    Send,
    ShieldCheck,
    TriangleAlert,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

type ReferenceOption = { id: string; code: string; name: string; type?: string };
type ActivityOption = {
    id: string;
    code: string;
    title: string;
    status: string;
    unit: ReferenceOption;
    lpj: { id: string; status: string; current_version: number } | null;
};
type Source = { type: string; reference: string; checksum: string | null };
type ReviewOutput = {
    summary: string | null;
    risks: string[];
    recommendations: string[];
    sources: string[];
    assumptions: string[];
    comparable_factors: string[];
    normalization: string | null;
};
type IntelligenceReview = {
    id: string;
    type: string;
    status: string;
    model: string | null;
    prompt_hash: string;
    prompt_version: string;
    response_id: string | null;
    usage: Record<string, number>;
    latency_ms: number | null;
    retry_count: number;
    confidence: number | null;
    limitations: string | null;
    human_review_status: string;
    review_notes: string | null;
    reviewed_output: ReviewOutput | null;
    created_at: string | null;
    reviewed_at: string | null;
    activity: {
        id: string;
        code: string;
        title: string;
        status: string;
        url: string;
        lpj_url: string | null;
        unit: ReferenceOption;
    };
    lpj_version: { id: string; version: number; status: string; checksum: string; submitted_at: string | null } | null;
    requester: { id: string; name: string };
    reviewer: { id: string; name: string } | null;
    sources: Source[];
    output: ReviewOutput;
    can_review: boolean;
    can_cancel: boolean;
    can_create_planning_draft: boolean;
    planning_draft: { id: string; code: string; status: string } | null;
};
type Filters = {
    q: string;
    unit: string | null;
    type: string;
    status: string;
    review_status: string;
    model: string;
    sort: string;
};
type Props = {
    summary: { total: number; pending_review: number; accepted: number; average_confidence: number | null };
    scope: { can_view_all_units: boolean; selected_unit: ReferenceOption | null; label: string };
    capabilities: { can_request: boolean; can_review: boolean };
    service: {
        mode: 'OPENAI' | 'LOCAL_RULES';
        prompt_version: string;
        core_workflow_independent: boolean;
        usage: { enabled: boolean; used_tokens: number; limit_tokens: number | null; remaining_tokens: number | null; monthly_used_tokens: number; monthly_limit_tokens: number | null; monthly_remaining_tokens: number | null; warning: boolean; blocked: boolean } | null;
    };
    reviews: Paginated<IntelligenceReview>;
    filters: Filters;
    options: { types: string[]; models: string[]; units: ReferenceOption[]; activities: ActivityOption[] };
};
type MetricProps = { icon: LucideIcon; label: string; value: string | number; detail: string; tone?: string };

function Metric({ icon: Icon, label, value, detail, tone = 'text-primary-700' }: MetricProps) {
    return (
        <div className="min-w-0 px-5 py-5 sm:px-6">
            <dt className="text-[11px] font-bold text-muted-600">{label}</dt>
            <dd>
                <span className="mt-2 flex items-start justify-between gap-3">
                    <strong className="text-2xl font-black tracking-[-0.03em] text-ink-950 tabular">{value}</strong>
                    <span className={cn('grid size-9 shrink-0 place-items-center rounded-lg bg-slate-50', tone)}><Icon aria-hidden="true" className="size-[18px]" /></span>
                </span>
                <span className="mt-2 block text-[11px] leading-5 text-muted-600">{detail}</span>
            </dd>
        </div>
    );
}

function AnalysisRequestForm({ activities }: { activities: ActivityOption[] }) {
    const form = useForm({ activity_id: activities[0]?.id || '', type: 'PRECHECK' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/ai-reviews', { preserveScroll: true });
    };

    return (
        <details className="panel mt-6 overflow-hidden">
            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 sm:px-6">
                <span className="flex min-w-0 items-center gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-blue-50 text-primary-700"><FileSearch aria-hidden="true" className="size-[18px]" /></span>
                    <span><b className="block text-sm text-ink-950">Jalankan pemeriksaan pendukung</b><span className="mt-1 block text-[11px] leading-5 text-muted-600">Output selalu berstatus advisory dan tidak mengubah workflow.</span></span>
                </span>
                <span className="text-xs font-bold text-primary-700">Buka form</span>
            </summary>
            <form onSubmit={submit} className="grid gap-4 border-t border-line-200 bg-slate-50/60 p-5 sm:grid-cols-2 sm:p-6">
                {Object.keys(form.errors).length > 0 && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800 sm:col-span-2"><b>Analisis belum dapat dijalankan.</b><p className="mt-1">{Object.values(form.errors)[0]}</p></div>}
                <div>
                    <label className="label" htmlFor="intelligence-activity">Activity-ID</label>
                    <select id="intelligence-activity" className="field" value={form.data.activity_id} onChange={(event) => form.setData('activity_id', event.target.value)} required>
                        <option value="">Pilih kegiatan</option>
                        {activities.map((activity) => <option key={activity.id} value={activity.id}>{activity.code} · {activity.title}</option>)}
                    </select>
                </div>
                <div>
                    <label className="label" htmlFor="intelligence-type">Jenis pemeriksaan</label>
                    <select id="intelligence-type" className="field" value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                        <option value="PRECHECK">Kelengkapan awal</option>
                        <option value="RISK_REVIEW">Risiko dan anomali</option>
                        <option value="NARRATIVE">Konsistensi narasi</option>
                    </select>
                </div>
                <div className="flex items-center justify-between gap-4 sm:col-span-2">
                    <p className="max-w-2xl text-[11px] leading-5 text-muted-600">Data LPJ diperlakukan sebagai sumber tidak tepercaya. Model tidak menerima kewenangan approval, transaksi, finding, atau CAPA.</p>
                    <button type="submit" className="btn-primary shrink-0" disabled={form.processing || !form.data.activity_id}><Send aria-hidden="true" className="size-4" />{form.processing ? 'Memeriksa…' : 'Jalankan'}</button>
                </div>
            </form>
        </details>
    );
}

function HumanReviewForm({ review }: { review: IntelligenceReview }) {
    const form = useForm({
        decision: 'ACCEPTED',
        review_notes: '',
        modified_summary: review.output.summary || '',
        modified_recommendations: review.output.recommendations.join('\n'),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(`/ai-reviews/${review.id}/human-review`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-5 grid gap-4 border-t border-line-200 pt-5">
            <div className="flex items-center gap-2"><ShieldCheck aria-hidden="true" className="size-4 text-primary-700" /><h4 className="text-sm font-bold text-ink-950">Keputusan reviewer manusia</h4></div>
            {Object.keys(form.errors).length > 0 && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-800"><b>Keputusan belum dapat dicatat.</b><p>{Object.values(form.errors)[0]}</p></div>}
            <div>
                <label className="label" htmlFor={`decision-${review.id}`}>Keputusan</label>
                <select id={`decision-${review.id}`} className="field" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}>
                    <option value="ACCEPTED">Terima sebagai hasil review</option>
                    <option value="MODIFIED">Ubah dan terima</option>
                    <option value="REJECTED">Tolak rekomendasi</option>
                </select>
            </div>
            {form.data.decision === 'MODIFIED' && <>
                <div>
                    <label className="label" htmlFor={`summary-${review.id}`}>Ringkasan terverifikasi</label>
                    <textarea id={`summary-${review.id}`} className="field min-h-24" value={form.data.modified_summary} onChange={(event) => form.setData('modified_summary', event.target.value)} required />
                </div>
                <div>
                    <label className="label" htmlFor={`recommendations-${review.id}`}>Rekomendasi terverifikasi</label>
                    <textarea id={`recommendations-${review.id}`} className="field min-h-28" value={form.data.modified_recommendations} onChange={(event) => form.setData('modified_recommendations', event.target.value)} required />
                    <p className="mt-1 text-[11px] text-muted-600">Gunakan satu baris untuk setiap rekomendasi.</p>
                </div>
            </>}
            <div>
                <label className="label" htmlFor={`notes-${review.id}`}>Alasan / catatan {form.data.decision === 'ACCEPTED' ? '(opsional)' : '(wajib)'}</label>
                <textarea id={`notes-${review.id}`} className="field min-h-20" value={form.data.review_notes} onChange={(event) => form.setData('review_notes', event.target.value)} required={form.data.decision !== 'ACCEPTED'} />
            </div>
            <button type="submit" className="btn-primary justify-self-start" disabled={form.processing}>
                {form.data.decision === 'REJECTED' ? <XCircle aria-hidden="true" className="size-4" /> : <CheckCircle2 aria-hidden="true" className="size-4" />}
                {form.processing ? 'Mencatat…' : 'Catat keputusan'}
            </button>
        </form>
    );
}

function PlanningDraftAction({ review }: { review: IntelligenceReview }) {
    const form = useForm({});

    if (review.planning_draft) {
        return (
            <div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div><b className="text-xs text-blue-950">Draft planning {review.planning_draft.code}</b><p className="mt-1 text-[11px] leading-5 text-blue-900">Lineage rekomendasi dan sumber LPJ tersimpan pada draft.</p></div>
                <Link href={`/planning?focus=${review.planning_draft.id}`} className="btn-secondary bg-white"><ClipboardPlus aria-hidden="true" className="size-4" />Buka draft</Link>
            </div>
        );
    }

    if (!review.can_create_planning_draft) {
        return null;
    }

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.post(`/ai-reviews/${review.id}/planning-draft`, { preserveScroll: true }); }} className="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><b className="text-xs text-blue-950">Gunakan hasil review sebagai titik awal</b><p className="mt-1 max-w-2xl text-[11px] leading-5 text-blue-900">Sistem membuat draft e-Planning yang masih harus dilengkapi, diajukan, dan disetujui melalui alur formal.</p></div>
                <button type="submit" className="btn-primary shrink-0" disabled={form.processing}>{form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <ClipboardPlus aria-hidden="true" className="size-4" />}{form.processing ? 'Membuat…' : 'Buat draft planning'}</button>
            </div>
            {Object.keys(form.errors).length > 0 && <p role="alert" className="mt-3 text-xs font-semibold text-red-700">{String(Object.values(form.errors)[0])}</p>}
        </form>
    );
}

function CancelAiReviewButton({ review }: { review: IntelligenceReview }) {
    const form = useForm({});

    return (
        <form onSubmit={(event) => { event.preventDefault(); form.patch(`/ai-reviews/${review.id}/cancel`, { preserveScroll: true }); }}>
            <button type="submit" className="btn-secondary border-red-200 text-red-700 hover:bg-red-50" disabled={form.processing}>
                <XCircle aria-hidden="true" className="size-4" />
                {form.processing ? 'Membatalkan…' : 'Batalkan pemeriksaan'}
            </button>
        </form>
    );
}

function IntelligenceCard({ review }: { review: IntelligenceReview }) {
    const output = review.human_review_status === 'MODIFIED' && review.reviewed_output ? review.reviewed_output : review.output;
    const totalTokens = Number(review.usage.total_tokens || Number(review.usage.input_tokens || 0) + Number(review.usage.output_tokens || 0));
    const isPending = review.status === 'QUEUED' || review.status === 'RUNNING';
    const isRuleBased = review.model?.startsWith('local-rules') ?? false;

    return (
        <article className="panel overflow-hidden">
            <div className="flex flex-col gap-4 border-b border-line-200 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 text-[11px] font-bold text-primary-700">{isRuleBased ? <GitCompareArrows aria-hidden="true" className="size-4" /> : <Bot aria-hidden="true" className="size-4" />}{isRuleBased ? 'Rule-based' : 'AI-generated'} · {review.type.replaceAll('_', ' ')}</span>
                        <StatusBadge status={review.status} />
                        <StatusBadge status={review.human_review_status} />
                    </div>
                    <h3 className="mt-2 text-base font-bold text-ink-950"><Link href={review.activity.url} className="hover:text-primary-700 hover:underline">{review.activity.code} · {review.activity.title}</Link></h3>
                    <p className="mt-1 text-[11px] text-muted-600">Diminta {review.requester.name} · {formatDate(review.created_at, true)} · {review.activity.unit.name}</p>
                </div>
                <span className="inline-flex min-h-8 shrink-0 items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 text-[11px] font-bold text-slate-700"><CircleGauge aria-hidden="true" className="size-4 text-primary-700" />Confidence {review.confidence === null ? '—' : `${review.confidence}%`}</span>
            </div>

            <div className="grid xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="min-w-0 p-5 sm:p-6">
                    {isPending && <div role="status" className="mb-5 flex flex-col gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-950 sm:flex-row sm:items-start sm:justify-between"><div className="flex items-start gap-3"><LoaderCircle aria-hidden="true" className="mt-0.5 size-4 shrink-0 animate-spin" /><div><b className="text-xs">{review.status === 'QUEUED' ? 'Pemeriksaan menunggu antrean' : 'Pemeriksaan sedang berjalan'}</b><p className="mt-1 text-[11px] leading-5">Halaman akan diperbarui otomatis melalui kanal realtime setelah hasil tersedia. Workflow utama tetap dapat digunakan.</p></div></div>{review.can_cancel && <CancelAiReviewButton review={review} />}</div>}
                    <div className="rounded-lg border border-blue-100 bg-blue-50/70 p-4">
                        <span className="text-[10px] font-bold text-blue-800">Ringkasan analisis</span>
                        <p className="mt-2 text-sm leading-6 text-blue-950">{output.summary || 'Analisis belum menghasilkan ringkasan.'}</p>
                    </div>

                    <div className="mt-5 grid gap-5 lg:grid-cols-2">
                        <section aria-label="Risiko terdeteksi">
                            <h4 className="flex items-center gap-2 text-xs font-bold text-ink-950"><TriangleAlert aria-hidden="true" className="size-4 text-amber-700" />Risiko yang perlu divalidasi</h4>
                            {output.risks.length > 0 ? <ul className="mt-3 grid gap-2">{output.risks.map((risk) => <li key={risk} className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">{risk}</li>)}</ul> : <p className="mt-3 text-xs leading-5 text-muted-600">Tidak ada sinyal risiko pada output ini.</p>}
                        </section>
                        <section aria-label="Rekomendasi">
                            <h4 className="flex items-center gap-2 text-xs font-bold text-ink-950"><FileCheck2 aria-hidden="true" className="size-4 text-emerald-700" />Rekomendasi advisory</h4>
                            {output.recommendations.length > 0 ? <ol className="mt-3 grid gap-2">{output.recommendations.map((recommendation, index) => <li key={recommendation} className="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs leading-5 text-emerald-950"><b>{index + 1}.</b><span>{recommendation}</span></li>)}</ol> : <p className="mt-3 text-xs leading-5 text-muted-600">Belum ada rekomendasi yang dapat ditinjau.</p>}
                        </section>
                    </div>

                    {(output.assumptions.length > 0 || output.comparable_factors.length > 0 || output.normalization) && <details className="mt-5 rounded-lg border border-line-200 bg-slate-50/60 p-4">
                        <summary className="flex min-h-7 cursor-pointer list-none items-center gap-2 text-xs font-bold text-primary-700"><GitCompareArrows aria-hidden="true" className="size-4" />Asumsi dan basis perbandingan</summary>
                        <div className="mt-3 grid gap-4 text-xs leading-5 text-slate-700 sm:grid-cols-2">
                            <div><b className="text-[10px] text-muted-600">Asumsi</b><ul className="mt-1 list-disc pl-4">{output.assumptions.map((item) => <li key={item}>{item}</li>)}</ul></div>
                            <div><b className="text-[10px] text-muted-600">Comparable factors</b><p className="mt-1">{output.comparable_factors.join(', ') || 'Tidak dicatat'}</p><b className="mt-3 block text-[10px] text-muted-600">Normalisasi</b><p className="mt-1">{output.normalization || 'Tidak dicatat'}</p></div>
                        </div>
                    </details>}

                    {review.review_notes && <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs leading-5 text-emerald-950"><b>Catatan reviewer · {review.reviewer?.name}</b><p className="mt-1">{review.review_notes}</p><span className="mt-2 block text-[10px] text-emerald-800">{formatDate(review.reviewed_at, true)}</span></div>}
                    {review.can_review && <HumanReviewForm review={review} />}
                    <PlanningDraftAction review={review} />
                </div>

                <aside aria-label="Provenance analisis" className="border-t border-line-200 bg-slate-50/70 p-5 sm:p-6 xl:border-t-0 xl:border-l">
                    <div className="flex items-center gap-2"><BrainCircuit aria-hidden="true" className="size-4 text-primary-700" /><h4 className="text-xs font-bold text-ink-950">Trust & provenance</h4></div>
                    <dl className="mt-4 grid gap-4 text-xs">
                        <div><dt className="text-[10px] font-bold text-muted-600">Model / rule engine</dt><dd className="mt-1 break-words font-semibold text-slate-800">{review.model || 'Belum tersedia'}</dd></div>
                        <div><dt className="text-[10px] font-bold text-muted-600">Prompt policy</dt><dd className="mt-1 font-semibold text-slate-800">{review.prompt_version}</dd><dd className="mt-1 truncate font-mono text-[10px] text-muted-600" title={review.prompt_hash}>{review.prompt_hash}</dd></div>
                        <div><dt className="text-[10px] font-bold text-muted-600">LPJ source</dt><dd className="mt-1 font-semibold text-slate-800">{review.lpj_version ? `Versi ${review.lpj_version.version} · ${review.lpj_version.status}` : 'Belum terikat snapshot LPJ'}</dd>{review.activity.lpj_url && <dd className="mt-1"><Link href={review.activity.lpj_url} className="font-bold text-primary-700 hover:underline">Buka LPJ sumber</Link></dd>}</div>
                        <div><dt className="text-[10px] font-bold text-muted-600">Run telemetry</dt><dd className="mt-1 font-semibold text-slate-800">{review.latency_ms === null ? '—' : `${review.latency_ms} ms`} · retry {review.retry_count}</dd><dd className="mt-1 text-[10px] text-muted-600">{totalTokens > 0 ? `${totalTokens} token` : review.usage.input_records ? `${review.usage.input_records} record sumber · tanpa token` : 'Usage tidak tersedia'}{review.response_id ? ` · ${review.response_id}` : ''}</dd></div>
                    </dl>

                    <div className="mt-5 border-t border-line-200 pt-4">
                        <h5 className="text-[10px] font-bold text-muted-600">Sumber internal</h5>
                        <ul className="mt-2 grid gap-2">{review.sources.map((source) => <li key={`${source.type}-${source.reference}`} className="rounded-lg border border-slate-200 bg-white p-3"><b className="block text-[10px] text-slate-800">{source.type}</b><span className="mt-1 block break-words text-[10px] text-muted-600">{source.reference}</span>{source.checksum && <span className="mt-1 block truncate font-mono text-[9px] text-slate-500" title={source.checksum}>{source.checksum}</span>}</li>)}</ul>
                    </div>

                    <div className="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3">
                        <div className="flex gap-2"><Info aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-amber-800" /><div><b className="text-[10px] text-amber-950">Batasan</b><p className="mt-1 text-[11px] leading-5 text-amber-950">{review.limitations || 'Output wajib ditinjau manusia dan tidak boleh menjadi keputusan workflow.'}</p></div></div>
                    </div>
                </aside>
            </div>
        </article>
    );
}

export default function IntelligenceIndex({ summary, scope, capabilities, service, reviews, filters, options }: Props) {
    const [form, setForm] = useState<Filters>(filters);
    const hasPendingReview = reviews.data.some((review) => review.status === 'QUEUED' || review.status === 'RUNNING');
    const activeFilterCount = Object.entries(filters).filter(([key, value]) => value && !(key === 'sort' && value === 'latest')).length;
    const update = (key: keyof Filters, value: string) => setForm((current) => ({ ...current, [key]: value }));
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/intelligence', Object.fromEntries(Object.entries(form).filter(([, value]) => value)), { preserveScroll: true, preserveState: true, replace: true });
    };

    useEffect(() => {
        if (!hasPendingReview) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ['summary', 'reviews'] });
        }, 3_000);

        return () => window.clearInterval(interval);
    }, [hasPendingReview]);

    return (
        <>
            <Head title="LPJ Intelligence" />
            <PageHeader
                eyebrow="Human-in-the-loop"
                title="LPJ Intelligence Center"
                description="Tinjau analisis terstruktur, sumber, confidence, asumsi, dan batasannya sebelum rekomendasi boleh digunakan manusia. AI tidak memiliki kewenangan workflow."
                actions={<span className={cn('inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 text-xs font-bold', service.mode === 'OPENAI' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-blue-200 bg-blue-50 text-blue-800')}><span className={cn('size-2 rounded-full', service.mode === 'OPENAI' ? 'bg-emerald-600' : 'bg-primary-600')} />{service.mode === 'OPENAI' ? 'OpenAI terkonfigurasi' : 'Fallback rule engine aktif'}</span>}
            />

            {service.usage?.enabled && <aside className={cn('mt-5 flex flex-col gap-3 rounded-xl border p-4 text-xs sm:flex-row sm:items-center sm:justify-between', service.usage.blocked ? 'border-red-200 bg-red-50 text-red-900' : service.usage.warning ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-slate-200 bg-slate-50 text-slate-700')}><div><b>Guard penggunaan OpenAI harian & bulanan</b><p className="mt-1 text-[11px] leading-5">Hari ini {service.usage.used_tokens.toLocaleString('id-ID')} / {service.usage.limit_tokens?.toLocaleString('id-ID') ?? 'tanpa batas'} token · bulan ini {service.usage.monthly_used_tokens.toLocaleString('id-ID')} / {service.usage.monthly_limit_tokens?.toLocaleString('id-ID') ?? 'tanpa batas'} token.</p></div><span className="font-bold tabular">Sisa harian {service.usage.remaining_tokens?.toLocaleString('id-ID') ?? '∞'} · bulanan {service.usage.monthly_remaining_tokens?.toLocaleString('id-ID') ?? '∞'}</span></aside>}

            {capabilities.can_request && <AnalysisRequestForm activities={options.activities} />}

            <section aria-labelledby="intelligence-summary" className="panel mt-6 overflow-hidden">
                <div className="border-b border-line-200 bg-slate-50/70 px-5 py-4 sm:px-6"><h2 id="intelligence-summary" className="text-sm font-bold text-ink-950">Ringkasan {scope.label}</h2><p className="mt-1 text-[11px] text-muted-600">Metrik mengikuti filter aktif · prompt policy {service.prompt_version}.</p></div>
                <dl className="grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                    <Metric icon={BrainCircuit} label="Run ditemukan" value={summary.total} detail="Analisis sesuai scope dan filter" />
                    <Metric icon={Users} label="Menunggu manusia" value={summary.pending_review} detail="Tidak boleh dipakai sebelum direview" tone="text-amber-700" />
                    <Metric icon={ShieldCheck} label="Diterima / diubah" value={summary.accepted} detail="Keputusan reviewer telah tercatat" tone="text-emerald-700" />
                    <Metric icon={CircleGauge} label="Rata-rata confidence" value={summary.average_confidence === null ? '—' : `${summary.average_confidence}%`} detail="Indikator kualitas, bukan approval" tone="text-primary-700" />
                </dl>
            </section>

            <section aria-labelledby="intelligence-filters" className="panel mt-5 p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 id="intelligence-filters" className="text-sm font-bold text-ink-950">Filter review board</h2><p className="mt-1 text-[11px] text-muted-600">Telusuri Activity-ID, requester, model, dan status review.</p></div>{activeFilterCount > 0 && <span className="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[11px] font-bold text-primary-700">{activeFilterCount} filter aktif</span>}</div>
                <form onSubmit={applyFilters} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="md:col-span-2"><label className="label" htmlFor="intelligence-search">Cari analisis</label><div className="relative"><Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" /><input id="intelligence-search" className="field pl-9" value={form.q} onChange={(event) => update('q', event.target.value)} placeholder="Activity-ID, judul, requester, model, atau prompt hash" /></div></div>
                    {scope.can_view_all_units && <div><label className="label" htmlFor="intelligence-unit">Unit organisasi</label><select id="intelligence-unit" className="field" value={form.unit || ''} onChange={(event) => update('unit', event.target.value)}><option value="">Seluruh unit</option>{options.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} · {unit.name}</option>)}</select></div>}
                    <div><label className="label" htmlFor="intelligence-type-filter">Jenis analisis</label><select id="intelligence-type-filter" className="field" value={form.type} onChange={(event) => update('type', event.target.value)}><option value="">Semua jenis</option>{options.types.map((type) => <option key={type} value={type}>{type.replaceAll('_', ' ')}</option>)}</select></div>
                    <div><label className="label" htmlFor="intelligence-status">Status proses</label><select id="intelligence-status" className="field" value={form.status} onChange={(event) => update('status', event.target.value)}><option value="">Semua status</option><option value="QUEUED">Mengantre</option><option value="RUNNING">Berjalan</option><option value="REVIEW_REQUIRED">Perlu review</option><option value="SUCCEEDED">Berhasil</option><option value="FAILED">Gagal</option><option value="CANCELLED">Dibatalkan</option></select></div>
                    <div><label className="label" htmlFor="intelligence-review-status">Review manusia</label><select id="intelligence-review-status" className="field" value={form.review_status} onChange={(event) => update('review_status', event.target.value)}><option value="">Semua keputusan</option><option value="PENDING">Menunggu</option><option value="ACCEPTED">Diterima</option><option value="MODIFIED">Diubah</option><option value="REJECTED">Ditolak</option></select></div>
                    <div><label className="label" htmlFor="intelligence-model">Model / engine</label><select id="intelligence-model" className="field" value={form.model} onChange={(event) => update('model', event.target.value)}><option value="">Semua model</option>{options.models.map((model) => <option key={model} value={model}>{model}</option>)}</select></div>
                    <div><label className="label" htmlFor="intelligence-sort">Urutan</label><select id="intelligence-sort" className="field" value={form.sort} onChange={(event) => update('sort', event.target.value)}><option value="latest">Terbaru</option><option value="oldest">Terlama</option></select></div>
                    <div className="flex items-end gap-2 xl:col-span-4 xl:justify-end">{activeFilterCount > 0 && <Link href="/intelligence" className="btn-secondary"><RotateCcw aria-hidden="true" className="size-4" />Reset</Link>}<button type="submit" className="btn-primary"><Filter aria-hidden="true" className="size-4" />Terapkan filter</button></div>
                </form>
            </section>

            <section aria-labelledby="review-queue" className="mt-6">
                <div className="flex flex-wrap items-end justify-between gap-3"><div><h2 id="review-queue" className="text-base font-bold text-ink-950">Recommendation review board</h2><p className="mt-1 text-xs text-muted-600">{reviews.total} run · sumber dan batasan selalu ditampilkan bersama output.</p></div><span className="inline-flex items-center gap-2 text-[11px] font-semibold text-slate-600"><Activity aria-hidden="true" className="size-4 text-primary-700" />Core workflow tetap independen</span></div>
                {reviews.data.length > 0 ? <div className="mt-4 grid gap-5">{reviews.data.map((review) => <IntelligenceCard key={review.id} review={review} />)}</div> : <div className="panel mt-4"><EmptyState title="Belum ada analisis" description="Jalankan pemeriksaan pendukung atau ubah filter untuk melihat run lain." /></div>}
                <div className="panel mt-4 overflow-hidden"><Pagination links={reviews.links} /></div>
            </section>
        </>
    );
}
