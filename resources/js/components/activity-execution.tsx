import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, CheckCircle2, Circle, CircleDot, Flag, Play, Plus, Wrench } from 'lucide-react';
import type { FormEvent } from 'react';

export type ActivityAgendaItem = {
    id: string;
    title: string;
    description?: string;
    location?: string;
    starts_at: string;
    ends_at: string;
    status: string;
    notes?: string;
    completed_at?: string;
    completed_by?: { id: string; name: string };
};

export type ActivityExecutionRecord = {
    id: string;
    type: string;
    title: string;
    details?: string;
    severity?: string;
    progress_percent?: number;
    status: string;
    occurred_at: string;
    resolution_notes?: string;
    resolved_at?: string;
    creator: { id: string; name: string };
    resolver?: { id: string; name: string };
};

export type CompletionReadiness = {
    ready: boolean;
    policy_version: string;
    checks: Array<{ key: string; label: string; ready: boolean; detail: string }>;
    errors: Record<string, string>;
};

type ExecutionActivity = {
    id: string;
    title: string;
    starts_at: string;
    ends_at: string;
    status: string;
    agenda_items: ActivityAgendaItem[];
    execution_records: ActivityExecutionRecord[];
};

const lifecycleSteps = [
    { status: 'PLANNED', label: 'Direncanakan' },
    { status: 'IN_PROGRESS', label: 'Berjalan' },
    { status: 'COMPLETED', label: 'Selesai' },
];

function toLocalDateTime(value: string): string {
    const date = new Date(value);
    const offset = date.getTimezoneOffset();

    return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 16);
}

function FormErrors({ errors }: { errors: Record<string, string> }) {
    const messages = [...new Set(Object.values(errors))];
    if (!messages.length) return null;

    return <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-danger-600" role="alert" tabIndex={-1}>
        <b className="block">Aksi belum dapat diproses</b>
        <ul className="mt-1 list-disc space-y-1 pl-4">{messages.map((message) => <li key={message}>{message}</li>)}</ul>
    </div>;
}

export function ActivityLifecycle({ activity, readiness, canTransition }: { activity: ExecutionActivity; readiness: CompletionReadiness; canTransition: boolean }) {
    const form = useForm<Record<string, never>>({});
    const currentIndex = Math.max(0, lifecycleSteps.findIndex((step) => step.status === activity.status));
    const startIsDue = new Date(activity.starts_at).getTime() <= Date.now();
    const submit = (endpoint: 'starts' | 'completions') => form.post(`/activities/${activity.id}/${endpoint}`, { preserveScroll: true });

    return <section className="panel overflow-hidden">
        <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.72fr)]">
            <div>
                <div className="flex items-start justify-between gap-4">
                    <div><h2 className="text-base font-bold">Kontrol pelaksanaan</h2><p className="mt-1 text-xs leading-5 text-muted-600">Transisi status hanya dijalankan PIC dan seluruh perubahan dicatat di audit trail.</p></div>
                    <StatusBadge status={activity.status} />
                </div>
                <ol className="mt-6 grid grid-cols-3" aria-label="Tahapan pelaksanaan kegiatan">
                    {lifecycleSteps.map((step, index) => {
                        const reached = index <= currentIndex;
                        const active = index === currentIndex;
                        return <li key={step.status} className="relative flex flex-col items-center text-center">
                            {index > 0 && <span aria-hidden="true" className={`absolute top-4 right-1/2 h-0.5 w-full ${reached ? 'bg-primary-600' : 'bg-slate-200'}`} />}
                            <span className={`relative z-10 grid size-8 place-items-center rounded-full border-2 text-xs font-bold ${reached ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-300 bg-white text-slate-500'}`} aria-current={active ? 'step' : undefined}>{index < currentIndex ? <CheckCircle2 className="size-4" aria-hidden="true" /> : index + 1}</span>
                            <span className={`mt-2 text-xs font-semibold ${active ? 'text-primary-700' : 'text-slate-600'}`}>{step.label}</span>
                        </li>;
                    })}
                </ol>
                <div className="mt-6 grid gap-3">
                    <FormErrors errors={form.errors as Record<string, string>} />
                    {canTransition && activity.status === 'PLANNED' && <div><button type="button" className="btn-primary" disabled={form.processing || !startIsDue} onClick={() => submit('starts')}><Play className="size-4" aria-hidden="true" />{form.processing ? 'Memulai…' : 'Mulai kegiatan'}</button>{!startIsDue && <p className="mt-2 text-xs text-muted-600">Tombol aktif pada {formatDate(activity.starts_at, true)}.</p>}</div>}
                    {canTransition && activity.status === 'IN_PROGRESS' && <button type="button" className="btn-primary w-fit" disabled={form.processing || !readiness.ready} onClick={() => submit('completions')}><Flag className="size-4" aria-hidden="true" />{form.processing ? 'Memeriksa…' : 'Selesaikan kegiatan'}</button>}
                    {!canTransition && activity.status !== 'COMPLETED' && <p className="text-xs leading-5 text-muted-600">Hanya PIC kegiatan yang dapat menjalankan transisi status.</p>}
                </div>
            </div>
            <div className="rounded-xl border border-line-200 bg-slate-50 p-4">
                <div className="flex items-end justify-between gap-3"><div><h3 className="text-sm font-bold">Gate penyelesaian</h3><p className="mt-1 text-[11px] text-muted-600">Kebijakan {readiness.policy_version}</p></div><span className={`text-xs font-bold ${readiness.ready ? 'text-emerald-700' : 'text-amber-800'}`}>{readiness.checks.filter((check) => check.ready).length}/{readiness.checks.length} siap</span></div>
                <ul className="mt-3 grid gap-2">{readiness.checks.map((check) => <li key={check.key} className="flex items-start gap-2 rounded-lg bg-white p-2.5 text-xs"><span className={`mt-0.5 ${check.ready ? 'text-success-600' : 'text-warning-600'}`}>{check.ready ? <CheckCircle2 className="size-4" aria-hidden="true" /> : <Circle className="size-4" aria-hidden="true" />}</span><span><b className="block text-slate-700">{check.label}</b><span className="mt-0.5 block leading-5 text-muted-600">{check.detail}</span></span></li>)}</ul>
            </div>
        </div>
    </section>;
}

