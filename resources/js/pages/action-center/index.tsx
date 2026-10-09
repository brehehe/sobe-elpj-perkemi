import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertOctagon,
    ArrowUpRight,
    CheckCheck,
    CircleDot,
    Clock3,
    ListTodo,
    RotateCcw,
    Search,
    ShieldAlert,
    SlidersHorizontal,
    UserRoundCheck,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Option = { value: string; label: string };
type Task = {
    id: string;
    type: string;
    type_label: string;
    title: string;
    description?: string | null;
    priority: string;
    status: string;
    source: string;
    created_at?: string | null;
    due_at?: string | null;
    sla: { status: string; label: string; tone: 'danger' | 'warning' | 'success' | 'info' | 'neutral' };
    completion_condition: string;
    escalation_path: string;
    action_label: string;
    action_href: string;
    is_actionable: boolean;
    recipient: { kind: 'USER' | 'CAPABILITY'; label: string };
    unit?: { id: string; code: string; name: string } | null;
    object: { type?: string | null; id?: string | null; label: string; title?: string | null };
};
type Filters = {
    q: string;
    status: string;
    priority: string;
    due: string;
    sort: string;
    direction: string;
    unit: string;
    per_page: number;
};
type Props = {
    tasks: Paginated<Task>;
    metrics: { open: number; in_progress: number; overdue: number; due_today: number; done: number };
    filters: Filters;
    options: { statuses: Option[]; priorities: Option[]; due: Option[] };
    scope: { can_view_across_units: boolean; units: Array<{ id: string; code: string; name: string }> };
};

const slaTone: Record<Task['sla']['tone'], string> = {
    danger: 'border-red-200 bg-red-50 text-red-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    info: 'border-blue-200 bg-blue-50 text-blue-800',
    neutral: 'border-slate-200 bg-slate-50 text-slate-700',
};

function queryUrl(filters: Filters, changes: Partial<Filters>): string {
    const next = { ...filters, ...changes };
    const params = new URLSearchParams();

    Object.entries(next).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined && !(key === 'per_page' && value === 20)) {
            params.set(key, String(value));
        }
    });

    const query = params.toString();
    return query ? `/action-center?${query}` : '/action-center';
}

