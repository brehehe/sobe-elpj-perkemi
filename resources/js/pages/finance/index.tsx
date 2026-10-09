import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { PaginationLink } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, BadgeCheck, CheckCircle2, CircleDollarSign, LoaderCircle, Scale, Send, XCircle } from 'lucide-react';
import type { FormEvent } from 'react';

type BudgetLine = {
    id: string;
    budget_id: string;
    budget_code: string;
    account_code: string;
    description: string;
    subtotal: string;
};

type Exception = {
    id: string;
    category: string;
    severity: string;
    status: string;
    detail: string;
    resolution_type?: string;
    resolution?: string;
    decision?: string;
    decision_notes?: string;
    authority_policy_version?: string;
    due_at?: string;
    requested_at?: string;
    decided_at?: string;
    owner?: { name: string };
    requester?: { name: string };
    decision_maker?: { name: string };
};

type Transaction = {
    id: string;
    reference_number?: string;
    description: string;
    transaction_date: string;
    amount: string;
    account_code?: string;
    reconciliation_status: string;
    activity: { id: string; budget_id: string; code: string; title: string };
    budget_line?: BudgetLine;
    reconciliation?: { variance: string; checks: Record<string, boolean>; exceptions: Exception[] };
};

type FinanceProps = {
    transactions: { data: Transaction[]; links: PaginationLink[] };
    budgetLines: BudgetLine[];
    metrics: { total: string | number; matched: number; exceptions: number; openExceptions: number };
    canReconcile: boolean;
    canResolveException: boolean;
    canApproveException: boolean;
};

