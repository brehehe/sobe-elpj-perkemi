import { PageHeader } from '@/components/page-header';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Bot, CheckCircle2, Clock3, FileLock2, History, LoaderCircle, Save, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

type FieldOption = { value: string; label: string };
type PolicyField = {
    name: string;
    label: string;
    type: 'text' | 'number' | 'textarea' | 'checkbox' | 'select';
    value: string | number | boolean;
    help: string | null;
    min: number | null;
    max: number | null;
    options: FieldOption[];
};
type PolicyHistory = { id: string; version: string; change_summary: string; is_active: boolean; creator: string; activated_at: string | null };
type Policy = {
    type: string;
    anchor: string;
    label: string;
    description: string;
    active_version: string;
    source: 'CONFIG_DEFAULT' | 'PUBLISHED';
    fields: PolicyField[];
    history: PolicyHistory[];
};
type AiUsage = {
    enabled: boolean;
    policy_version: string;
    used_tokens: number;
    limit_tokens: number | null;
    remaining_tokens: number | null;
    monthly_used_tokens: number;
    monthly_limit_tokens: number | null;
    monthly_remaining_tokens: number | null;
    warning_percent: number;
    daily_warning: boolean;
    monthly_warning: boolean;
    warning: boolean;
    daily_blocked: boolean;
    monthly_blocked: boolean;
    blocked: boolean;
    disclaimer: string;
    provider_configured: boolean;
    model: string;
};
type FormValue = string | number | boolean;
type PolicyFormData = { version: string; change_summary: string; [key: string]: FormValue };

const icons = {
    VERIFICATION: ShieldCheck,
    WORKFLOW: Clock3,
    TEMPLATES: FileLock2,
    RETENTION: History,
    AI: Bot,
    SYSTEM: AlertTriangle,
};