export default function ActionCenterIndex({ tasks, metrics, filters, options, scope }: Props) {
    const [form, setForm] = useState(filters);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(queryUrl(form, {}), {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Action Center" />
            <PageHeader
                eyebrow="Kendali pekerjaan"
                title="Action Center"
                description="Antrean kerja dibentuk oleh workflow aktual, dibatasi menurut penerima dan unit, serta ditutup ketika kondisi pada objek sumber terpenuhi."
            />

            <section aria-label="Ringkasan tugas" className="mt-6 grid gap-px overflow-hidden rounded-xl border border-line-200 bg-line-200 sm:grid-cols-2 xl:grid-cols-5">
                <Metric icon={ListTodo} label="Terbuka" value={metrics.open} />
                <Metric icon={CircleDot} label="Dikerjakan" value={metrics.in_progress} />
                <Metric icon={AlertOctagon} label="Lewat tenggat" value={metrics.overdue} tone="danger" />
                <Metric icon={Clock3} label="Jatuh tempo hari ini" value={metrics.due_today} tone="warning" />
                <Metric icon={CheckCheck} label="Selesai" value={metrics.done} tone="success" />
            </section>

            <nav aria-label="Filter status cepat" className="mt-5 flex gap-2 overflow-x-auto pb-1">
                {options.statuses.map((option) => {
                    const active = filters.status === option.value;
                    return (
                        <Link
                            key={option.value || 'all'}
                            href={queryUrl(filters, { status: option.value })}
                            preserveScroll
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-11 shrink-0 items-center rounded-lg border px-3 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                                active ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                            )}
                        >
                            {option.label}
                        </Link>
                    );
                })}
            </nav>

            <form onSubmit={submit} className="mt-4 border-y border-line-200 bg-white py-4" aria-label="Filter antrean tugas">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(240px,1.5fr)_repeat(5,minmax(130px,1fr))_auto]">
                    <div>
                        <label className="label" htmlFor="task-search">Cari tugas atau Activity-ID</label>
                        <div className="relative">
                            <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                            <input id="task-search" className="field pl-10" value={form.q} onChange={(event) => setForm({ ...form, q: event.target.value })} maxLength={100} placeholder="Judul, jenis, atau kegiatan" />
                        </div>
                    </div>
                    <SelectField id="task-priority" label="Prioritas" value={form.priority} options={options.priorities} onChange={(priority) => setForm({ ...form, priority })} />
                    <SelectField id="task-due" label="Tenggat" value={form.due} options={options.due} onChange={(due) => setForm({ ...form, due })} />
                    {scope.can_view_across_units && <SelectField id="task-unit" label="Unit" value={form.unit} options={[{ value: '', label: 'Semua unit' }, ...scope.units.map((unit) => ({ value: unit.id, label: `${unit.code} · ${unit.name}` }))]} onChange={(unit) => setForm({ ...form, unit })} />}
                    <SelectField id="task-sort" label="Urutkan" value={form.sort} options={[{ value: 'priority', label: 'Prioritas' }, { value: 'due_at', label: 'Tenggat' }, { value: 'created_at', label: 'Waktu dibuat' }]} onChange={(sort) => setForm({ ...form, sort })} />
                    <SelectField id="task-direction" label="Arah" value={form.direction} options={[{ value: 'asc', label: 'Naik' }, { value: 'desc', label: 'Turun' }]} onChange={(direction) => setForm({ ...form, direction })} />
                    <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
                        <button type="submit" className="btn-primary flex-1 px-3"><SlidersHorizontal aria-hidden="true" className="size-4" />Terapkan</button>
                        <Link href="/action-center" aria-label="Reset seluruh filter" className="btn-secondary px-3"><RotateCcw aria-hidden="true" className="size-4" /></Link>
                    </div>
                </div>
            </form>

            <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p aria-live="polite" className="text-sm font-semibold text-ink-950">
                    {tasks.total === 0 ? 'Tidak ada tugas ditemukan' : `Menampilkan ${tasks.from}–${tasks.to} dari ${tasks.total} tugas`}
                </p>
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-600" htmlFor="task-page-size">
                    Baris
                    <select
                        id="task-page-size"
                        className="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm text-ink-950"
                        value={filters.per_page}
                        onChange={(event) => router.get(queryUrl(filters, { per_page: Number(event.target.value) }), {}, { preserveScroll: true })}
                    >
                        <option value={10}>10</option>
                        <option value={20}>20</option>
                        <option value={50}>50</option>
                    </select>
                </label>
            </div>

            {tasks.data.length > 0 ? (
                <section className="mt-3 overflow-hidden rounded-xl border border-line-200 bg-white" aria-labelledby="task-list-title">
                    <h2 id="task-list-title" className="sr-only">Daftar tugas workflow</h2>
                    <div className="hidden overflow-x-auto lg:block" role="region" aria-label="Daftar tugas yang dapat digulir" tabIndex={0}>
                        <table className="w-full min-w-[1120px] text-left text-xs">
                            <caption className="sr-only">Tugas menurut prioritas, objek, penerima, tenggat SLA, dan aksi yang diperlukan.</caption>
                            <thead className="sticky top-0 bg-slate-50 text-slate-700">
                                <tr>
                                    <th scope="col" className="px-4 py-3">Prioritas</th>
                                    <th scope="col" className="px-4 py-3">Tugas & kondisi selesai</th>
                                    <th scope="col" className="px-4 py-3">Objek & unit</th>
                                    <th scope="col" className="px-4 py-3">Penerima</th>
                                    <th scope="col" className="px-4 py-3">Tenggat & SLA</th>
                                    <th scope="col" className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line-200">
                                {tasks.data.map((task) => <DesktopTaskRow key={task.id} task={task} />)}
                            </tbody>
                        </table>
                    </div>
                    <div className="divide-y divide-line-200 lg:hidden">
                        {tasks.data.map((task) => <MobileTaskCard key={task.id} task={task} />)}
                    </div>
                    <Pagination links={tasks.links} />
                </section>
            ) : (
                <section className="mt-3 rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                    <ListTodo aria-hidden="true" className="mx-auto size-9 text-slate-400" />
                    <h2 className="mt-4 text-base font-bold text-ink-950">Tidak ada tugas pada filter ini</h2>
                    <p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-muted-600">Ubah filter atau reset pencarian. Tugas baru akan muncul otomatis ketika workflow membuat tindakan untuk pengguna atau capability Anda.</p>
                    <Link href="/action-center" className="btn-secondary mt-5"><RotateCcw aria-hidden="true" className="size-4" />Reset filter</Link>
                </section>
            )}
        </>
    );
}