function ReconcileForm({ transaction, lines }: { transaction: Transaction; lines: BudgetLine[] }) {
    const candidates = lines.filter((line) => line.budget_id === transaction.activity.budget_id);
    const form = useForm({ budget_line_id: transaction.budget_line?.id || '', notes: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/transactions/${transaction.id}/reconciliations`, { preserveScroll: true });
    };

    return <form onSubmit={submit} className="grid gap-2 sm:grid-cols-[minmax(260px,1fr)_1fr_auto]">
        <select aria-label={`Baris RAB untuk ${transaction.reference_number || transaction.id}`} required className="field" value={form.data.budget_line_id} onChange={(event) => form.setData('budget_line_id', event.target.value)}>
            <option value="">Pilih baris RAB</option>
            {candidates.map((line) => <option key={line.id} value={line.id}>{line.account_code} · {line.description} · {formatCurrency(line.subtotal)}</option>)}
        </select>
        <input aria-label="Catatan rekonsiliasi" className="field" placeholder="Catatan pemeriksaan (opsional)" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
        <button disabled={form.processing || candidates.length === 0} className="btn-secondary">
            {form.processing ? <LoaderCircle className="size-4 animate-spin" aria-hidden="true" /> : <Scale className="size-4" aria-hidden="true" />}Rekonsiliasi
        </button>
        {form.errors.budget_line_id && <p className="error sm:col-span-3" role="alert">{form.errors.budget_line_id}</p>}
    </form>;
}

function ResolveForm({ exception }: { exception: Exception }) {
    const form = useForm({ resolution_type: 'CLARIFIED', resolution: '' });
    const errors = form.errors as typeof form.errors & { status?: string };
    const requestsApproval = form.data.resolution_type === 'APPROVED_EXCEPTION';

    return <form className="mt-4 grid gap-3 rounded-lg border border-white/80 bg-white/70 p-3 sm:grid-cols-[190px_1fr_auto]" onSubmit={(event) => {
        event.preventDefault();
        form.patch(`/reconciliation-exceptions/${exception.id}`, { preserveScroll: true });
    }}>
        <div>
            <label className="label" htmlFor={`resolution-type-${exception.id}`}>Jalur penyelesaian</label>
            <select id={`resolution-type-${exception.id}`} className="field bg-white" value={form.data.resolution_type} onChange={(event) => form.setData('resolution_type', event.target.value)}>
                <option value="MAPPED">Pemetaan diperbaiki</option>
                <option value="SOURCE_CORRECTED">Sumber dikoreksi</option>
                <option value="CLARIFIED">Diklarifikasi</option>
                <option value="APPROVED_EXCEPTION">Ajukan pengecualian resmi</option>
            </select>
        </div>
        <div>
            <label className="label" htmlFor={`resolution-${exception.id}`}>{requestsApproval ? 'Dasar permintaan' : 'Uraian penyelesaian'}</label>
            <input id={`resolution-${exception.id}`} required className="field bg-white" placeholder={requestsApproval ? 'Jelaskan alasan, dampak, dan bukti pendukung' : 'Jelaskan bukti dan hasil penyelesaian'} value={form.data.resolution} onChange={(event) => form.setData('resolution', event.target.value)} />
            {form.errors.resolution && <p className="error" role="alert">{form.errors.resolution}</p>}
        </div>
        <button className="btn-primary self-end" disabled={form.processing}>
            {form.processing ? <LoaderCircle className="size-4 animate-spin" aria-hidden="true" /> : requestsApproval ? <Send className="size-4" aria-hidden="true" /> : <CheckCircle2 className="size-4" aria-hidden="true" />}
            {requestsApproval ? 'Kirim permintaan' : 'Selesaikan exception'}
        </button>
        {errors.status && <p className="error sm:col-span-3" role="alert">{errors.status}</p>}
    </form>;
}

function DecisionForm({ exception }: { exception: Exception }) {
    const form = useForm({ decision: 'APPROVED', notes: '' });
    const errors = form.errors as typeof form.errors & { status?: string; approver?: string };

    return <form className="mt-4 grid gap-3 rounded-lg border border-amber-200 bg-white p-3 sm:grid-cols-[180px_1fr_auto]" onSubmit={(event) => {
        event.preventDefault();
        form.post(`/reconciliation-exceptions/${exception.id}/decisions`, { preserveScroll: true });
    }}>
        <div>
            <label className="label" htmlFor={`decision-${exception.id}`}>Keputusan</label>
            <select id={`decision-${exception.id}`} className="field" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}>
                <option value="APPROVED">Setujui pengecualian</option>
                <option value="REJECTED">Tolak dan kembalikan</option>
            </select>
        </div>
        <div>
            <label className="label" htmlFor={`decision-notes-${exception.id}`}>Catatan keputusan</label>
            <input id={`decision-notes-${exception.id}`} required className="field" placeholder="Tuliskan dasar kewenangan dan pertimbangan" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
            {form.errors.notes && <p className="error" role="alert">{form.errors.notes}</p>}
        </div>
        <button className="btn-primary self-end" disabled={form.processing}>
            {form.processing ? <LoaderCircle className="size-4 animate-spin" aria-hidden="true" /> : form.data.decision === 'APPROVED' ? <BadgeCheck className="size-4" aria-hidden="true" /> : <XCircle className="size-4" aria-hidden="true" />}
            Catat keputusan
        </button>
        {(errors.status || errors.approver) && <p className="error sm:col-span-3" role="alert">{errors.status || errors.approver}</p>}
    </form>;
}

function ExceptionPanel({ exception, canResolve, canApprove }: { exception: Exception; canResolve: boolean; canApprove: boolean }) {
    const rejected = exception.status === 'REJECTED';
    const pending = exception.status === 'PENDING_APPROVAL';
    const approved = exception.status === 'APPROVED_EXCEPTION';
    const tone = rejected ? 'border-red-200 bg-red-50' : approved ? 'border-violet-200 bg-violet-50' : 'border-amber-200 bg-amber-50';
    const textTone = rejected ? 'text-red-950' : approved ? 'text-violet-950' : 'text-amber-950';

    return <article className={`mt-3 rounded-xl border p-4 ${tone}`}>
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div className="flex flex-wrap items-center gap-2"><StatusBadge status={exception.status} /><b className={`text-xs ${textTone}`}>{exception.category} · {exception.severity}</b></div>
                <p className={`mt-2 text-xs leading-5 ${textTone}`}>{exception.detail}</p>
            </div>
            {exception.due_at && <span className="text-[10px] font-semibold text-slate-600">Jatuh tempo {formatDate(exception.due_at)}</span>}
        </div>

        {exception.resolution && <div className="mt-3 grid gap-1 border-t border-current/10 pt-3 text-[11px] leading-5 text-slate-700">
            <b>{exception.resolution_type === 'APPROVED_EXCEPTION' ? 'Permintaan pengecualian' : 'Penyelesaian'}</b>
            <p>{exception.resolution}</p>
            {exception.requester && <span className="text-[10px] text-slate-600">Diajukan {exception.requester.name}{exception.requested_at ? ` · ${formatDate(exception.requested_at, true)}` : ''}</span>}
        </div>}

        {exception.decision && <div className="mt-3 flex items-start gap-3 rounded-lg border border-current/10 bg-white/70 p-3">
            {exception.decision === 'APPROVED' ? <BadgeCheck className="mt-0.5 size-4 shrink-0 text-violet-700" aria-hidden="true" /> : <XCircle className="mt-0.5 size-4 shrink-0 text-red-700" aria-hidden="true" />}
            <div className="text-[11px] leading-5 text-slate-700"><b>{exception.decision === 'APPROVED' ? 'Pengecualian disetujui' : 'Permintaan ditolak'}</b><p>{exception.decision_notes}</p><span className="text-[10px] text-slate-600">{exception.decision_maker?.name ?? 'Pejabat berwenang'}{exception.decided_at ? ` · ${formatDate(exception.decided_at, true)}` : ''}{exception.authority_policy_version ? ` · ${exception.authority_policy_version}` : ''}</span></div>
        </div>}

        {canResolve && ['OPEN', 'IN_PROGRESS', 'REJECTED'].includes(exception.status) && <ResolveForm exception={exception} />}
        {canApprove && pending && <DecisionForm exception={exception} />}
        {pending && !canApprove && <p className="mt-3 rounded-lg border border-amber-200 bg-white/70 p-3 text-[11px] leading-5 text-amber-950">Menunggu keputusan pengguna dengan kewenangan menyetujui pengecualian. Gate LPJ tetap tertutup sampai keputusan dicatat.</p>}
    </article>;
}

export default function FinanceIndex({ transactions, budgetLines, metrics, canReconcile, canResolveException, canApproveException }: FinanceProps) {
    return <>
        <Head title="Keuangan & Rekonsiliasi" />
        <PageHeader eyebrow="Pelaksanaan → Keuangan → Rekonsiliasi" title="Keuangan dan rekonsiliasi" description="Cocokkan transaksi dengan baris RAB, referensi, akun, dan bukti kegiatan. Selisih menjadi exception yang wajib memiliki resolusi atau keputusan pengecualian resmi." />
        <section className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Metric icon={CircleDollarSign} label="Nilai transaksi" value={formatCurrency(metrics.total)} />
            <Metric icon={CheckCircle2} label="Cocok/selesai" value={String(metrics.matched)} tone="emerald" />
            <Metric icon={AlertTriangle} label="Transaksi exception" value={String(metrics.exceptions)} tone="amber" />
            <Metric icon={Scale} label="Exception belum final" value={String(metrics.openExceptions)} tone="red" />
        </section>
        <section className="panel mt-6 overflow-hidden">
            <div className="overflow-x-auto" role="region" aria-label="Daftar transaksi yang dapat digulir" tabIndex={0}>
                <table className="w-full min-w-[1050px] text-left text-xs">
                    <thead className="bg-slate-50 text-slate-600"><tr><th className="px-4 py-3">Transaksi</th><th className="px-4 py-3">Activity-ID</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Akun</th><th className="px-4 py-3 text-right">Nilai</th><th className="px-4 py-3">Status</th></tr></thead>
                    <tbody>
                        {transactions.data.map((transaction) => <tr key={transaction.id} className="border-t border-line-200 align-top"><td className="px-4 py-4"><b className="block text-slate-800">{transaction.reference_number || 'Tanpa referensi'}</b><span className="mt-1 block max-w-xs text-muted-600">{transaction.description}</span></td><td className="px-4 py-4"><b className="text-primary-700">{transaction.activity.code}</b><span className="block text-muted-600">{transaction.activity.title}</span></td><td className="px-4 py-4 whitespace-nowrap">{formatDate(transaction.transaction_date)}</td><td className="px-4 py-4 tabular">{transaction.account_code || '—'}</td><td className="px-4 py-4 text-right font-bold tabular">{formatCurrency(transaction.amount)}</td><td className="px-4 py-4"><StatusBadge status={transaction.reconciliation_status} /></td></tr>)}
                        {transactions.data.length === 0 && <tr><td colSpan={6} className="p-10 text-center text-muted-600">Belum ada transaksi.</td></tr>}
                    </tbody>
                </table>
            </div>
            {transactions.data.map((transaction) => <div key={`actions-${transaction.id}`} className="border-t border-line-200 bg-slate-50/60 px-4 py-4">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2"><b className="text-xs text-slate-700">{transaction.reference_number || transaction.id} · pemeriksaan terhadap RAB</b>{transaction.reconciliation && <span className="text-xs text-muted-600">Selisih {formatCurrency(transaction.reconciliation.variance)}</span>}</div>
                {canReconcile && <ReconcileForm transaction={transaction} lines={budgetLines} />}
                {transaction.reconciliation?.exceptions.map((exception) => <ExceptionPanel key={exception.id} exception={exception} canResolve={canResolveException} canApprove={canApproveException} />)}
            </div>)}
            <Pagination links={transactions.links} />
        </section>
    </>;
}

function Metric({ icon: Icon, label, value, tone = 'blue' }: { icon: typeof CircleDollarSign; label: string; value: string; tone?: 'blue' | 'emerald' | 'amber' | 'red' }) {
    const tones = { blue: 'bg-blue-50 text-primary-700', emerald: 'bg-emerald-50 text-emerald-700', amber: 'bg-amber-50 text-amber-700', red: 'bg-red-50 text-danger-600' };

    return <article className="panel flex items-center gap-4 p-4"><span className={`grid size-10 place-items-center rounded-lg ${tones[tone]}`}><Icon className="size-5" aria-hidden="true" /></span><div><span className="text-xs text-muted-600">{label}</span><b className="block text-xl tabular text-ink-950">{value}</b></div></article>;
}