function formatDate(value: string | null) {
    return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function PolicyFieldControl({ policy, field, data, setData, error }: {
    policy: Policy;
    field: PolicyField;
    data: PolicyFormData;
    setData: (key: string, value: FormValue) => void;
    error?: string;
}) {
    const id = `${policy.anchor}-${field.name}`;
    const describedBy = [field.help ? `${id}-help` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined;

    if (field.type === 'checkbox') {
        return <div className="rounded-xl border border-line-200 bg-slate-50 p-4">
            <label htmlFor={id} className="flex cursor-pointer items-start gap-3">
                <input id={id} type="checkbox" className="mt-0.5 size-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" checked={Boolean(data[field.name])} onChange={(event) => setData(field.name, event.target.checked)} aria-describedby={describedBy} />
                <span><b className="block text-xs text-ink-950">{field.label}</b>{field.help && <span id={`${id}-help`} className="mt-1 block text-[11px] leading-5 text-muted-600">{field.help}</span>}</span>
            </label>
            {error && <p id={`${id}-error`} className="error mt-2">{error}</p>}
        </div>;
    }

    return <div>
        <label htmlFor={id} className="label">{field.label}</label>
        {field.type === 'textarea' ? <textarea id={id} rows={field.name.includes('checklist') || field.name === 'template_mappings' || field.name === 'sections' ? 5 : 3} className="field resize-y" value={String(data[field.name] ?? '')} onChange={(event) => setData(field.name, event.target.value)} aria-describedby={describedBy} /> : field.type === 'select' ? <select id={id} className="field" value={String(data[field.name] ?? '')} onChange={(event) => setData(field.name, event.target.value)} aria-describedby={describedBy}>{field.options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select> : <input id={id} type={field.type} min={field.min ?? undefined} max={field.max ?? undefined} className="field" value={String(data[field.name] ?? '')} onChange={(event) => setData(field.name, field.type === 'number' ? Number(event.target.value) : event.target.value)} aria-describedby={describedBy} />}
        {field.help && <p id={`${id}-help`} className="mt-1.5 text-[11px] leading-5 text-muted-600">{field.help}</p>}
        {error && <p id={`${id}-error`} className="error">{error}</p>}
    </div>;
}

function AiUsagePanel({ usage }: { usage: AiUsage }) {
    const dailyPercentage = usage.limit_tokens ? Math.min(Math.round((usage.used_tokens / usage.limit_tokens) * 100), 100) : 0;
    const monthlyPercentage = usage.monthly_limit_tokens ? Math.min(Math.round((usage.monthly_used_tokens / usage.monthly_limit_tokens) * 100), 100) : 0;

    return <aside className="mb-5 rounded-xl border border-blue-200 bg-blue-50/60 p-4" aria-label="Penggunaan AI harian dan bulanan">
        <div className="flex flex-wrap items-start justify-between gap-3"><div><b className="text-xs text-ink-950">Provider eksternal</b><p className="mt-1 text-[11px] text-muted-600">Model {usage.model} · kredensial {usage.provider_configured ? 'terkonfigurasi' : 'belum dikonfigurasi'}</p></div><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${usage.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'}`}>{usage.enabled ? 'AKTIF' : 'LOCAL FALLBACK'}</span></div>
        <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <div><div className="flex items-end justify-between gap-3"><span className="text-[11px] text-muted-600">Token hari ini</span><b className="tabular text-sm">{usage.used_tokens.toLocaleString('id-ID')} / {usage.limit_tokens?.toLocaleString('id-ID') ?? '—'}</b></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-white"><div className={`h-full rounded-full ${usage.daily_blocked ? 'bg-danger-600' : usage.daily_warning ? 'bg-amber-500' : 'bg-primary-600'}`} style={{ width: `${dailyPercentage}%` }} /></div></div>
            <div><div className="flex items-end justify-between gap-3"><span className="text-[11px] text-muted-600">Token bulan ini</span><b className="tabular text-sm">{usage.monthly_used_tokens.toLocaleString('id-ID')} / {usage.monthly_limit_tokens?.toLocaleString('id-ID') ?? '—'}</b></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-white"><div className={`h-full rounded-full ${usage.monthly_blocked ? 'bg-danger-600' : usage.monthly_warning ? 'bg-amber-500' : 'bg-primary-600'}`} style={{ width: `${monthlyPercentage}%` }} /></div></div>
        </div>
        <p className="mt-3 text-[11px] leading-5 text-muted-600">{usage.disclaimer} API key dan project ID tidak pernah ditampilkan di halaman ini.</p>
    </aside>;
}

function PolicySection({ policy, aiUsage }: { policy: Policy; aiUsage?: AiUsage }) {
    const initial: PolicyFormData = { version: '', change_summary: '' };
    policy.fields.forEach((field) => { initial[field.name] = field.value; });
    const form = useForm<PolicyFormData>(initial);
    const Icon = icons[policy.type as keyof typeof icons] ?? ShieldCheck;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/administration/governance/${policy.type}`, {
            preserveScroll: true,
            onSuccess: () => form.reset('version', 'change_summary'),
        });
    };

    return <section id={policy.anchor} className="panel scroll-mt-24 overflow-hidden" aria-labelledby={`${policy.anchor}-title`}>
        <div className="border-b border-line-200 bg-slate-50/70 px-5 py-5 sm:px-6">
            <div className="flex items-start gap-4"><span className="grid size-11 shrink-0 place-items-center rounded-xl bg-navy-950 text-white"><Icon aria-hidden="true" className="size-5" /></span><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 id={`${policy.anchor}-title`} className="text-base font-bold text-ink-950">{policy.label}</h2><span className="rounded-full border border-line-200 bg-white px-2 py-1 text-[9px] font-bold text-slate-600">{policy.active_version}</span></div><p className="mt-1 text-xs leading-5 text-muted-600">{policy.description}</p><p className="mt-2 text-[10px] font-semibold text-slate-500">Sumber aktif: {policy.source === 'PUBLISHED' ? 'versi publikasi database' : 'default konfigurasi terversi'}</p></div></div>
        </div>
        <div className="grid gap-6 p-5 sm:p-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div>
                {aiUsage && <AiUsagePanel usage={aiUsage} />}
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <div className="grid gap-5 md:grid-cols-2">{policy.fields.map((field) => <PolicyFieldControl key={field.name} policy={policy} field={field} data={form.data} setData={form.setData} error={form.errors[field.name]} />)}</div>
                    <div className="grid gap-5 border-t border-line-200 pt-5 md:grid-cols-2">
                        <div><label htmlFor={`${policy.anchor}-version`} className="label">Versi baru</label><input id={`${policy.anchor}-version`} className="field" placeholder={`${policy.anchor}-v2`} required value={form.data.version} onChange={(event) => form.setData('version', event.target.value.toLowerCase())} />{form.errors.version && <p className="error">{form.errors.version}</p>}</div>
                        <div><label htmlFor={`${policy.anchor}-summary`} className="label">Ringkasan perubahan</label><textarea id={`${policy.anchor}-summary`} className="field resize-y" rows={3} required value={form.data.change_summary} onChange={(event) => form.setData('change_summary', event.target.value)} />{form.errors.change_summary && <p className="error">{form.errors.change_summary}</p>}</div>
                    </div>
                    {form.errors.type && <p className="error">{form.errors.type}</p>}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"><p className="max-w-2xl text-[11px] leading-5 text-amber-900">Publikasi langsung berlaku untuk proses baru. Snapshot LPJ, tugas yang sudah aktif, dan paket arsip historis tidak ditulis ulang.</p><button type="submit" className="btn-primary shrink-0" disabled={form.processing || !form.data.version || !form.data.change_summary}>{form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Save aria-hidden="true" className="size-4" />}{form.processing ? 'Mempublikasikan…' : 'Publikasikan versi'}</button></div>
                </form>
            </div>
            <aside aria-label={`Riwayat ${policy.label}`}>
                <div className="flex items-center gap-2"><History aria-hidden="true" className="size-4 text-primary-700" /><h3 className="text-xs font-bold text-ink-950">Riwayat publikasi</h3></div>
                {policy.history.length ? <ol className="mt-3 grid gap-3">{policy.history.map((item) => <li key={item.id} className="rounded-xl border border-line-200 p-3"><div className="flex items-center justify-between gap-2"><b className="text-xs">{item.version}</b>{item.is_active && <span className="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-700"><CheckCircle2 aria-hidden="true" className="size-3" />AKTIF</span>}</div><p className="mt-2 text-[11px] leading-5 text-muted-600">{item.change_summary}</p><span className="mt-2 block text-[9px] text-slate-500">{item.creator} · {formatDate(item.activated_at)}</span></li>)}</ol> : <p className="mt-3 rounded-xl border border-dashed border-line-200 p-4 text-[11px] leading-5 text-muted-600">Belum ada publikasi database. Sistem masih memakai default konfigurasi yang tercantum di atas.</p>}
            </aside>
        </div>
    </section>;
}

export default function Governance({ policies, aiUsage }: { policies: Policy[]; aiUsage: AiUsage }) {
    return <>
        <Head title="Kebijakan & pengaturan" />
        <PageHeader eyebrow="Administrasi · governance terversi" title="Kebijakan dan pengaturan" description="Kelola kontrak operasional yang memengaruhi LPJ berikutnya. Setiap perubahan diterbitkan sebagai versi baru, dicatat pada audit trail, dan tidak mengubah snapshot historis." actions={<><Link href="/administration" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Administrasi</Link><Link href="/audit" className="btn-primary">Jejak audit</Link></>} />
        <nav aria-label="Navigasi kebijakan" className="mt-6 flex gap-2 overflow-x-auto pb-2">{policies.map((policy) => <a key={policy.type} href={`#${policy.anchor}`} className="min-h-10 shrink-0 rounded-full border border-line-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-blue-300 hover:text-primary-700">{policy.label}</a>)}</nav>
        <div className="mt-4 grid gap-6">{policies.map((policy) => <PolicySection key={policy.type} policy={policy} aiUsage={policy.type === 'AI' ? aiUsage : undefined} />)}</div>
    </>;
}