function DesktopTaskRow({ task }: { task: Task }) {
    return (
        <tr className={cn('align-top', task.sla.status === 'OVERDUE' && 'bg-red-50/30')}>
            <td className="px-4 py-4"><StatusBadge status={task.priority} /><StatusBadge status={task.status} className="mt-2 block w-fit" /></td>
            <td className="max-w-md px-4 py-4">
                <span className="text-[11px] font-bold text-primary-700">{task.type_label}</span>
                <h3 className="mt-1 text-sm font-bold text-ink-950">{task.title}</h3>
                {task.description && <p className="mt-1 max-w-xl text-xs leading-5 text-muted-600">{task.description}</p>}
                <details className="mt-2 text-[11px] leading-5 text-slate-600">
                    <summary className="cursor-pointer font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">Syarat selesai & eskalasi</summary>
                    <p className="mt-2"><b>Selesai bila:</b> {task.completion_condition}</p>
                    <p className="mt-1"><b>Eskalasi:</b> {task.escalation_path}</p>
                </details>
            </td>
            <td className="px-4 py-4">
                <span className="font-mono text-[11px] font-bold text-ink-950 [overflow-wrap:anywhere]">{task.object.label}</span>
                {task.object.title && <span className="mt-1 block max-w-48 text-[11px] leading-5 text-muted-600">{task.object.title}</span>}
                <span className="mt-2 block text-[10px] font-semibold text-slate-500">{task.unit ? `${task.unit.code} · ${task.unit.name}` : 'Lintas unit'}</span>
            </td>
            <td className="px-4 py-4"><span className="flex max-w-44 items-start gap-2 font-semibold text-slate-700"><UserRoundCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-primary-700" />{task.recipient.label}</span><span className="mt-2 block text-[10px] text-slate-500">{task.recipient.kind === 'USER' ? 'Ditugaskan langsung' : 'Berdasarkan capability'}</span></td>
            <td className="px-4 py-4"><SlaLabel task={task} /><span className="mt-2 block whitespace-nowrap text-[11px] text-slate-600">{formatDate(task.due_at, true)}</span></td>
            <td className="px-4 py-4 text-right">
                <Link href={task.action_href} className={task.is_actionable ? 'btn-primary px-3 text-xs' : 'btn-secondary px-3 text-xs'}>
                    {task.action_label}<ArrowUpRight aria-hidden="true" className="size-4" />
                </Link>
            </td>
        </tr>
    );
}

function MobileTaskCard({ task }: { task: Task }) {
    return (
        <article className={cn('p-4', task.sla.status === 'OVERDUE' && 'border-l-4 border-l-danger-600 bg-red-50/30')}>
            <div className="flex flex-wrap items-center gap-2"><StatusBadge status={task.priority} /><StatusBadge status={task.status} /><SlaLabel task={task} /></div>
            <p className="mt-4 text-[11px] font-bold text-primary-700">{task.type_label}</p>
            <h3 className="mt-1 text-base font-bold text-ink-950">{task.title}</h3>
            {task.description && <p className="mt-2 text-sm leading-6 text-muted-600">{task.description}</p>}
            <dl className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-3 text-xs sm:grid-cols-2">
                <div><dt className="font-semibold text-slate-500">Objek</dt><dd className="mt-1 font-mono font-bold [overflow-wrap:anywhere]">{task.object.label}</dd></div>
                <div><dt className="font-semibold text-slate-500">Unit</dt><dd className="mt-1 font-semibold">{task.unit ? `${task.unit.code} · ${task.unit.name}` : 'Lintas unit'}</dd></div>
                <div><dt className="font-semibold text-slate-500">Penerima</dt><dd className="mt-1 font-semibold">{task.recipient.label}</dd></div>
                <div><dt className="font-semibold text-slate-500">Tenggat</dt><dd className="mt-1 font-semibold">{formatDate(task.due_at, true)}</dd></div>
            </dl>
            <div className="mt-4 border-l-2 border-primary-600 pl-3 text-xs leading-5 text-slate-600"><p><b>Selesai bila:</b> {task.completion_condition}</p><p className="mt-1"><b>Eskalasi:</b> {task.escalation_path}</p></div>
            <Link href={task.action_href} className={cn('mt-5 w-full', task.is_actionable ? 'btn-primary' : 'btn-secondary')}>
                {task.action_label}<ArrowUpRight aria-hidden="true" className="size-4" />
            </Link>
        </article>
    );
}

function SlaLabel({ task }: { task: Task }) {
    return <span className={cn('inline-flex min-h-7 items-center gap-1.5 rounded-md border px-2 py-1 text-[10px] font-bold', slaTone[task.sla.tone])}><ShieldAlert aria-hidden="true" className="size-3.5" />{task.sla.label}</span>;
}

function SelectField({ id, label, value, options, onChange }: { id: string; label: string; value: string; options: Option[]; onChange: (value: string) => void }) {
    return <div><label className="label" htmlFor={id}>{label}</label><select id={id} className="field" value={value} onChange={(event) => onChange(event.target.value)}>{options.map((option) => <option key={option.value || 'all'} value={option.value}>{option.label}</option>)}</select></div>;
}

function Metric({ icon: Icon, label, value, tone = 'default' }: { icon: typeof ListTodo; label: string; value: number; tone?: 'default' | 'danger' | 'warning' | 'success' }) {
    const colors = {
        default: 'bg-blue-50 text-primary-700',
        danger: 'bg-red-50 text-danger-600',
        warning: 'bg-amber-50 text-amber-700',
        success: 'bg-emerald-50 text-success-600',
    };

    return <article className="flex min-h-24 items-center gap-4 bg-white p-4"><span className={cn('grid size-10 shrink-0 place-items-center rounded-lg', colors[tone])}><Icon aria-hidden="true" className="size-5" /></span><div><span className="text-xs text-muted-600">{label}</span><b className="tabular block text-2xl text-ink-950">{value}</b></div></article>;
}
