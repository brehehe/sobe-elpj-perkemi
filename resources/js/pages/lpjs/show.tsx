import { PageHeader } from '@/components/page-header';
import { LpjComposer, type LpjComposerDraft, type LpjComposerReadiness } from '@/components/lpj-composer';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency, formatDate } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    ArrowLeft,
    BadgeCheck,
    Bot,
    CheckCircle2,
    ClipboardCheck,
    CircleX,
    Download,
    ExternalLink,
    FileLock2,
    Fingerprint,
    Forward,
    Landmark,
    ListChecks,
    LockKeyhole,
    RotateCcw,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';

type Verification = { id: string; stage: string; status: string; notes?: string; checklist?: { item: string; result: string; notes?: string }[]; evidence_ids?: string[]; material_exception?: string; verified_at?: string; verifier?: { name: string } };
type EvidenceOption = { id: string; title: string; version: number; type: string; review_status: string };
type CorrectiveAction = { id: string; action: string; status: string; evidence_path?: string; submitted_at: string; submitter: { name: string } };
type Finding = { id: string; code: string; severity: string; status: string; title: string; detail: string; criteria: string; condition: string; cause: string; risk: string; recommendation: string; due_at?: string; owner?: { name: string }; corrective_actions: CorrectiveAction[] };
type Approval = { id: string; level: string; decision: string; notes?: string; authority_policy_version?: string; authority_reference?: string; decision_pack_checksum?: string; decided_at: string; decision_maker: { name: string } };
type VerifiedPack = { id: string; approval_id: string; verification_number: string; policy_version: string; checksum: string; public_token: string; issued_at: string };
type Disposition = { id: string; subject: string; instructions: string; priority: string; status: string; due_at?: string; evidence_ids?: string[]; response_notes?: string; review_notes?: string; reviewed_at?: string; can_respond: boolean; can_review: boolean; sender: { name: string }; recipient?: { name: string }; reviewer?: { name: string } };
type AiReview = { id: string; type: string; status: string; model?: string; output?: { summary?: string; risks?: string[]; recommendations?: string[] }; confidence?: string; limitations?: string; human_review_status: string; reviewed_at?: string; requester: { id: string; name: string }; reviewer?: { name: string } };
type Version = { id: string; version: number; status: string; checksum?: string; submitted_at?: string; is_immutable: boolean };
type CurrentVersion = Version & {
    snapshot?: Record<string, unknown>;
    verifications: Verification[];
    findings: Finding[];
    approvals: Approval[];
    dispositions: Disposition[];
    verified_pack?: VerifiedPack | null;
    archive?: { id: string; archive_number: string; checksum: string; archived_at: string; retention_until?: string; retention_policy_version?: string } | null;
};
type LpjDetail = LpjComposerDraft & {
    id: string;
    status: string;
    current_version: number;
    total_amount: string;
    submitted_at?: string;
    approved_at?: string;
    pb_verified_at?: string;
    archived_at?: string;
    owner: { name: string; email: string };
    activity: {
        id: string;
        code: string;
        title: string;
        starts_at: string;
        ends_at: string;
        budget_ceiling: string;
        unit: { code: string; name: string };
        mandate: { number: string; title: string };
        participants: unknown[];
        transactions: { reconciliation_status: string; reconciliation?: { exceptions: { status: string }[] } }[];
        evidence: EvidenceOption[];
        evaluations: unknown[];
        plan?: { code: string; status: string };
        budget?: { code: string; status: string };
        ai_reviews: AiReview[];
    };
    versions: Version[];
    current_version_record?: CurrentVersion | null;
};

type Capabilities = { submit: boolean; exportDraft: boolean; verify: boolean; approve: boolean; pbVerify: boolean; archive: boolean; openFinding: boolean; requestAi: boolean; reviewAi: boolean; createDisposition: boolean; respondDisposition: boolean };
type UserOption = { id: string; name: string };
type VerificationWorkflowStage = { stage: string; label: string; status: string; state: 'COMPLETED' | 'ACTIVE' | 'LOCKED'; checklist: string[]; verifier?: { id: string; name: string }; verified_at?: string; can_verify: boolean };
type VerificationWorkflow = { policy_version: string; completed: number; total: number; percentage: number; next_stage?: string | null; next_stage_label?: string | null; can_verify_next_stage: boolean; blocked_reason?: string | null; stages: VerificationWorkflowStage[] };
type ApprovalCheck = { key: string; label: string; passed: boolean; message: string };
type ApprovalAuthority = { valid: boolean; source?: string | null; reference?: string | null; role?: string | null; unit_scope?: string; evaluated_amount: string; max_amount?: string | null; valid_until?: string | null };
type ApprovalDecisionPack = {
    lpj: { id: string; version: number; checksum: string; submitted_at?: string };
    activity: { code: string; title: string; location?: string; unit: { code: string; name: string }; mandate: { number?: string; title?: string } };
    financial: { currency: string; budget: string; actual: string; variance: string; transaction_count: number; reconciled_count: number };
    verifications: { stage: string; status: string; verifier?: { name: string } | null; verified_at?: string | null }[];
    findings: unknown[];
    approved_exceptions: unknown[];
    open_dispositions: unknown[];
    audit_history: { event_id: string; event_type: string; from_status?: string; to_status?: string; created_at?: string }[];
    source_references: { activity_id?: string; mandate_id?: string; plan_id?: string; plan_version?: number; budget_id?: string; budget_version?: number; evidence: unknown[] };
};
type ApprovalWorkflow = { policy_version: string; level: string; level_label: string; can_decide: boolean; can_approve: boolean; blockers: string[]; authority?: ApprovalAuthority | null; checks: ApprovalCheck[]; decision_pack?: ApprovalDecisionPack | null };

const steps = [
    { label: 'Draf', states: ['DRAFT', 'READY_TO_SUBMIT', 'RETURNED'] },
    { label: 'Verifikasi', states: ['SUBMITTED', 'IN_VERIFICATION', 'CLARIFICATION_REQUIRED', 'FINDING_OPEN'] },
    { label: 'Persetujuan', states: ['READY_FOR_APPROVAL', 'REJECTED'] },
    { label: 'PB', states: ['APPROVED'] },
    { label: 'Arsip', states: ['PB_VERIFIED', 'ARCHIVED'] },
];

function FormError({ message }: { message?: string }) {
    return message ? <p className="error" role="alert">{message}</p> : null;
}