function AgendaStatusForm({ activityId, item }: { activityId: string; item: ActivityAgendaItem }) {
    const options = item.status === 'PLANNED'
        ? [{ value: 'IN_PROGRESS', label: 'Mulai agenda' }, { value: 'CANCELLED', label: 'Batalkan agenda' }]
        : item.status === 'IN_PROGRESS'
            ? [{ value: 'COMPLETED', label: 'Tandai selesai' }, { value: 'CANCELLED', label: 'Batalkan agenda' }]
            : [];
    const form = useForm({ status: options[0]?.value ?? '', notes: '' });
    if (!options.length) return null;

    return <form className="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] sm:items-end" onSubmit={(event) => { event.preventDefault(); form.patch(`/activities/${activityId}/agenda-items/${item.id}`, { preserveScroll: true }); }}>
        <div><label className="label" htmlFor={`agenda-status-${item.id}`}>Status berikutnya</label><select id={`agenda-status-${item.id}`} className="field" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></div>
        <div><label className="label" htmlFor={`agenda-notes-${item.id}`}>Catatan {form.data.status === 'CANCELLED' ? '(wajib)' : '(opsional)'}</label><input id={`agenda-notes-${item.id}`} className="field" required={form.data.status === 'CANCELLED'} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
        <button className="btn-secondary" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Perbarui'}</button>
        {(form.errors.status || form.errors.notes) && <p className="error sm:col-span-3" role="alert">{form.errors.status || form.errors.notes}</p>}
    </form>;
}

