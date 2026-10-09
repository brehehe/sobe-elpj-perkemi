import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, BarChart3, CheckCircle2, ClipboardCheck, FileCheck2, Send, ShieldCheck } from 'lucide-react';
import { useRef, type FormEvent } from 'react';

export type EvaluationEvidence = {
    id: string;
    supersedes_id?: string;
    title: string;
    version: number;
    scan_status: string;
    review_status: string;
};

type PlanKpi = {
    name: string;
    definition?: string;
    unit: string;
    baseline?: number | string;
    target: number | string;
    formula?: string;
    weight?: number | string;
    period_start?: string;
    period_end?: string;
};

type KpiDefinition = {
    id: string;
    name: string;
    definition: string;
    unit: string;
    baseline?: string;
    target: string;
    period_start: string;
    period_end: string;
    formula: string;
    formula_version: string;
    weight: string;
    result?: {
        id: string;
        actual: string;
        achievement_percent: string;
        notes?: string;
        evidence: { id: string; title: string; version: number };
    };
};

export type ActivityEvaluation = {
    id: string;
    version: number;
    status: string;
    summary: string;
    outcomes?: string;
    completion_percent?: string;
    satisfaction_score?: string;
    lessons_learned?: string;
    successful_practices?: string;
    improvement_areas?: string;
    score?: string;
    metrics?: { actual_participants?: number; target_participants?: number; kpi_count?: number };
    recommendations?: string[];
    formula_version?: string;
    review_notes?: string;
    reviewed_at?: string;
    accepted_at?: string;
    created_at: string;
    creator: { id: string; name: string };
    reviewer?: { id: string; name: string };
    kpi_definitions: KpiDefinition[];
    can_review: boolean;
};

type EvaluationFormData = {
    summary: string;
    outcomes: string;
    completion_percent: string;
    satisfaction_score: string;
    lessons_learned: string;
    successful_practices: string;
    improvement_areas: string;
    recommendations: string;
    kpis: { position: number; actual: string; evidence_id: string; notes: string }[];
};

const achievementPreview = (kpi: PlanKpi, actualValue: string) => {
    const target = Number(kpi.target);
    const actual = Number(actualValue);
    if (actualValue === '' || Number.isNaN(actual)) return null;
    if ((kpi.formula || 'HIGHER_IS_BETTER') === 'LOWER_IS_BETTER') {
        if (actual <= target) return 100;
        return target === 0 ? 0 : Math.min(200, target / actual * 100);
    }
    if (target === 0) return actual === 0 ? 100 : 200;
    return Math.min(200, actual / target * 100);
};

const fieldError = (errors: Partial<Record<keyof EvaluationFormData, string>>, path: string) => (errors as Record<string, string>)[path];