function ActionCard({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
    return <details className="group rounded-xl border border-line-200 bg-white open:shadow-sm">
        <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 focus-visible:outline-none">
            <span><b className="block text-sm text-ink-950">{title}</b><span className="mt-0.5 block text-xs leading-5 text-muted-600">{description}</span></span>
            <span aria-hidden="true" className="text-xl text-slate-400 transition group-open:rotate-45">+</span>
        </summary>
        <div className="border-t border-line-200 p-4">{children}</div>
    </details>;
}

function ApprovalDecisionPackPanel({ workflow }: { workflow: ApprovalWorkflow }) {
    const pack = workflow.decision_pack;
    if (!pack) return null;
    const issueCount = pack.findings.length + pack.open_dispositions.length;

    return <section className="panel overflow-hidden" aria-labelledby="approval-pack-title">
        <div className="border-b border-line-200 bg-slate-950 px-5 py-5 text-white">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div><p className="text-[10px] font-bold tracking-[0.18em] text-blue-300">APPROVAL DECISION PACK · LPJ v{pack.lpj.version}</p><h2 id="approval-pack-title" className="mt-2 text-base font-bold">{pack.activity.code} · {pack.activity.title}</h2><p className="mt-1 text-xs text-slate-300">{pack.activity.unit.code} · {pack.activity.unit.name} · {workflow.level_label}</p></div>
                <div className="rounded-lg border border-white/15 bg-white/5 px-3 py-2 text-right"><p className="text-[9px] font-bold text-slate-400">POLICY</p><p className="mt-1 font-mono text-[10px] text-blue-200">{workflow.policy_version}</p></div>
            </div>
        </div>
        <div className="grid border-b border-line-200 sm:grid-cols-2 xl:grid-cols-4">
            {[
                ['Pagu', formatCurrency(pack.financial.budget), Landmark],
                ['Realisasi', formatCurrency(pack.financial.actual), BadgeCheck],
                ['Varians', formatCurrency(pack.financial.variance), ListChecks],
                ['Isu terbuka', `${issueCount}`, issueCount === 0 ? CheckCircle2 : AlertTriangle],
            ].map(([label, value, Icon], index) => <div key={String(label)} className={`p-4 ${index < 3 ? 'border-b border-line-200 sm:odd:border-r xl:border-r xl:border-b-0' : ''}`}><div className="flex items-center gap-2 text-muted-600"><Icon aria-hidden="true" className="size-4" /><span className="text-[10px] font-bold">{String(label).toUpperCase()}</span></div><strong className="tabular mt-2 block text-sm text-ink-950">{String(value)}</strong></div>)}
        </div>
        <div className="grid lg:grid-cols-[minmax(0,1fr)_minmax(280px,.72fr)]">
            <div className="border-b border-line-200 p-5 lg:border-r lg:border-b-0">
                <div className="flex items-center justify-between gap-3"><div><h3 className="text-xs font-bold">Gate keputusan</h3><p className="mt-1 text-[11px] text-muted-600">Kelengkapan, integritas, kewenangan, dan pemisahan tugas.</p></div><StatusBadge status={workflow.can_approve ? 'VERIFIED' : 'FINDING'} /></div>
                <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                    {workflow.checks.map((check) => <li key={check.key} className="flex min-h-10 items-start gap-2 rounded-lg bg-slate-50 px-3 py-2 text-[11px]"><span className={`mt-0.5 ${check.passed ? 'text-emerald-700' : 'text-red-700'}`}>{check.passed ? <CheckCircle2 aria-hidden="true" className="size-4" /> : <CircleX aria-hidden="true" className="size-4" />}</span><span><b className="block text-ink-950">{check.label}</b>{!check.passed && <span className="mt-0.5 block leading-4 text-red-700">{check.message}</span>}</span></li>)}
                </ul>
            </div>
            <div className="p-5">
                <h3 className="text-xs font-bold">Jejak sumber</h3>
                <dl className="mt-4 grid gap-3 text-[11px]"><div><dt className="font-bold text-muted-600">MANDAT</dt><dd className="mt-1 font-semibold">{pack.activity.mandate.number || '—'} · {pack.activity.mandate.title || '—'}</dd></div><div><dt className="font-bold text-muted-600">VERSI SUMBER</dt><dd className="mt-1 font-semibold">Rencana v{pack.source_references.plan_version || '—'} · Anggaran v{pack.source_references.budget_version || '—'}</dd></div><div><dt className="font-bold text-muted-600">REKONSILIASI</dt><dd className="mt-1 font-semibold">{pack.financial.reconciled_count}/{pack.financial.transaction_count} transaksi selesai</dd></div><div><dt className="font-bold text-muted-600">RIWAYAT PENTING</dt><dd className="mt-1 font-semibold">{pack.audit_history.length} event · {pack.source_references.evidence.length} bukti sumber</dd></div></dl>
                <p className="mt-4 [overflow-wrap:anywhere] rounded-lg bg-slate-50 p-3 font-mono text-[9px] leading-4 text-slate-500">Snapshot {pack.lpj.checksum}</p>
            </div>
        </div>
    </section>;
}

function VerifiedPackPanel({ verifiedPack }: { verifiedPack: VerifiedPack }) {
    return <section className="overflow-hidden rounded-xl border border-emerald-700/20 bg-white shadow-sm" aria-labelledby="verified-pack-title">
        <div className="bg-emerald-950 p-5 text-white">
            <div className="flex items-start justify-between gap-4">
                <div><p className="text-[10px] font-bold tracking-[0.18em] text-emerald-300">PB VERIFIED · IMMUTABLE</p><h2 id="verified-pack-title" className="mt-2 text-sm font-bold">Verified Pack diterbitkan</h2><p className="mt-1 text-[11px] text-emerald-100">Terikat ke approval dan snapshot LPJ final.</p></div>
                <span className="grid size-11 shrink-0 place-items-center rounded-full border border-emerald-400/40 bg-emerald-400/10"><ShieldCheck aria-hidden="true" className="size-6 text-emerald-300" /></span>
            </div>
        </div>
        <dl className="grid gap-3 p-5 text-[11px]">
            <div><dt className="flex items-center gap-2 font-bold text-muted-600"><Fingerprint aria-hidden="true" className="size-4" />VERIFICATION-ID</dt><dd className="mt-1 font-mono font-semibold text-ink-950 [overflow-wrap:anywhere]">{verifiedPack.verification_number}</dd></div>
            <div><dt className="font-bold text-muted-600">APPROVAL-ID</dt><dd className="mt-1 font-mono text-[10px] [overflow-wrap:anywhere]">{verifiedPack.approval_id}</dd></div>
            <div><dt className="font-bold text-muted-600">DITERBITKAN</dt><dd className="mt-1 font-semibold">{formatDate(verifiedPack.issued_at, true)}</dd></div>
            <div><dt className="flex items-center gap-2 font-bold text-muted-600"><LockKeyhole aria-hidden="true" className="size-4" />SHA-256</dt><dd className="mt-1 font-mono text-[9px] leading-4 text-slate-500 [overflow-wrap:anywhere]">{verifiedPack.checksum}</dd></div>
        </dl>
        <div className="grid gap-2 border-t border-line-200 p-4 sm:grid-cols-2 xl:grid-cols-1">
            <a href={`/verify/${verifiedPack.public_token}`} target="_blank" rel="noreferrer" className="btn-primary w-full"><ExternalLink aria-hidden="true" className="size-4" />Validasi publik<span className="sr-only"> (tab baru)</span></a>
            <a href={`/verified-packs/${verifiedPack.id}/download`} className="btn-secondary w-full"><Download aria-hidden="true" className="size-4" />Unduh Verified Pack</a>
        </div>
    </section>;
}

function CorrectiveActionForm({ finding }: { finding: Finding }) {
    const form = useForm<{ action: string; evidence: File | null }>({ action: '', evidence: null });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/findings/${finding.id}/corrective-actions`, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() });
    };

    return <form onSubmit={submit} className="mt-4 grid gap-3 border-t border-slate-100 pt-4">
        <div><label className="label" htmlFor={`action-${finding.id}`}>Tindakan korektif</label><textarea id={`action-${finding.id}`} className="field min-h-24" value={form.data.action} onChange={(event) => form.setData('action', event.target.value)} required /><FormError message={form.errors.action} /></div>
        <div><label className="label" htmlFor={`evidence-${finding.id}`}>Bukti perbaikan (opsional)</label><input id={`evidence-${finding.id}`} className="field file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:font-semibold file:text-primary-700" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(event) => form.setData('evidence', event.target.files?.[0] ?? null)} /><FormError message={form.errors.evidence} /></div>
        <button className="btn-secondary justify-self-start" disabled={form.processing}><RotateCcw className="size-4" />{form.processing ? 'Mengirim…' : 'Ajukan CAPA'}</button>
    </form>;
}

function CorrectiveActionReview({ finding, correctiveAction }: { finding: Finding; correctiveAction: CorrectiveAction }) {
    const form = useForm({ decision: 'ACCEPTED', notes: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/findings/${finding.id}/corrective-actions/${correctiveAction.id}/review`, { preserveScroll: true });
    };

    return <form onSubmit={submit} className="mt-3 grid gap-3 border-t border-emerald-200 pt-3"><div><label className="label" htmlFor={`review-${correctiveAction.id}`}>Hasil pemeriksaan CAPA</label><select id={`review-${correctiveAction.id}`} className="field" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}><option value="ACCEPTED">Terima dan tutup temuan</option><option value="REJECTED">Tolak dan minta perbaikan</option></select></div><div><label className="label" htmlFor={`review-notes-${correctiveAction.id}`}>Catatan pemeriksa</label><textarea id={`review-notes-${correctiveAction.id}`} className="field min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /><FormError message={form.errors.notes || form.errors.decision} /></div><button className="btn-secondary justify-self-start" disabled={form.processing}><ShieldCheck className="size-4" />Simpan pemeriksaan</button></form>;
}

