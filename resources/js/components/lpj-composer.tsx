import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import {
    BookOpenCheck,
    CheckCircle2,
    CircleAlert,
    ExternalLink,
    Eye,
    FileText,
    Printer,
    Save,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

type ComposerCheck = {
    key: string;
    label: string;
    ready: boolean;
    result: 'PASS' | 'WARNING' | 'BLOCKED';
    detail: string;
    source_reference: string;
};

type ComposerModule = {
    key: string;
    label: string;
    description: string;
    percentage: number;
    ready: boolean;
    source_count: number;
    source_reference: string;
    missing: string[];
};

export type LpjComposerReadiness = {
    ready: boolean;
    percentage: number;
    policy_version: string;
    template: { key: string; label: string };
    checks: ComposerCheck[];
    modules: ComposerModule[];
    errors: Record<string, string>;
    warnings: Record<string, string>;
};

export type LpjComposerDraft = {
    id: string;
    status: string;
    executive_summary?: string | null;
    implementation_narrative?: string | null;
    outcome_narrative?: string | null;
    financial_narrative?: string | null;
    constraints?: string | null;
    follow_up_plan?: string | null;
    draft_revision: number;
    draft_updated_at?: string | null;
    draft_updater?: { name: string } | null;
};

type NarrativeField = Exclude<keyof LpjComposerDraft, 'id' | 'status' | 'draft_revision' | 'draft_updated_at' | 'draft_updater'>;

const narrativeFields: { key: NarrativeField; label: string; help: string; placeholder: string }[] = [
    {
        key: 'executive_summary',
        label: 'Ringkasan eksekutif',
        help: 'Jelaskan tujuan, pelaksanaan, capaian, dan kesimpulan utama secara ringkas.',
        placeholder: 'Ringkas konteks kegiatan dan hasil utamanya…',
    },
    {
        key: 'implementation_narrative',
        label: 'Narasi pelaksanaan',
        help: 'Berikan konteks yang tidak tersedia pada agenda dan catatan pelaksanaan otomatis.',
        placeholder: 'Jelaskan dinamika pelaksanaan yang relevan…',
    },
    {
        key: 'outcome_narrative',
        label: 'Narasi hasil dan outcome',
        help: 'Interpretasikan output, outcome, dan KPI yang sudah dihitung dari evaluasi.',
        placeholder: 'Jelaskan arti capaian dan dampak awal kegiatan…',
    },
    {
        key: 'financial_narrative',
        label: 'Narasi realisasi keuangan',
        help: 'Jelaskan variance atau konteks transaksi tanpa mengetik ulang angka sumber.',
        placeholder: 'Jelaskan realisasi, efisiensi, atau variance penting…',
    },
    {
        key: 'constraints',
        label: 'Kendala dan penanganan',
        help: 'Tuliskan kendala, keputusan penanganan, atau nyatakan bila tidak ada kendala material.',
        placeholder: 'Jelaskan kendala dan cara penanganannya…',
    },
    {
        key: 'follow_up_plan',
        label: 'Rencana tindak lanjut',
        help: 'Nyatakan tindak lanjut yang dapat ditindaklanjuti setelah kegiatan.',
        placeholder: 'Jelaskan aksi berikut, pemilik, atau arah perbaikan…',
    },
];

function FieldError({ message }: { message?: string }) {
    return message ? <p className="error" role="alert">{message}</p> : null;
}

function resultTone(result: ComposerCheck['result']): string {
    if (result === 'PASS') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (result === 'WARNING') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    return 'border-red-200 bg-red-50 text-red-800';
}

export function LpjComposer({
    lpj,
    activityId,
    activityCode,
    composer,
    canEdit,
}: {
    lpj: LpjComposerDraft;
    activityId: string;
    activityCode: string;
    composer: LpjComposerReadiness;
    canEdit: boolean;
}) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const [saveState, setSaveState] = useState<'idle' | 'unsaved' | 'saving' | 'saved' | 'error'>('idle');
    const form = useForm({
        executive_summary: lpj.executive_summary ?? '',
        implementation_narrative: lpj.implementation_narrative ?? '',
        outcome_narrative: lpj.outcome_narrative ?? '',
        financial_narrative: lpj.financial_narrative ?? '',
        constraints: lpj.constraints ?? '',
        follow_up_plan: lpj.follow_up_plan ?? '',
        draft_revision: lpj.draft_revision,
    });
    const errors = form.errors as Record<string, string>;

    const persistDraft = () => {
        setSaveState('saving');
        form.patch(`/lpjs/${lpj.id}/draft`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const refreshedLpj = page.props.lpj as unknown as LpjComposerDraft;
                const savedData = { ...form.data, draft_revision: refreshedLpj.draft_revision };
                form.setData(savedData);
                form.setDefaults(savedData);
                setSaveState('saved');
            },
            onError: () => setSaveState('error'),
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        persistDraft();
    };

    useEffect(() => {
        if (!canEdit || !form.isDirty || form.processing) {
            return;
        }

        setSaveState('unsaved');
        const timeoutId = window.setTimeout(persistDraft, 1500);

        return () => window.clearTimeout(timeoutId);
    }, [
        canEdit,
        form.data.executive_summary,
        form.data.implementation_narrative,
        form.data.outcome_narrative,
        form.data.financial_narrative,
        form.data.constraints,
        form.data.follow_up_plan,
        form.isDirty,
        form.processing,
    ]);

    useEffect(() => {
        if (!form.isDirty) {
            return;
        }

        const warnBeforeLeaving = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', warnBeforeLeaving);

        return () => window.removeEventListener('beforeunload', warnBeforeLeaving);
    }, [form.isDirty]);

    return <section id="lpj-composer" aria-labelledby="lpj-composer-title" className="panel overflow-hidden">
        <div className="border-b border-line-200 p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="max-w-2xl">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-primary-700">{composer.template.label}</span>
                        <span className="text-[10px] font-semibold text-muted-600">Aturan {composer.policy_version}</span>
                    </div>
                    <h2 id="lpj-composer-title" className="mt-3 text-lg font-bold text-ink-950">LPJ Composer</h2>
                    <p className="mt-1 text-xs leading-5 text-muted-600">Data faktual diambil otomatis dari source record. Lengkapi hanya narasi yang memerlukan konteks manusia.</p>
                </div>
                <div className="flex flex-wrap gap-2 print:hidden">
                    <button type="button" className="btn-secondary" onClick={() => setPreviewOpen((open) => !open)} aria-expanded={previewOpen} aria-controls="composer-preview">
                        <Eye className="size-4" />{previewOpen ? 'Tutup preview' : 'Preview draft'}
                    </button>
                    <button type="button" className="btn-secondary" onClick={() => window.print()}>
                        <Printer className="size-4" />Cetak / ekspor
                    </button>
                </div>
            </div>

            <div className="mt-5 grid gap-4 rounded-xl border border-line-200 bg-slate-50 p-4 sm:grid-cols-[120px_1fr] sm:items-center">
                <div>
                    <strong className="tabular text-3xl text-primary-700">{composer.percentage}%</strong>
                    <span className="mt-1 block text-[10px] font-bold uppercase tracking-wide text-muted-600">Kelengkapan</span>
                </div>
                <div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-white" role="progressbar" aria-label="Kelengkapan LPJ" aria-valuemin={0} aria-valuemax={100} aria-valuenow={composer.percentage}>
                        <div className={`h-full rounded-full ${composer.ready ? 'bg-success-600' : 'bg-primary-600'}`} style={{ width: `${composer.percentage}%` }} />
                    </div>
                    <p className="mt-2 text-[11px] leading-5 text-muted-600" aria-live="polite">
                        {composer.ready ? 'Semua blocker telah selesai. Draft siap diajukan sebagai snapshot baru.' : `${Object.keys(composer.errors).length} blocker masih perlu diselesaikan sebelum submit.`}
                    </p>
                </div>
            </div>
        </div>

        <div className="grid gap-3 border-b border-line-200 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
            {composer.modules.map((module) => <article key={module.key} className="rounded-xl border border-line-200 bg-white p-4">
                <div className="flex items-start justify-between gap-3">
                    <span className={`grid size-9 shrink-0 place-items-center rounded-lg ${module.ready ? 'bg-emerald-50 text-success-600' : 'bg-amber-50 text-amber-700'}`}>
                        {module.ready ? <CheckCircle2 className="size-4" aria-hidden="true" /> : <CircleAlert className="size-4" aria-hidden="true" />}
                    </span>
                    <span className={`rounded-full px-2 py-1 text-[10px] font-bold ${module.ready ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}`}>{module.percentage}%</span>
                </div>
                <h3 className="mt-3 text-xs font-bold text-ink-950">{module.label}</h3>
                <p className="mt-1 min-h-10 text-[11px] leading-5 text-muted-600">{module.description}</p>
                <div className="mt-3 border-t border-slate-100 pt-3">
                    <span className="block text-[10px] font-semibold text-slate-500">{module.source_count} source · {module.source_reference}</span>
                    {module.missing.length > 0 && <p className="mt-2 text-[10px] leading-4 text-red-700">{module.missing[0]}</p>}
                    <Link href={`/activities/${activityId}`} className="mt-2 inline-flex min-h-7 items-center gap-1 text-[10px] font-bold text-primary-700 hover:underline">
                        Lihat sumber <ExternalLink className="size-3" aria-hidden="true" />
                    </Link>
                </div>
            </article>)}
        </div>

        <form onSubmit={submit} className="p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-bold text-ink-950">Narasi kontekstual</h3>
                    <p className="mt-1 text-[11px] leading-5 text-muted-600">Semua kolom boleh disimpan bertahap, tetapi wajib lengkap sebelum submit.</p>
                </div>
                <div className="text-right text-[10px] font-semibold text-muted-600">
                    <span className="block">Revisi draft {lpj.draft_revision}{lpj.draft_updated_at ? ` · ${lpj.draft_updater?.name ?? 'Pengguna'} · ${formatDate(lpj.draft_updated_at, true)}` : ''}</span>
                    {canEdit && <span className={`mt-1 block ${saveState === 'error' ? 'text-red-700' : saveState === 'saved' ? 'text-emerald-700' : 'text-slate-600'}`} role="status" aria-live="polite">
                        {saveState === 'saving' ? 'Menyimpan otomatis…' : saveState === 'unsaved' ? 'Perubahan belum disimpan · autosave 1,5 detik' : saveState === 'saved' ? 'Semua perubahan tersimpan' : saveState === 'error' ? 'Autosave gagal · gunakan tombol Simpan draft' : 'Autosave aktif'}
                    </span>}
                </div>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-2">
                {narrativeFields.map((field) => <div key={field.key}>
                    <div className="flex items-center justify-between gap-3">
                        <label className="label" htmlFor={`composer-${field.key}`}>{field.label}</label>
                        <span className="tabular text-[9px] text-slate-500">{form.data[field.key].length}/5000</span>
                    </div>
                    <textarea
                        id={`composer-${field.key}`}
                        className="field min-h-32 resize-y"
                        value={form.data[field.key]}
                        onChange={(event) => form.setData(field.key, event.target.value)}
                        placeholder={field.placeholder}
                        maxLength={5000}
                        disabled={!canEdit}
                        aria-describedby={`composer-${field.key}-help`}
                    />
                    <p id={`composer-${field.key}-help`} className="mt-1 text-[10px] leading-4 text-muted-600">{field.help}</p>
                    <FieldError message={errors[field.key]} />
                </div>)}
            </div>

            <FieldError message={errors.draft_revision || errors.status} />
            {canEdit ? <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-line-200 pt-5">
                <button className="btn-primary" disabled={form.processing || !form.isDirty}>
                    <Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan draft'}
                </button>
                <span className="text-[10px] text-muted-600">Penyimpanan tidak membuat versi immutable. Versi baru dibuat saat submit.</span>
            </div> : <p className="mt-5 rounded-lg border border-blue-100 bg-blue-50 p-3 text-[11px] leading-5 text-blue-900">Narasi terkunci pada status ini. Gunakan snapshot aktif untuk proses verifikasi dan approval.</p>}
        </form>

        {previewOpen && <div id="composer-preview" className="border-t border-line-200 bg-slate-50 p-5 sm:p-6">
            <div className="mx-auto max-w-3xl rounded-xl border border-line-200 bg-white p-6 shadow-sm print:max-w-none print:border-0 print:shadow-none">
                <div className="flex items-start justify-between gap-4 border-b border-line-200 pb-5">
                    <div><span className="text-[10px] font-bold text-primary-700">DRAFT · {activityCode}</span><h3 className="mt-1 text-lg font-bold">Preview Laporan Pertanggungjawaban</h3><p className="mt-1 text-[11px] text-muted-600">Template {composer.template.label} · belum merupakan snapshot resmi</p></div>
                    <FileText className="size-6 text-primary-700" aria-hidden="true" />
                </div>
                <div className="mt-5 grid gap-5">
                    {narrativeFields.map((field, index) => <section key={field.key}>
                        <h4 className="text-xs font-bold">{index + 1}. {field.label}</h4>
                        <p className="mt-2 whitespace-pre-wrap text-xs leading-6 text-slate-700">{form.data[field.key].trim() || 'Belum diisi.'}</p>
                    </section>)}
                </div>
                <div className="mt-6 border-t border-line-200 pt-4 text-[10px] leading-5 text-muted-600">
                    <BookOpenCheck className="mr-1 inline size-3" aria-hidden="true" /> Source record ditelusuri melalui Activity-ID {activityCode}; pre-check {composer.policy_version}.
                </div>
            </div>
        </div>}

        <details className="group border-t border-line-200">
            <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-xs font-bold sm:px-6">
                <span>Pre-submission check · {composer.checks.filter((check) => check.result === 'PASS').length} lulus / {composer.checks.length} kontrol</span>
                <span aria-hidden="true" className="text-xl text-slate-400 transition group-open:rotate-45">+</span>
            </summary>
            <div className="grid gap-2 border-t border-line-200 p-5 sm:p-6">
                {composer.checks.map((check) => <div key={check.key} className={`grid gap-2 rounded-lg border p-3 sm:grid-cols-[160px_1fr] ${resultTone(check.result)}`}>
                    <div><StatusBadge status={check.result} /><b className="mt-2 block text-[11px]">{check.label}</b></div>
                    <div><p className="text-[11px] leading-5">{check.detail}</p><p className="mt-1 text-[9px] font-semibold opacity-75">Sumber: {check.source_reference}</p></div>
                </div>)}
            </div>
        </details>
    </section>;
}