function EvaluationEditor({ activityId, planKpis, evidenceItems, targetParticipants, actualParticipants, isRevision }: { activityId: string; planKpis: PlanKpi[]; evidenceItems: EvaluationEvidence[]; targetParticipants?: number; actualParticipants: number; isRevision: boolean }) {
    const errorSummary = useRef<HTMLDivElement>(null);
    const supersededIds = new Set(evidenceItems.map((item) => item.supersedes_id).filter(Boolean));
    const availableEvidence = evidenceItems.filter((item) => item.scan_status === 'REGISTERED' && item.review_status !== 'REJECTED' && !supersededIds.has(item.id));
    const form = useForm<EvaluationFormData>({
        summary: '', outcomes: '', completion_percent: '', satisfaction_score: '', lessons_learned: '', successful_practices: '', improvement_areas: '', recommendations: '',
        kpis: planKpis.map((_, position) => ({ position, actual: '', evidence_id: '', notes: '' })),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/activities/${activityId}/evaluations`, {
            preserveScroll: true,
            onError: () => requestAnimationFrame(() => errorSummary.current?.focus()),
        });
    };

    return <details className="rounded-xl border border-blue-200 bg-blue-50/40" open={!isRevision}>
        <summary className="flex min-h-12 cursor-pointer list-none items-center gap-3 px-4 py-3 text-sm font-bold text-primary-800"><span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-primary-700"><ClipboardCheck className="size-4" /></span>{isRevision ? 'Buat versi evaluasi baru' : 'Finalisasi evaluasi kegiatan'}</summary>
        <form onSubmit={submit} className="grid gap-5 border-t border-blue-100 p-4 sm:p-5">
            {form.hasErrors && <div ref={errorSummary} tabIndex={-1} role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"><b className="block">Evaluasi belum dapat disimpan.</b><span>Periksa field yang ditandai, termasuk setiap hasil KPI dan bukti pendukungnya.</span></div>}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-line-200 bg-white p-3"><span className="text-[10px] font-bold text-muted-600">TARGET PESERTA</span><strong className="tabular mt-1 block text-xl text-ink-950">{targetParticipants ?? '—'}</strong></div>
                <div className="rounded-lg border border-line-200 bg-white p-3"><span className="text-[10px] font-bold text-muted-600">PESERTA AKTUAL</span><strong className="tabular mt-1 block text-xl text-ink-950">{actualParticipants}</strong></div>
                <div><label className="label" htmlFor="evaluation-completion">Completion (%)</label><input id="evaluation-completion" required type="number" inputMode="decimal" min="0" max="100" step="0.01" className="field bg-white tabular" value={form.data.completion_percent} onChange={(event) => form.setData('completion_percent', event.target.value)} />{form.errors.completion_percent && <p className="error" role="alert">{form.errors.completion_percent}</p>}</div>
                <div><label className="label" htmlFor="evaluation-satisfaction">Satisfaction (0–5)</label><input id="evaluation-satisfaction" required type="number" inputMode="decimal" min="0" max="5" step="0.01" className="field bg-white tabular" value={form.data.satisfaction_score} onChange={(event) => form.setData('satisfaction_score', event.target.value)} />{form.errors.satisfaction_score && <p className="error" role="alert">{form.errors.satisfaction_score}</p>}</div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2"><label className="label" htmlFor="evaluation-summary">Ringkasan output</label><textarea id="evaluation-summary" required className="field min-h-24 bg-white" value={form.data.summary} onChange={(event) => form.setData('summary', event.target.value)} />{form.errors.summary && <p className="error" role="alert">{form.errors.summary}</p>}</div>
                <div className="sm:col-span-2"><label className="label" htmlFor="evaluation-outcomes">Outcome</label><textarea id="evaluation-outcomes" required className="field min-h-24 bg-white" value={form.data.outcomes} onChange={(event) => form.setData('outcomes', event.target.value)} />{form.errors.outcomes && <p className="error" role="alert">{form.errors.outcomes}</p>}</div>
                <div><label className="label" htmlFor="evaluation-lessons">Lessons learned</label><textarea id="evaluation-lessons" required className="field min-h-24 bg-white" value={form.data.lessons_learned} onChange={(event) => form.setData('lessons_learned', event.target.value)} />{form.errors.lessons_learned && <p className="error" role="alert">{form.errors.lessons_learned}</p>}</div>
                <div><label className="label" htmlFor="evaluation-practices">Yang berjalan baik</label><textarea id="evaluation-practices" required className="field min-h-24 bg-white" value={form.data.successful_practices} onChange={(event) => form.setData('successful_practices', event.target.value)} />{form.errors.successful_practices && <p className="error" role="alert">{form.errors.successful_practices}</p>}</div>
                <div><label className="label" htmlFor="evaluation-improvements">Area perbaikan</label><textarea id="evaluation-improvements" required className="field min-h-24 bg-white" value={form.data.improvement_areas} onChange={(event) => form.setData('improvement_areas', event.target.value)} />{form.errors.improvement_areas && <p className="error" role="alert">{form.errors.improvement_areas}</p>}</div>
                <div><label className="label" htmlFor="evaluation-recommendations">Rekomendasi PIC</label><textarea id="evaluation-recommendations" className="field min-h-24 bg-white" value={form.data.recommendations} onChange={(event) => form.setData('recommendations', event.target.value)} />{form.errors.recommendations && <p className="error" role="alert">{form.errors.recommendations}</p>}</div>
            </div>

            <fieldset className="grid gap-3">
                <legend className="text-sm font-bold text-ink-950">Hasil KPI dari planning</legend>
                <p className="text-xs leading-5 text-muted-600">Target tidak dapat diubah di sini. Setiap aktual harus ditautkan ke bukti yang sudah lolos pemeriksaan berkas.</p>
                {planKpis.map((kpi, index) => {
                    const row = form.data.kpis[index];
                    const preview = achievementPreview(kpi, row?.actual || '');
                    return <article key={`${kpi.name}-${index}`} className="rounded-xl border border-line-200 bg-white p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div><div className="flex flex-wrap items-center gap-2"><b className="text-sm text-ink-950">{kpi.name}</b><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">Bobot {kpi.weight || 1}</span></div><p className="mt-1 text-xs leading-5 text-muted-600">{kpi.definition || 'Definisi mengikuti indikator pada planning.'}</p></div>{preview !== null && <span aria-live="polite" className="tabular text-lg font-bold text-primary-700">{preview.toFixed(2)}%</span>}</div>
                        <dl className="mt-3 grid gap-2 text-[11px] sm:grid-cols-4"><div><dt className="text-muted-600">Baseline</dt><dd className="tabular font-bold">{kpi.baseline ?? '—'} {kpi.unit}</dd></div><div><dt className="text-muted-600">Target</dt><dd className="tabular font-bold">{kpi.target} {kpi.unit}</dd></div><div><dt className="text-muted-600">Periode</dt><dd className="font-bold">{kpi.period_start || 'Mulai kegiatan'} – {kpi.period_end || 'Selesai kegiatan'}</dd></div><div><dt className="text-muted-600">Formula</dt><dd className="font-bold">{(kpi.formula || 'HIGHER_IS_BETTER').replaceAll('_', ' ')}</dd></div></dl>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2"><div><label className="label" htmlFor={`kpi-actual-${index}`}>Aktual ({kpi.unit})</label><input id={`kpi-actual-${index}`} required type="number" inputMode="decimal" min="0" step="any" className="field tabular" value={row?.actual || ''} onChange={(event) => form.setData('kpis', form.data.kpis.map((item, position) => position === index ? { ...item, actual: event.target.value } : item))} />{fieldError(form.errors, `kpis.${index}.actual`) && <p className="error" role="alert">{fieldError(form.errors, `kpis.${index}.actual`)}</p>}</div><div><label className="label" htmlFor={`kpi-evidence-${index}`}>Evidence KPI</label><select id={`kpi-evidence-${index}`} required className="field" value={row?.evidence_id || ''} onChange={(event) => form.setData('kpis', form.data.kpis.map((item, position) => position === index ? { ...item, evidence_id: event.target.value } : item))}><option value="">Pilih bukti terdaftar</option>{availableEvidence.map((item) => <option key={item.id} value={item.id}>{item.title} · v{item.version}</option>)}</select>{fieldError(form.errors, `kpis.${index}.evidence_id`) && <p className="error" role="alert">{fieldError(form.errors, `kpis.${index}.evidence_id`)}</p>}</div><div className="sm:col-span-2"><label className="label" htmlFor={`kpi-notes-${index}`}>Catatan hasil (opsional)</label><textarea id={`kpi-notes-${index}`} className="field min-h-20" value={row?.notes || ''} onChange={(event) => form.setData('kpis', form.data.kpis.map((item, position) => position === index ? { ...item, notes: event.target.value } : item))} /></div></div>
                    </article>;
                })}
                {fieldError(form.errors, 'kpis') && <p className="error" role="alert">{fieldError(form.errors, 'kpis')}</p>}
            </fieldset>
            {availableEvidence.length === 0 && <div role="alert" className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><b className="block">Belum ada evidence KPI yang siap.</b>Unggah bukti dan tunggu pemeriksaan berkas selesai sebelum memfinalisasi evaluasi.</div>}
            <button className="btn-primary justify-self-start" disabled={form.processing || availableEvidence.length === 0 || planKpis.length === 0}>{form.processing ? 'Memfinalisasi…' : <><Send className="size-4" />Finalisasi & kirim review</>}</button>
        </form>
    </details>;
}

function EvaluationReviewForm({ evaluation }: { evaluation: ActivityEvaluation }) {
    const form = useForm({ decision: 'ACCEPTED', notes: '', severity: 'MEDIUM', due_at: '' });
    const finding = form.data.decision === 'FINDING';
    return <form className="mt-4 grid gap-3 rounded-xl border border-violet-200 bg-violet-50/50 p-4" onSubmit={(event) => { event.preventDefault(); form.patch(`/evaluations/${evaluation.id}/review`, { preserveScroll: true }); }}>
        <div className="flex items-center gap-2 text-sm font-bold text-violet-900"><ShieldCheck className="size-4" />Review Verifier Substantif</div>
        <div className="grid gap-3 sm:grid-cols-2"><div><label className="label" htmlFor={`evaluation-decision-${evaluation.id}`}>Keputusan</label><select id={`evaluation-decision-${evaluation.id}`} className="field bg-white" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}><option value="ACCEPTED">Terima evaluasi</option><option value="CLARIFICATION_REQUIRED">Minta klarifikasi</option><option value="FINDING">Buat finding evaluasi</option></select></div>{finding && <div><label className="label" htmlFor={`evaluation-severity-${evaluation.id}`}>Severity</label><select id={`evaluation-severity-${evaluation.id}`} className="field bg-white" value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}>{['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'].map((value) => <option key={value}>{value}</option>)}</select></div>}{finding && <div><label className="label" htmlFor={`evaluation-due-${evaluation.id}`}>Batas tindak lanjut</label><input id={`evaluation-due-${evaluation.id}`} required type="date" className="field bg-white" value={form.data.due_at} onChange={(event) => form.setData('due_at', event.target.value)} /></div>}<div className={finding ? '' : 'sm:col-span-2'}><label className="label" htmlFor={`evaluation-review-notes-${evaluation.id}`}>Catatan {form.data.decision === 'ACCEPTED' ? '(opsional)' : '(wajib)'}</label><textarea id={`evaluation-review-notes-${evaluation.id}`} required={form.data.decision !== 'ACCEPTED'} className="field min-h-20 bg-white" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div></div>
        {(form.errors.decision || form.errors.notes || form.errors.severity || form.errors.due_at) && <p className="error" role="alert">{form.errors.decision || form.errors.notes || form.errors.severity || form.errors.due_at}</p>}
        <button className="btn-secondary justify-self-start" disabled={form.processing}><CheckCircle2 className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan keputusan'}</button>
    </form>;
}

function EvaluationRecord({ evaluation, latest }: { evaluation: ActivityEvaluation; latest: boolean }) {
    return <article className={`rounded-xl border p-4 sm:p-5 ${latest ? 'border-blue-200 bg-blue-50/30' : 'border-line-200 bg-white'}`}>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><b className="text-sm text-ink-950">Evaluasi v{evaluation.version}</b><StatusBadge status={evaluation.status} />{latest && <span className="rounded-full bg-blue-100 px-2 py-1 text-[10px] font-bold text-primary-800">VERSI TERKINI</span>}</div><p className="mt-1 text-[11px] text-muted-600">{evaluation.creator.name} · {formatDate(evaluation.created_at, true)}</p><code className="mt-1 block break-all text-[9px] text-slate-500">Evaluation-ID · {evaluation.id}</code></div>{evaluation.score && <div className="text-left sm:text-right"><span className="text-[10px] font-bold text-muted-600">CAPAIAN TERTIMBANG</span><strong className="tabular block text-2xl text-primary-700">{Number(evaluation.score).toFixed(2)}%</strong></div>}</div>
        <p className="mt-4 text-sm leading-6 text-slate-700">{evaluation.summary}</p>
        {evaluation.outcomes && <div className="mt-3 rounded-lg border-l-4 border-primary-500 bg-white p-3 text-xs leading-5 text-slate-700"><b className="block text-ink-950">Outcome</b>{evaluation.outcomes}</div>}
        <dl className="mt-4 grid gap-2 text-xs sm:grid-cols-3"><div className="rounded-lg bg-white p-3"><dt className="text-[10px] font-bold text-muted-600">COMPLETION</dt><dd className="tabular mt-1 font-bold">{evaluation.completion_percent ? `${evaluation.completion_percent}%` : '—'}</dd></div><div className="rounded-lg bg-white p-3"><dt className="text-[10px] font-bold text-muted-600">SATISFACTION</dt><dd className="tabular mt-1 font-bold">{evaluation.satisfaction_score ? `${evaluation.satisfaction_score}/5` : '—'}</dd></div><div className="rounded-lg bg-white p-3"><dt className="text-[10px] font-bold text-muted-600">PESERTA AKTUAL</dt><dd className="tabular mt-1 font-bold">{evaluation.metrics?.actual_participants ?? '—'}</dd></div></dl>
        {evaluation.kpi_definitions?.length > 0 && <div className="mt-4 grid gap-3 lg:grid-cols-2">{evaluation.kpi_definitions.map((kpi) => <div key={kpi.id} className="rounded-lg border border-line-200 bg-white p-3"><div className="flex items-start justify-between gap-3"><div><b className="text-xs text-ink-950">{kpi.name}</b><p className="mt-1 text-[10px] leading-4 text-muted-600">{kpi.definition}</p></div>{kpi.result && <strong className="tabular text-sm text-emerald-700">{kpi.result.achievement_percent}%</strong>}</div><dl className="mt-3 grid grid-cols-3 gap-2 text-[10px]"><div><dt className="text-muted-600">Baseline</dt><dd className="tabular font-bold">{kpi.baseline ?? '—'}</dd></div><div><dt className="text-muted-600">Target</dt><dd className="tabular font-bold">{kpi.target}</dd></div><div><dt className="text-muted-600">Aktual</dt><dd className="tabular font-bold">{kpi.result?.actual ?? '—'} {kpi.unit}</dd></div></dl>{kpi.result?.evidence && <p className="mt-3 flex items-center gap-2 text-[10px] font-semibold text-primary-700"><FileCheck2 className="size-3.5" />{kpi.result.evidence.title} · v{kpi.result.evidence.version}</p>}<p className="mt-2 text-[9px] text-muted-600">{kpi.formula.replaceAll('_', ' ')} · {kpi.formula_version} · {kpi.period_start}–{kpi.period_end}</p>{kpi.result && <code className="mt-1 block break-all text-[9px] text-slate-500">KPI-Result-ID · {kpi.result.id}</code>}</div>)}</div>}
        <div className="mt-4 grid gap-3 text-xs sm:grid-cols-3"><div><b className="block text-ink-950">Lessons learned</b><p className="mt-1 leading-5 text-muted-600">{evaluation.lessons_learned || '—'}</p></div><div><b className="block text-ink-950">Yang berjalan baik</b><p className="mt-1 leading-5 text-muted-600">{evaluation.successful_practices || '—'}</p></div><div><b className="block text-ink-950">Area perbaikan</b><p className="mt-1 leading-5 text-muted-600">{evaluation.improvement_areas || '—'}</p></div></div>
        {evaluation.review_notes && <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><b className="block">Catatan {evaluation.reviewer?.name || 'verifier'}</b>{evaluation.review_notes}</div>}
        {latest && evaluation.can_review && evaluation.status === 'SUBMITTED' && <EvaluationReviewForm evaluation={evaluation} />}
    </article>;
}

export function ActivityEvaluationPanel({ activityId, activityStatus, planKpis, targetParticipants, actualParticipants, evidenceItems, evaluations, canManage }: { activityId: string; activityStatus: string; planKpis: PlanKpi[]; targetParticipants?: number; actualParticipants: number; evidenceItems: EvaluationEvidence[]; evaluations: ActivityEvaluation[]; canManage: boolean }) {
    const latest = evaluations[0];
    const canSubmit = canManage && activityStatus === 'COMPLETED' && latest?.status !== 'SUBMITTED';
    return <section className="panel overflow-hidden" aria-labelledby="activity-evaluation-title">
        <div className="flex flex-col gap-3 border-b border-line-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><div className="flex items-center gap-2"><BarChart3 className="size-5 text-primary-700" /><h2 id="activity-evaluation-title" className="text-base font-bold">Evaluasi, output & outcome</h2></div><p className="mt-1 text-xs leading-5 text-muted-600">Target planning, hasil aktual, formula terversi, evidence KPI, dan review substantif dalam satu lineage.</p></div>{latest && <StatusBadge status={latest.status} />}</div>
        <div className="grid gap-4 p-4 sm:p-5">
            {canSubmit && <EvaluationEditor activityId={activityId} planKpis={planKpis} evidenceItems={evidenceItems} targetParticipants={targetParticipants} actualParticipants={actualParticipants} isRevision={evaluations.length > 0} />}
            {!canSubmit && latest?.status === 'SUBMITTED' && <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-primary-900"><ShieldCheck className="mt-0.5 size-4 shrink-0" /><span><b className="block">Menunggu review substantif.</b>PIC dapat membuat versi baru setelah verifier menerima, meminta klarifikasi, atau membuka finding.</span></div>}
            {activityStatus !== 'COMPLETED' && canManage && <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><AlertTriangle className="mt-0.5 size-4 shrink-0" /><span>Evaluasi dapat difinalisasi setelah kegiatan berstatus selesai.</span></div>}
            {evaluations.length > 0 ? evaluations.map((evaluation, index) => <EvaluationRecord key={evaluation.id} evaluation={evaluation} latest={index === 0} />) : <div className="rounded-xl border border-dashed border-line-200 p-8 text-center"><BarChart3 className="mx-auto size-8 text-slate-300" /><b className="mt-3 block text-sm text-slate-700">Belum ada evaluasi</b><p className="mt-1 text-xs text-muted-600">Finalisasi hasil setelah pelaksanaan selesai dan evidence KPI tersedia.</p></div>}
        </div>
    </section>;
}