export function ActivityAgenda({ activity, canManage }: { activity: ExecutionActivity; canManage: boolean }) {
    const form = useForm({ title: '', description: '', location: '', starts_at: toLocalDateTime(activity.starts_at), ends_at: toLocalDateTime(activity.ends_at) });
    const canEdit = canManage && ['PLANNED', 'IN_PROGRESS'].includes(activity.status);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/activities/${activity.id}/agenda-items`, { preserveScroll: true, onSuccess: () => form.reset('title', 'description', 'location') });
    };

    return <section className="panel overflow-hidden">
        <div className="flex items-start justify-between gap-4 border-b border-line-200 px-5 py-4"><div><h2 className="text-base font-bold">Agenda pelaksanaan</h2><p className="mt-1 text-xs leading-5 text-muted-600">Setiap sesi berada dalam rentang jadwal kegiatan dan ditutup satu per satu.</p></div><CalendarClock className="size-5 text-primary-600" aria-hidden="true" /></div>
        {activity.agenda_items.length ? <div>{activity.agenda_items.map((item) => <article key={item.id} className="border-b border-slate-100 px-5 py-4 last:border-b-0"><div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><b className="block text-sm text-slate-800">{item.title}</b><p className="mt-1 text-xs leading-5 text-muted-600">{formatDate(item.starts_at, true)}–{formatDate(item.ends_at, true)}{item.location ? ` · ${item.location}` : ''}</p>{item.description && <p className="mt-2 text-xs leading-5 text-slate-600">{item.description}</p>}{item.notes && <p className="mt-2 border-l-2 border-slate-300 pl-3 text-xs leading-5 text-muted-600">{item.notes}</p>}</div><StatusBadge status={item.status} /></div>{canManage && activity.status === 'IN_PROGRESS' && <AgendaStatusForm activityId={activity.id} item={item} />}</article>)}</div> : <EmptyState title="Agenda belum disusun" description="Tambahkan sesi agar progres pelaksanaan dan gate penyelesaian dapat diperiksa." />}
        {canEdit && <details className="group border-t border-line-200"><summary className="flex min-h-12 cursor-pointer list-none items-center justify-between px-5 text-xs font-bold text-primary-700"><span className="flex items-center gap-2"><Plus className="size-4" aria-hidden="true" />Tambah agenda</span><span aria-hidden="true" className="text-lg text-slate-400 group-open:rotate-45">+</span></summary><form onSubmit={submit} className="grid gap-3 border-t border-line-200 bg-slate-50/70 p-5 md:grid-cols-2"><div className="md:col-span-2"><label className="label" htmlFor="agenda-title">Judul agenda</label><input id="agenda-title" className="field bg-white" required aria-describedby={form.errors.title ? 'agenda-title-error' : undefined} value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />{form.errors.title && <p id="agenda-title-error" className="error" role="alert">{form.errors.title}</p>}</div><div><label className="label" htmlFor="agenda-start">Mulai</label><input id="agenda-start" type="datetime-local" className="field bg-white" required value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} />{form.errors.starts_at && <p className="error" role="alert">{form.errors.starts_at}</p>}</div><div><label className="label" htmlFor="agenda-end">Selesai</label><input id="agenda-end" type="datetime-local" className="field bg-white" required value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} />{form.errors.ends_at && <p className="error" role="alert">{form.errors.ends_at}</p>}</div><div><label className="label" htmlFor="agenda-location">Lokasi</label><input id="agenda-location" className="field bg-white" value={form.data.location} onChange={(event) => form.setData('location', event.target.value)} /></div><div><label className="label" htmlFor="agenda-description">Keterangan</label><input id="agenda-description" className="field bg-white" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} /></div><div className="md:col-span-2"><button className="btn-primary" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan agenda'}</button></div></form></details>}
    </section>;
}

function ResolveIssueForm({ activityId, record }: { activityId: string; record: ActivityExecutionRecord }) {
    const form = useForm({ resolution_notes: '' });
    return <form className="mt-3 flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); form.patch(`/activities/${activityId}/execution-records/${record.id}`, { preserveScroll: true }); }}><div className="min-w-0 flex-1"><label className="label" htmlFor={`resolution-${record.id}`}>Resolusi issue</label><textarea id={`resolution-${record.id}`} className="field min-h-20 bg-white" required value={form.data.resolution_notes} onChange={(event) => form.setData('resolution_notes', event.target.value)} />{form.errors.resolution_notes && <p className="error" role="alert">{form.errors.resolution_notes}</p>}</div><button className="btn-secondary" disabled={form.processing}><Wrench className="size-4" aria-hidden="true" />{form.processing ? 'Menyimpan…' : 'Tutup issue'}</button></form>;
}

export function ActivityExecutionLog({ activity, canManage }: { activity: ExecutionActivity; canManage: boolean }) {
    const occurredAt = new Date(Math.min(Math.max(Date.now(), new Date(activity.starts_at).getTime()), new Date(activity.ends_at).getTime())).toISOString();
    const form = useForm({ type: 'PROGRESS', title: '', details: '', severity: 'MEDIUM', progress_percent: '', occurred_at: toLocalDateTime(occurredAt) });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(`/activities/${activity.id}/execution-records`, { preserveScroll: true, onSuccess: () => form.reset('title', 'details', 'progress_percent') });
    };

    return <section className="panel overflow-hidden">
        <div className="flex items-start justify-between gap-4 border-b border-line-200 px-5 py-4"><div><h2 className="text-base font-bold">Buku lapangan</h2><p className="mt-1 text-xs leading-5 text-muted-600">Progres, insiden, perubahan, dan catatan PIC tersimpan sebagai record terpisah.</p></div><CircleDot className="size-5 text-primary-600" aria-hidden="true" /></div>
        {activity.execution_records.length ? <div>{activity.execution_records.map((record) => <article key={record.id} className="border-b border-slate-100 px-5 py-4 last:border-b-0"><div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><b className="text-sm text-slate-800">{record.title}</b><StatusBadge status={record.type} />{record.severity && <StatusBadge status={record.severity} />}</div><p className="mt-1 text-[11px] text-muted-600">{formatDate(record.occurred_at, true)} · {record.creator.name}</p>{record.details && <p className="mt-2 text-xs leading-5 text-slate-600">{record.details}</p>}{record.progress_percent !== undefined && record.progress_percent !== null && <div className="mt-3 flex items-center gap-3"><div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-primary-600" style={{ width: `${record.progress_percent}%` }} /></div><b className="tabular text-xs text-primary-700">{record.progress_percent}%</b></div>}{record.resolution_notes && <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-xs leading-5 text-emerald-800"><b>Resolusi:</b> {record.resolution_notes}</p>}</div><StatusBadge status={record.status} /></div>{canManage && activity.status === 'IN_PROGRESS' && record.status === 'OPEN' && <ResolveIssueForm activityId={activity.id} record={record} />}</article>)}</div> : <EmptyState title="Belum ada catatan pelaksanaan" description="Saat kegiatan berjalan, catat progres dan setiap perubahan penting di sini." />}
        {canManage && activity.status === 'IN_PROGRESS' && <details className="group border-t border-line-200"><summary className="flex min-h-12 cursor-pointer list-none items-center justify-between px-5 text-xs font-bold text-primary-700"><span className="flex items-center gap-2"><Plus className="size-4" aria-hidden="true" />Catat pelaksanaan</span><span aria-hidden="true" className="text-lg text-slate-400 group-open:rotate-45">+</span></summary><form onSubmit={submit} className="grid gap-3 border-t border-line-200 bg-slate-50/70 p-5 md:grid-cols-2"><div><label className="label" htmlFor="execution-type">Jenis catatan</label><select id="execution-type" className="field bg-white" value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}><option value="PROGRESS">Progres</option><option value="INCIDENT">Insiden</option><option value="CHANGE">Perubahan</option><option value="NOTE">Catatan PIC</option></select></div><div><label className="label" htmlFor="execution-time">Waktu kejadian</label><input id="execution-time" type="datetime-local" className="field bg-white" required value={form.data.occurred_at} onChange={(event) => form.setData('occurred_at', event.target.value)} /></div><div className="md:col-span-2"><label className="label" htmlFor="execution-title">Judul</label><input id="execution-title" className="field bg-white" required value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />{form.errors.title && <p className="error" role="alert">{form.errors.title}</p>}</div>{form.data.type === 'PROGRESS' && <div><label className="label" htmlFor="execution-progress">Progres (0–100%)</label><input id="execution-progress" type="number" min="0" max="100" className="field bg-white" required value={form.data.progress_percent} onChange={(event) => form.setData('progress_percent', event.target.value)} />{form.errors.progress_percent && <p className="error" role="alert">{form.errors.progress_percent}</p>}</div>}{form.data.type === 'INCIDENT' && <div><label className="label" htmlFor="execution-severity">Severity</label><select id="execution-severity" className="field bg-white" value={form.data.severity} onChange={(event) => form.setData('severity', event.target.value)}><option value="LOW">Rendah</option><option value="MEDIUM">Sedang</option><option value="HIGH">Tinggi</option><option value="CRITICAL">Kritis</option></select></div>}<div className={form.data.type === 'NOTE' || form.data.type === 'CHANGE' ? 'md:col-span-2' : ''}><label className="label" htmlFor="execution-details">Detail</label><textarea id="execution-details" className="field min-h-24 bg-white" value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} /></div><div className="md:col-span-2"><FormErrors errors={form.errors as Record<string, string>} /><button className="btn-primary mt-3" disabled={form.processing}>{form.data.type === 'INCIDENT' ? <AlertTriangle className="size-4" aria-hidden="true" /> : <CircleDot className="size-4" aria-hidden="true" />}{form.processing ? 'Menyimpan…' : 'Simpan catatan'}</button></div></form></details>}
    </section>;
}