function DispositionActions({ disposition, evidence }: { disposition: Disposition; evidence: EvidenceOption[] }) {
    const form = useForm({ status: '', notes: '', evidence_ids: [] as string[] });
    const update = (status: string) => {
        form.setData('status', status);
        form.transform((data) => ({ ...data, status }));
        form.patch(`/dispositions/${disposition.id}`, { preserveScroll: true });
    };
    if (disposition.can_respond && ['OPEN', 'ACKNOWLEDGED', 'REOPENED'].includes(disposition.status)) {
        const [status, label] = disposition.status === 'OPEN' ? ['ACKNOWLEDGED', 'Akui disposisi'] : ['IN_PROGRESS', disposition.status === 'REOPENED' ? 'Kerjakan kembali' : 'Mulai kerjakan'];
        return <button className="btn-secondary mt-3 min-h-9 px-3 py-1 text-xs" disabled={form.processing} onClick={() => update(status)}>{label}</button>;
    }
    if (disposition.can_respond && disposition.status === 'IN_PROGRESS') {
        const activeEvidence = evidence.filter((item) => item.review_status !== 'SUPERSEDED');
        return <form className="mt-3 grid gap-2 rounded-lg border border-blue-100 bg-blue-50/60 p-3" onSubmit={(event) => { event.preventDefault(); update('EVIDENCE_SUBMITTED'); }}><fieldset className="grid gap-1"><legend className="label">Bukti tindak lanjut</legend>{activeEvidence.map((item) => <label key={item.id} className="flex min-h-9 items-center gap-2 rounded-md px-2 text-xs hover:bg-white"><input type="checkbox" className="size-4 accent-blue-700" checked={form.data.evidence_ids.includes(item.id)} onChange={(event) => form.setData('evidence_ids', event.target.checked ? [...form.data.evidence_ids, item.id] : form.data.evidence_ids.filter((id) => id !== item.id))} /><span className="min-w-0 flex-1 truncate">{item.title} · v{item.version}</span></label>)}</fieldset><div><label className="label" htmlFor={`disposition-response-${disposition.id}`}>Ringkasan tindak lanjut</label><textarea id={`disposition-response-${disposition.id}`} required className="field min-h-20 bg-white" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div><FormError message={form.errors.evidence_ids || form.errors.notes || form.errors.status} /><button className="btn-secondary" disabled={form.processing}><Send className="size-4" />Kirim bukti penyelesaian</button></form>;
    }
    if (disposition.can_review && disposition.status === 'EVIDENCE_SUBMITTED') {
        return <div className="mt-3 grid gap-2 rounded-lg border border-line-200 p-3"><div><label className="label" htmlFor={`disposition-review-${disposition.id}`}>Catatan pemeriksa</label><textarea id={`disposition-review-${disposition.id}`} required className="field min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div><FormError message={form.errors.notes || form.errors.status} /><div className="flex flex-wrap gap-2"><button className="btn-primary min-h-9 px-3 py-1 text-xs" disabled={form.processing || !form.data.notes.trim()} onClick={() => update('CLOSED')}><CheckCircle2 className="size-4" />Terima & tutup</button><button className="btn-secondary min-h-9 px-3 py-1 text-xs" disabled={form.processing || !form.data.notes.trim()} onClick={() => update('IN_PROGRESS')}><RotateCcw className="size-4" />Minta perbaikan</button></div></div>;
    }
    if (disposition.can_review && disposition.status === 'CLOSED') {
        return <div className="mt-3 grid gap-2"><label className="label" htmlFor={`disposition-reopen-${disposition.id}`}>Alasan buka kembali</label><textarea id={`disposition-reopen-${disposition.id}`} required className="field min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /><button className="btn-secondary justify-self-start" disabled={form.processing || !form.data.notes.trim()} onClick={() => update('REOPENED')}><RotateCcw className="size-4" />Buka kembali</button></div>;
    }

    return null;
}

function AiHumanReview({ review }: { review: AiReview }) {
    const form = useForm({ decision: 'ACCEPTED', review_notes: '' });
    return <form className="mt-3 grid gap-3 border-t border-blue-100 pt-3" onSubmit={(event) => { event.preventDefault(); form.patch(`/ai-reviews/${review.id}/human-review`, { preserveScroll: true }); }}><div className="flex flex-wrap gap-2"><select aria-label="Keputusan review manusia" className="field max-w-56" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}><option value="ACCEPTED">Terima sebagai masukan</option><option value="REJECTED">Tolak rekomendasi</option></select><button className="btn-secondary" disabled={form.processing || (form.data.decision === 'REJECTED' && !form.data.review_notes.trim())}><ShieldCheck aria-hidden="true" className="size-4" />Catat review manusia</button></div><div><label className="label" htmlFor={`ai-review-notes-${review.id}`}>Alasan / catatan {form.data.decision === 'REJECTED' ? '(wajib)' : '(opsional)'}</label><textarea id={`ai-review-notes-${review.id}`} className="field min-h-20" required={form.data.decision === 'REJECTED'} value={form.data.review_notes} onChange={(event) => form.setData('review_notes', event.target.value)} /><FormError message={form.errors.review_notes || form.errors.decision} /></div></form>;
}

function VerificationAction({ lpjId, evidence, workflow }: { lpjId: string; evidence: EvidenceOption[]; workflow: VerificationWorkflow }) {
    const stage = workflow.stages.find((item) => item.stage === workflow.next_stage);
    const form = useForm({
        stage: stage?.stage || '',
        status: 'VERIFIED',
        notes: '',
        checklist: (stage?.checklist || []).map((item) => ({ item, result: 'PASS', notes: '' })),
        evidence_ids: [] as string[],
        material_exception: '',
    });
    const fieldError = (path: string) => (form.errors as Record<string, string>)[path];

    if (!stage) return null;

    return <ActionCard title={`Verifikasi ${stage.label}`} description={`Gate aktif berikutnya · ${workflow.policy_version}`}>
        <form onSubmit={(event) => { event.preventDefault(); form.post(`/lpjs/${lpjId}/verifications`, { preserveScroll: true }); }} className="grid gap-4">
            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                <span className="text-[10px] font-bold tracking-wide text-blue-700">TAHAP {workflow.completed + 1} DARI {workflow.total}</span>
                <p className="mt-1 text-sm font-bold text-ink-950">{stage.label}</p>
                <p className="mt-1 text-[11px] leading-5 text-blue-900">Urutan gate dikunci oleh snapshot kebijakan. Tahap yang sudah selesai tidak dapat ditimpa.</p>
            </div>
            <fieldset className="grid gap-2 rounded-lg border border-line-200 p-3">
                <legend className="px-1 text-xs font-bold">Checklist domain</legend>
                {form.data.checklist.map((check, index) => <div key={check.item} className="grid gap-2 rounded-md bg-slate-50 p-2 sm:grid-cols-[1fr_150px]">
                    <div><label className="label" htmlFor={`check-notes-${index}`}>{check.item}</label><input id={`check-notes-${index}`} className="field bg-white" placeholder="Catatan item (opsional)" value={check.notes} onChange={(event) => form.setData('checklist', form.data.checklist.map((item, itemIndex) => itemIndex === index ? { ...item, notes: event.target.value } : item))} /></div>
                    <div><label className="label" htmlFor={`check-result-${index}`}>Hasil</label><select id={`check-result-${index}`} className="field bg-white" value={check.result} onChange={(event) => form.setData('checklist', form.data.checklist.map((item, itemIndex) => itemIndex === index ? { ...item, result: event.target.value } : item))}><option value="PASS">Lolos</option><option value="FAIL">Tidak lolos</option><option value="NOT_APPLICABLE">Tidak berlaku</option></select></div>
                    {fieldError(`checklist.${index}.result`) && <p className="error sm:col-span-2">{fieldError(`checklist.${index}.result`)}</p>}
                </div>)}
                <FormError message={form.errors.checklist} />
            </fieldset>
            <fieldset className="grid gap-2 rounded-lg border border-line-200 p-3">
                <legend className="px-1 text-xs font-bold">Evidence yang diperiksa</legend>
                {evidence.map((item) => <label key={item.id} className="flex min-h-10 cursor-pointer items-center gap-3 rounded-md px-2 text-xs hover:bg-slate-50"><input type="checkbox" className="size-4 accent-blue-700" checked={form.data.evidence_ids.includes(item.id)} onChange={(event) => form.setData('evidence_ids', event.target.checked ? [...form.data.evidence_ids, item.id] : form.data.evidence_ids.filter((id) => id !== item.id))} /><span className="min-w-0 flex-1 truncate">{item.title} · v{item.version}</span><StatusBadge status={item.review_status} /></label>)}
                <FormError message={form.errors.evidence_ids} />
            </fieldset>
            <div><label className="label" htmlFor="material-exception">Exception material (opsional)</label><textarea id="material-exception" className="field min-h-20" value={form.data.material_exception} onChange={(event) => form.setData('material_exception', event.target.value)} /><FormError message={form.errors.material_exception} /></div>
            <div><label className="label" htmlFor="verification-status">Keputusan</label><select id="verification-status" className="field" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="VERIFIED">VERIFIED · domain lolos</option><option value="FINDING">FINDING · memerlukan CAPA</option><option value="RETURNED">RETURNED · revisi dan submit versi baru</option><option value="CLARIFICATION_REQUIRED">CLARIFICATION REQUIRED · minta informasi tambahan</option></select><FormError message={form.errors.status} />{form.data.status === 'FINDING' && <p className="mt-1 text-[11px] leading-5 text-amber-800">Buka temuan terlebih dahulu agar owner, tenggat, dan CAPA tercatat.</p>}</div>
            <div><label className="label" htmlFor="verification-notes">Catatan keputusan {form.data.status === 'VERIFIED' ? '(opsional)' : '(wajib)'}</label><textarea id="verification-notes" className="field min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /><FormError message={form.errors.notes} /></div>
            <button className="btn-primary" disabled={form.processing}><ShieldCheck className="size-4" />{form.processing ? 'Menyimpan…' : `Simpan keputusan ${stage.label}`}</button>
        </form>
    </ActionCard>;
}

export default function LpjShow({ lpj, composer, verificationWorkflow, approvalWorkflow, can, dispositionUsers }: { lpj: LpjDetail; composer: LpjComposerReadiness; verificationWorkflow: VerificationWorkflow; approvalWorkflow: ApprovalWorkflow; can: Capabilities; dispositionUsers: UserOption[] }) {
    const version = lpj.current_version_record;
    const activeStep = Math.max(0, steps.findIndex((step) => step.states.includes(lpj.status)));
    const canEditComposer = can.submit && ['DRAFT', 'READY_TO_SUBMIT', 'RETURNED'].includes(lpj.status);

    const submitForm = useForm({ notes: '' });
    const findingForm = useForm({ severity: 'MEDIUM', title: '', detail: '', criteria: '', condition: '', cause: '', risk: '', recommendation: '', owner_id: '', due_at: '' });
    const dispositionForm = useForm({ to_user_id: '', to_role: '', subject: '', instructions: '', priority: 'MEDIUM', due_at: '' });
    const approvalForm = useForm({ level: can.pbVerify && lpj.status === 'APPROVED' ? 'PB' : 'APPROVER', decision: 'APPROVED', notes: '' });
    const archiveForm = useForm<Record<string, string>>({});
    const aiForm = useForm({ activity_id: lpj.activity.id, type: 'PRECHECK' });
    const approvalErrorRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (Object.keys(approvalForm.errors).length > 0) approvalErrorRef.current?.focus();
    }, [approvalForm.errors]);

    return <>
        <Head title={`LPJ ${lpj.activity.code}`} />
        <PageHeader
            eyebrow={`${lpj.activity.unit.code} · ${lpj.activity.code}`}
            title={`LPJ ${lpj.activity.title}`}
            description={`Pemilik ${lpj.owner.name} · versi aktif ${lpj.current_version || 'belum diajukan'}`}
            actions={<><Link href="/lpjs" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link><Link href={`/activities/${lpj.activity.id}`} className="btn-secondary">Data sumber</Link>{can.exportDraft && <a href={`/lpjs/${lpj.id}/draft-export`} className="btn-primary"><Download aria-hidden="true" className="size-4" />Ekspor draf</a>}</>}
        />

        <section aria-label="Tahapan LPJ" className="panel mt-6 overflow-hidden p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3"><div><h2 className="text-sm font-bold">Alur persetujuan</h2><p className="mt-1 text-xs text-muted-600">Status saat ini <StatusBadge status={lpj.status} /></p></div><span className="tabular text-xs font-semibold text-muted-600">v{lpj.current_version}</span></div>
            <ol className="mt-5 grid grid-cols-5 gap-2">
                {steps.map((step, index) => <li key={step.label} aria-current={index === activeStep ? 'step' : undefined} className="min-w-0">
                    <div className={`h-1.5 rounded-full ${index <= activeStep ? 'bg-primary-600' : 'bg-slate-200'}`} />
                    <span className={`mt-2 block text-[10px] font-bold sm:text-xs ${index === activeStep ? 'text-primary-700' : 'text-muted-600'}`}>{step.label}</span>
                </li>)}
            </ol>
        </section>

        <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_390px]">
            <div className="grid content-start gap-5">
                <LpjComposer lpj={lpj} activityId={lpj.activity.id} activityCode={lpj.activity.code} composer={composer} canEdit={canEditComposer} />

                <section className="panel p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4"><div><h2 className="text-base font-bold">Ringkasan pertanggungjawaban</h2><p className="mt-1 text-xs text-muted-600">Snapshot mengikat Activity-ID, mandat, versi rencana, anggaran, dan semua bukti sumber.</p></div><strong className="tabular text-xl text-primary-700">{formatCurrency(lpj.total_amount)}</strong></div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt className="text-[10px] font-bold text-muted-600">MANDAT</dt><dd className="mt-1 text-xs font-semibold">{lpj.activity.mandate.number}</dd></div>
                        <div><dt className="text-[10px] font-bold text-muted-600">PAGU</dt><dd className="tabular mt-1 text-xs font-semibold">{formatCurrency(lpj.activity.budget_ceiling)}</dd></div>
                        <div><dt className="text-[10px] font-bold text-muted-600">PERIODE</dt><dd className="mt-1 text-xs font-semibold">{formatDate(lpj.activity.starts_at)}–{formatDate(lpj.activity.ends_at)}</dd></div>
                        <div><dt className="text-[10px] font-bold text-muted-600">CHECKSUM</dt><dd className="mt-1 truncate font-mono text-[10px]" title={version?.checksum}>{version?.checksum || 'Belum dibentuk'}</dd></div>
                    </dl>
                </section>

                <section id="lpj-verification" className="panel scroll-mt-24 overflow-hidden">
                    <div className="border-b border-line-200 px-5 py-4">
                        <div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-base font-bold">Verifikasi digital</h2><p className="mt-1 text-xs text-muted-600">Gate wajib berjalan berurutan dan terikat pada LPJ v{lpj.current_version}.</p></div><div className="text-right"><strong className="tabular text-lg text-primary-700">{verificationWorkflow.completed}/{verificationWorkflow.total}</strong><p className="text-[10px] font-semibold text-muted-600">{verificationWorkflow.policy_version}</p></div></div>
                        <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Progres verifikasi" aria-valuemin={0} aria-valuemax={100} aria-valuenow={verificationWorkflow.percentage}><div className="h-full rounded-full bg-primary-600 transition-[width]" style={{ width: `${verificationWorkflow.percentage}%` }} /></div>
                        {verificationWorkflow.next_stage_label && <p className="mt-3 text-[11px] font-semibold text-slate-600">Gate berikutnya: <span className="text-ink-950">{verificationWorkflow.next_stage_label}</span></p>}
                    </div>
                    <div className="grid sm:grid-cols-2 xl:grid-cols-4">
                        {verificationWorkflow.stages.map((workflowStage, index) => {
                            const item = version?.verifications.find((verification) => verification.stage === workflowStage.stage);
                            return <article key={workflowStage.stage} aria-current={workflowStage.state === 'ACTIVE' ? 'step' : undefined} className={`border-b border-line-200 p-5 sm:border-r sm:even:border-r-0 xl:border-b-0 xl:even:border-r xl:last:border-r-0 ${workflowStage.state === 'ACTIVE' ? 'bg-blue-50/60' : ''}`}>
                                <div className="flex items-center justify-between gap-3"><span className={`grid size-9 place-items-center rounded-lg ${workflowStage.state === 'COMPLETED' ? 'bg-emerald-50 text-emerald-700' : workflowStage.state === 'ACTIVE' ? 'bg-blue-100 text-primary-700' : 'bg-slate-100 text-slate-400'}`}><ClipboardCheck className="size-4" /></span><span className="text-[10px] font-bold text-slate-400">0{index + 1}</span></div>
                                <h3 className="mt-3 text-xs font-bold">{workflowStage.label}</h3>
                                <div className="mt-2"><StatusBadge status={item?.status || 'PENDING'} /></div>
                                <p className="mt-3 text-[11px] leading-5 text-muted-600">{item?.notes || 'Belum ada catatan verifikator.'}</p>
                                {item?.checklist?.length ? <p className="mt-2 text-[10px] font-semibold text-slate-500">{item.checklist.length} pemeriksaan · {item.evidence_ids?.length || 0} bukti</p> : null}
                                {item?.verifier ? <p className="mt-2 text-[10px] font-semibold text-slate-500">{item.verifier.name} · {formatDate(item.verified_at)}</p> : workflowStage.stage === 'SYSTEM' && item?.verified_at ? <p className="mt-2 text-[10px] font-semibold text-slate-500">Otomatis · {formatDate(item.verified_at)}</p> : null}
                                {workflowStage.state === 'ACTIVE' && <p className="mt-3 border-t border-blue-200 pt-3 text-[10px] font-bold text-blue-700">TAHAP AKTIF</p>}
                            </article>;
                        })}
                    </div>
                </section>

                <ApprovalDecisionPackPanel workflow={approvalWorkflow} />

                <section id="lpj-findings" className="panel scroll-mt-24 overflow-hidden">
                    <div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Temuan & tindakan korektif</h2><p className="mt-1 text-xs text-muted-600">Koreksi tidak mengubah snapshot lama; pengajuan ulang membentuk versi baru.</p></div>
                    {version?.findings.length ? <div className="divide-y divide-line-200">{version.findings.map((finding) => <article key={finding.id} className="p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3"><div><span className="text-[10px] font-bold text-danger-600">{finding.code} · {finding.severity}</span><h3 className="mt-1 text-sm font-bold">{finding.title}</h3></div><StatusBadge status={finding.status} /></div>
                        <p className="mt-3 text-xs leading-6 text-slate-600">{finding.detail}</p>
                        <dl className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2"><div><dt className="text-[10px] font-bold text-muted-600">KRITERIA</dt><dd className="mt-1 text-xs leading-5">{finding.criteria}</dd></div><div><dt className="text-[10px] font-bold text-muted-600">KONDISI</dt><dd className="mt-1 text-xs leading-5">{finding.condition}</dd></div><div><dt className="text-[10px] font-bold text-muted-600">SEBAB</dt><dd className="mt-1 text-xs leading-5">{finding.cause}</dd></div><div><dt className="text-[10px] font-bold text-muted-600">RISIKO</dt><dd className="mt-1 text-xs leading-5">{finding.risk}</dd></div><div className="sm:col-span-2"><dt className="text-[10px] font-bold text-muted-600">REKOMENDASI</dt><dd className="mt-1 text-xs leading-5">{finding.recommendation}</dd></div></dl>
                        <p className="mt-2 text-[10px] font-semibold text-muted-600">Tenggat: {formatDate(finding.due_at)}</p>
                        {finding.corrective_actions.map((action) => <div key={action.id} className="mt-4 rounded-lg border border-success-600/20 bg-emerald-50/50 p-3"><div className="flex items-center justify-between gap-3"><b className="text-xs">CAPA · {action.submitter.name}</b><StatusBadge status={action.status} /></div><p className="mt-2 text-[11px] leading-5 text-slate-600">{action.action}</p>{can.openFinding && action.status === 'SUBMITTED' && <CorrectiveActionReview finding={finding} correctiveAction={action} />}</div>)}
                        {!['RESOLVED', 'REJECTED'].includes(finding.status) && <CorrectiveActionForm finding={finding} />}
                    </article>)}</div> : <div className="p-8 text-center"><CheckCircle2 className="mx-auto size-8 text-success-600" /><h3 className="mt-3 text-sm font-bold">Tidak ada temuan aktif</h3><p className="mt-1 text-xs text-muted-600">Temuan verifikasi akan tampil di sini beserta riwayat CAPA.</p></div>}
                </section>

                <section id="lpj-dispositions" className="panel scroll-mt-24 overflow-hidden">
                    <div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Disposisi</h2><p className="mt-1 text-xs text-muted-600">Instruksi terarah pada versi LPJ aktif, dengan penerima, tenggat, dan status tindak lanjut.</p></div>
                    {version?.dispositions.length ? <div className="divide-y divide-line-200">{version.dispositions.map((disposition) => <article key={disposition.id} className="p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><span className="text-[10px] font-bold text-primary-700">{disposition.sender.name} → {disposition.recipient?.name || 'berdasarkan peran'}</span><h3 className="mt-1 text-sm font-bold">{disposition.subject}</h3></div><div className="flex gap-2"><StatusBadge status={disposition.priority} /><StatusBadge status={disposition.status} /></div></div><p className="mt-3 text-xs leading-6 text-slate-600">{disposition.instructions}</p><p className="mt-2 text-[10px] font-semibold text-muted-600">Tenggat: {formatDate(disposition.due_at, true)}</p>{disposition.response_notes && <p className="mt-3 rounded-lg bg-blue-50 p-3 text-xs leading-5 text-blue-900">Tindak lanjut: {disposition.response_notes} · {disposition.evidence_ids?.length || 0} bukti</p>}{disposition.reviewer && <p className="mt-2 text-[10px] leading-5 text-muted-600">Review {disposition.reviewer.name} · {formatDate(disposition.reviewed_at, true)} · {disposition.review_notes}</p>}<DispositionActions disposition={disposition} evidence={lpj.activity.evidence} /></article>)}</div> : <p className="p-8 text-center text-xs text-muted-600">Belum ada disposisi pada versi ini.</p>}
                </section>

                <section className="panel overflow-hidden">
                    <div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Pemeriksaan pendukung & review manusia</h2><p className="mt-1 text-xs text-muted-600">Keluaran terstruktur bersifat advisory dan tidak pernah mengubah status workflow secara otomatis.</p></div>
                    {lpj.activity.ai_reviews.length ? <div className="divide-y divide-line-200">{lpj.activity.ai_reviews.map((review) => <article className="p-5" key={review.id}><div className="flex flex-wrap items-start justify-between gap-3"><div><span className="text-[10px] font-bold text-primary-700">{review.type} · {review.model || 'model belum tersedia'}</span><h3 className="mt-1 text-sm font-bold">{review.output?.summary || 'Pemeriksaan belum menghasilkan ringkasan.'}</h3></div><div className="flex gap-2"><StatusBadge status={review.status} /><StatusBadge status={review.human_review_status} /></div></div>{review.output?.risks?.length ? <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3"><b className="text-[10px] text-amber-900">RISIKO TERDETEKSI</b><ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-amber-900">{review.output.risks.map((risk) => <li key={risk}>{risk}</li>)}</ul></div> : null}{review.output?.recommendations?.length ? <div className="mt-3"><b className="text-[10px] text-muted-600">REKOMENDASI</b><ul className="mt-2 list-disc space-y-1 pl-4 text-xs text-slate-600">{review.output.recommendations.map((item) => <li key={item}>{item}</li>)}</ul></div> : null}<p className="mt-3 text-[10px] leading-5 text-muted-600">Confidence {review.confidence ? `${Math.round(Number(review.confidence) * 100)}%` : '—'} · {review.limitations || 'Wajib ditinjau manusia.'}</p>{review.reviewer && <p className="mt-2 text-[10px] font-semibold text-slate-600">Direview {review.reviewer.name} · {formatDate(review.reviewed_at, true)}</p>}{can.reviewAi && review.status === 'REVIEW_REQUIRED' && review.human_review_status === 'PENDING' && <AiHumanReview review={review} />}</article>)}</div> : <p className="p-8 text-center text-xs text-muted-600">Belum ada pemeriksaan pendukung pada kegiatan ini.</p>}
                </section>

                <section id="lpj-approval" className="panel scroll-mt-24 overflow-hidden">
                    <div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Keputusan & jejak versi</h2></div>
                    <div className="grid lg:grid-cols-2">
                        <div className="border-b border-line-200 p-5 lg:border-r lg:border-b-0"><h3 className="text-xs font-bold">Keputusan</h3>{version?.approvals.length ? <div className="mt-3 grid gap-3">{version.approvals.map((approval) => <div key={approval.id} className="rounded-lg border border-line-200 p-3"><div className="flex items-center justify-between gap-3"><b className="text-xs">{approval.level} · {approval.decision_maker.name}</b><StatusBadge status={approval.decision} /></div><p className="mt-2 text-[11px] text-muted-600">{approval.notes || 'Tanpa catatan'} · {formatDate(approval.decided_at)}</p>{approval.authority_reference && <p className="mt-2 [overflow-wrap:anywhere] border-t border-slate-100 pt-2 font-mono text-[9px] text-slate-500">{approval.authority_policy_version} · {approval.authority_reference} · pack {approval.decision_pack_checksum?.slice(0, 12)}</p>}</div>)}</div> : <p className="mt-3 text-xs text-muted-600">Belum ada keputusan.</p>}</div>
                        <div className="p-5"><h3 className="text-xs font-bold">Versi immutable</h3><div className="mt-3 grid gap-2">{lpj.versions.map((item) => <div key={item.id} className="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span className="text-xs font-semibold">Versi {item.version} · {formatDate(item.submitted_at)}</span><div className="flex items-center gap-2"><StatusBadge status={item.status} />{item.is_immutable && <FileLock2 aria-label="Immutable" className="size-4 text-primary-700" />}</div></div>)}</div></div>
                    </div>
                </section>
            </div>

            <aside className="grid content-start gap-5">
                <section className="panel p-5"><div className="flex items-end justify-between gap-3"><div><h2 className="text-sm font-bold">Gate kelengkapan</h2><p className="mt-1 text-[10px] text-muted-600">{composer.policy_version}</p></div><strong className="tabular text-2xl text-primary-700">{composer.percentage}%</strong></div><ul className="mt-4 grid gap-2">{composer.modules.map((module) => <li key={module.key} className="flex min-h-8 items-center justify-between gap-3 text-xs"><span className="text-slate-600">{module.label}</span><span className={module.ready ? 'font-bold text-emerald-700' : 'font-bold text-red-700'}>{module.ready ? 'Siap' : `${module.percentage}%`}</span></li>)}</ul></section>

                {(can.submit || can.verify || can.openFinding || can.approve || can.pbVerify || can.archive || can.requestAi) && <section aria-label="Aksi workflow" className="grid gap-3">
                    {canEditComposer && <ActionCard title="Ajukan snapshot" description="Bekukan sumber, narasi, hasil pre-check, dan metadata sebagai versi immutable."><form onSubmit={(event) => { event.preventDefault(); submitForm.post(`/lpjs/${lpj.id}/submissions`, { preserveScroll: true }); }} className="grid gap-3"><div><label className="label" htmlFor="submission-notes">Catatan pengajuan</label><textarea id="submission-notes" className="field min-h-20" value={submitForm.data.notes} onChange={(event) => submitForm.setData('notes', event.target.value)} /><FormError message={submitForm.errors.notes || Object.values(submitForm.errors)[0]} /></div>{!composer.ready && <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] leading-5 text-amber-900">Selesaikan {Object.keys(composer.errors).length} blocker pada LPJ Composer sebelum submit.</p>}<button className="btn-primary" disabled={submitForm.processing || !composer.ready}><Send className="size-4" />{submitForm.processing ? 'Mengajukan…' : `Ajukan LPJ v${lpj.current_version + 1}`}</button></form></ActionCard>}

                    {can.verify && ['IN_VERIFICATION', 'CLARIFICATION_REQUIRED', 'FINDING_OPEN'].includes(lpj.status) && verificationWorkflow.can_verify_next_stage && <VerificationAction key={verificationWorkflow.next_stage} lpjId={lpj.id} evidence={lpj.activity.evidence} workflow={verificationWorkflow} />}
                    {can.verify && ['IN_VERIFICATION', 'CLARIFICATION_REQUIRED', 'FINDING_OPEN'].includes(lpj.status) && !verificationWorkflow.can_verify_next_stage && <section className="rounded-xl border border-line-200 bg-slate-50 p-4" role="status"><p className="text-xs font-bold text-ink-950">Verifikasi belum dapat Anda lanjutkan</p><p className="mt-1 text-[11px] leading-5 text-muted-600">{verificationWorkflow.blocked_reason}</p></section>}

                    {can.openFinding && version && ['IN_VERIFICATION', 'CLARIFICATION_REQUIRED', 'FINDING_OPEN'].includes(lpj.status) && <ActionCard title="Buka temuan" description="Dokumentasikan kriteria, kondisi, sebab, risiko, dan rekomendasi secara lengkap."><form onSubmit={(event) => { event.preventDefault(); findingForm.post(`/lpjs/${lpj.id}/findings`, { preserveScroll: true, onSuccess: () => findingForm.reset() }); }} className="grid gap-3"><div className="grid gap-3 sm:grid-cols-2"><div><label className="label" htmlFor="severity">Dampak</label><select id="severity" className="field" value={findingForm.data.severity} onChange={(event) => findingForm.setData('severity', event.target.value)}><option>LOW</option><option>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></div><div><label className="label" htmlFor="finding-owner">Pemilik tindak lanjut</label><select id="finding-owner" className="field" value={findingForm.data.owner_id} onChange={(event) => findingForm.setData('owner_id', event.target.value)}><option value="">Owner LPJ</option>{dispositionUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></div></div><div><label className="label" htmlFor="finding-title">Judul</label><input id="finding-title" className="field" value={findingForm.data.title} onChange={(event) => findingForm.setData('title', event.target.value)} required /><FormError message={findingForm.errors.title} /></div>{([['criteria', 'Kriteria'], ['condition', 'Kondisi'], ['cause', 'Sebab'], ['risk', 'Risiko'], ['recommendation', 'Rekomendasi'], ['detail', 'Rincian pendukung']] as const).map(([field, label]) => <div key={field}><label className="label" htmlFor={`finding-${field}`}>{label}</label><textarea id={`finding-${field}`} className="field min-h-20" value={findingForm.data[field]} onChange={(event) => findingForm.setData(field, event.target.value)} required /><FormError message={findingForm.errors[field]} /></div>)}<div><label className="label" htmlFor="due-at">Tenggat</label><input id="due-at" type="date" className="field" value={findingForm.data.due_at} onChange={(event) => findingForm.setData('due_at', event.target.value)} /></div><button className="btn-danger" disabled={findingForm.processing}><AlertTriangle className="size-4" />Buka temuan</button></form></ActionCard>}

                    {can.createDisposition && version && <ActionCard title="Buat disposisi" description="Kirim instruksi terarah yang terikat ke versi LPJ aktif."><form onSubmit={(event) => { event.preventDefault(); dispositionForm.post(`/lpjs/${lpj.id}/dispositions`, { preserveScroll: true, onSuccess: () => dispositionForm.reset() }); }} className="grid gap-3"><div><label className="label" htmlFor="disposition-user">Penerima</label><select id="disposition-user" className="field" value={dispositionForm.data.to_user_id} onChange={(event) => dispositionForm.setData('to_user_id', event.target.value)} required><option value="">Pilih penerima</option>{dispositionUsers.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></div><div><label className="label" htmlFor="disposition-subject">Subjek</label><input id="disposition-subject" required className="field" value={dispositionForm.data.subject} onChange={(event) => dispositionForm.setData('subject', event.target.value)} /></div><div><label className="label" htmlFor="disposition-instructions">Instruksi</label><textarea id="disposition-instructions" required className="field min-h-24" value={dispositionForm.data.instructions} onChange={(event) => dispositionForm.setData('instructions', event.target.value)} /></div><div className="grid gap-3 sm:grid-cols-2"><div><label className="label" htmlFor="disposition-priority">Prioritas</label><select id="disposition-priority" className="field" value={dispositionForm.data.priority} onChange={(event) => dispositionForm.setData('priority', event.target.value)}><option>LOW</option><option>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></div><div><label className="label" htmlFor="disposition-due">Tenggat</label><input id="disposition-due" type="datetime-local" className="field" value={dispositionForm.data.due_at} onChange={(event) => dispositionForm.setData('due_at', event.target.value)} /></div></div><button className="btn-primary" disabled={dispositionForm.processing}><Forward className="size-4" />Kirim disposisi</button></form></ActionCard>}

                    {((can.approve && lpj.status === 'READY_FOR_APPROVAL') || (can.pbVerify && lpj.status === 'APPROVED')) && <ActionCard title={lpj.status === 'APPROVED' ? 'Verifikasi PB' : 'Keputusan approver'} description="Keputusan ditandatangani, terikat decision pack, dan dipisahkan dari pembuat LPJ."><form onSubmit={(event) => { event.preventDefault(); approvalForm.post(`/lpjs/${lpj.id}/approvals`, { preserveScroll: true }); }} className="grid gap-3"><input type="hidden" value={approvalForm.data.level} />{Object.keys(approvalForm.errors).length > 0 && <div ref={approvalErrorRef} role="alert" tabIndex={-1} className="rounded-lg border border-red-200 bg-red-50 p-3 text-[11px] leading-5 text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600"><b>Keputusan belum dapat disimpan.</b><p>{Object.values(approvalForm.errors)[0]}</p></div>}<div className="rounded-lg border border-blue-100 bg-blue-50 p-3"><p className="text-[10px] font-bold text-blue-900">OTORITAS</p><p className="mt-1 text-[11px] font-semibold text-blue-950">{approvalWorkflow.authority?.reference || 'Tidak ada authority aktif'}</p><p className="mt-1 text-[10px] text-blue-800">Nilai diuji {formatCurrency(approvalWorkflow.authority?.evaluated_amount || '0')} · {approvalWorkflow.authority?.source || 'BLOCKED'}</p></div>{approvalWorkflow.blockers.length > 0 && <ul className="grid gap-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[11px] leading-5 text-amber-900">{approvalWorkflow.blockers.map((blocker) => <li key={blocker}>• {blocker}</li>)}</ul>}<div><label className="label" htmlFor="approval-decision">Keputusan</label><select id="approval-decision" className="field" value={approvalForm.data.decision} onChange={(event) => approvalForm.setData('decision', event.target.value)}><option value="APPROVED" disabled={!approvalWorkflow.can_approve}>Setujui{!approvalWorkflow.can_approve ? ' · gate belum lengkap' : ''}</option><option value="RETURNED">Kembalikan untuk perbaikan</option><option value="CLARIFICATION_REQUIRED">Minta klarifikasi</option><option value="REJECTED">Tolak</option></select></div><div><label className="label" htmlFor="approval-notes">Catatan {approvalForm.data.decision === 'APPROVED' ? '(opsional)' : '(wajib)'}</label><textarea id="approval-notes" className="field min-h-20" value={approvalForm.data.notes} onChange={(event) => approvalForm.setData('notes', event.target.value)} /><FormError message={approvalForm.errors.notes} /></div><button className="btn-primary" disabled={approvalForm.processing || !approvalWorkflow.can_decide || (approvalForm.data.decision === 'APPROVED' && !approvalWorkflow.can_approve)}><CheckCircle2 className="size-4" />{approvalForm.processing ? 'Menyimpan…' : 'Simpan keputusan'}</button></form></ActionCard>}

                    {can.archive && lpj.status === 'PB_VERIFIED' && <ActionCard title="Arsipkan paket final" description="Kunci Verified Pack, manifest bukti, keputusan, dan audit trail dengan kebijakan retensi berversi."><form onSubmit={(event) => { event.preventDefault(); archiveForm.post(`/lpjs/${lpj.id}/archives`, { preserveScroll: true }); }} className="grid gap-3"><p className="rounded-lg border border-blue-100 bg-blue-50 p-3 text-[11px] leading-5 text-blue-900">Retensi ditentukan otomatis oleh kebijakan organisasi dan tidak dapat diubah dari layar ini.</p><FormError message={archiveForm.errors.archive || archiveForm.errors.verified_pack} /><button className="btn-primary" disabled={archiveForm.processing}><Archive className="size-4" />{archiveForm.processing ? 'Mengarsipkan…' : 'Arsipkan paket final'}</button></form></ActionCard>}

                    {can.requestAi && <ActionCard title="Pemeriksaan pendukung AI" description="Rekomendasi tidak mengubah status dan wajib ditinjau manusia."><form onSubmit={(event) => { event.preventDefault(); aiForm.post('/ai-reviews', { preserveScroll: true }); }} className="grid gap-3"><div><label className="label" htmlFor="ai-type">Jenis pemeriksaan</label><select id="ai-type" className="field" value={aiForm.data.type} onChange={(event) => aiForm.setData('type', event.target.value)}><option value="PRECHECK">Kelengkapan awal</option><option value="RISK_REVIEW">Risiko dan anomali</option><option value="NARRATIVE">Konsistensi narasi</option></select></div><p className="text-[11px] leading-5 text-muted-600">Hasil AI bersifat advisory, dapat keliru, dan tidak menggantikan verifikator.</p><button className="btn-secondary" disabled={aiForm.processing}><Bot className="size-4" />Jalankan pemeriksaan</button></form></ActionCard>}
                </section>}

                {version?.verified_pack && <VerifiedPackPanel verifiedPack={version.verified_pack} />}

                {version?.archive && <section className="rounded-xl border border-success-600/25 bg-emerald-50 p-5"><CheckCircle2 className="size-6 text-success-600" /><h2 className="mt-3 text-sm font-bold">Arsip final tersedia</h2><p className="mt-1 text-xs font-semibold">{version.archive.archive_number}</p><p className="mt-1 text-[10px] text-slate-600">{version.archive.retention_policy_version} · retensi sampai {formatDate(version.archive.retention_until)}</p><code className="mt-2 block break-all text-[9px] text-slate-500">{version.archive.checksum}</code><a href={`/archives/${version.archive.id}/download`} className="btn-secondary mt-4 w-full"><Archive className="size-4" />Unduh paket arsip</a></section>}
            </aside>
        </div>
    </>;
}
