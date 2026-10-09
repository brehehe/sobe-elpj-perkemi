import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { cn, formatDate } from '@/lib/utils';
import type { Paginated, SharedProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Braces,
    CircleUserRound,
    Download,
    Filter,
    GitCompareArrows,
    History,
    KeyRound,
    RotateCcw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Option = { value: string; label: string };
type ReferenceOption = { id: string; name: string; code?: string; type?: string };
type AuditFilters = {
    q: string;
    object: string;
    unit: string | null;
    actor: string;
    domain: string;
    event: string;
    date_from: string;
    date_to: string;
    result: string;
    channel: string;
    sort: string;
};
type AuditEvent = {
    id: string;
    event_id: string | null;
    correlation_id: string | null;
    request_id: string | null;
    event_type: string;
    event_label: string;
    domain: string;
    source_channel: string;
    result: string;
    authority: string | null;
    payload_version: number;
    evidence_references: string[];
    created_at: string | null;
    actor: { id: string | null; name: string };
    unit: { id: string | null; code: string | null; name: string; type: string | null };
    object: { label: string; type: string | null; id: string | null; version: string | null; url: string | null };
    change: { before: string | null; after: string | null };
    reason: string | null;
    location: { ip_address: string | null; user_agent: string | null };
    payload: Record<string, unknown>;
};
type AuditProps = {
    summary: { total: number; actors: number; state_changes: number; failures: number };
    scope: {
        can_view_all_units: boolean;
        selected_unit: ReferenceOption | null;
        label: string;
    };
    events: Paginated<AuditEvent>;
    filters: AuditFilters;
    options: {
        actors: ReferenceOption[];
        domains: Option[];
        events: Option[];
        channels: string[];
        units: ReferenceOption[];
    };
};
type MetricProps = { icon: LucideIcon; label: string; value: number; detail: string; tone?: string };

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

function queryUrl(path: string, filters: AuditFilters): string {
    const query = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value && !(key === 'sort' && value === 'latest')) query.set(key, value);
    });

    return query.size > 0 ? `${path}?${query.toString()}` : path;
}

function eventIcon(domain: string): LucideIcon {
    if (domain === 'auth') return KeyRound;
    if (domain === 'activity') return Activity;
    if (domain === 'lpj') return ShieldCheck;
    if (domain === 'audit') return History;

    return Braces;
}

export default function AuditIndex({ summary, scope, events, filters, options }: AuditProps) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const [form, setForm] = useState<AuditFilters>(filters);
    const activeFilterCount = Object.entries(filters).filter(([key, value]) => value && !(key === 'sort' && value === 'latest')).length;

    const update = (key: keyof AuditFilters, value: string) => setForm((current) => ({ ...current, [key]: value }));
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/audit', Object.fromEntries(Object.entries(form).filter(([, value]) => value)), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Audit & Compliance" />
            <PageHeader
                eyebrow="Governance → jejak append-only"
                title="Audit & Compliance"
                description="Telusuri siapa melakukan apa, kapan, melalui channel mana, terhadap object apa, perubahan statusnya, serta alasan dan evidence yang menyertai keputusan."
                actions={<a href={queryUrl('/audit/export', filters)} className="btn-secondary"><Download aria-hidden="true" className="size-4" />Ekspor CSV</a>}
            />

            <section aria-labelledby="audit-summary" className="panel mt-6 overflow-hidden">
                <div className="border-b border-line-200 bg-slate-50/70 px-5 py-4 sm:px-6">
                    <h2 id="audit-summary" className="text-sm font-bold text-ink-950">Ringkasan {scope.label}</h2>
                    <p className="mt-1 text-[11px] text-muted-600">Metrik mengikuti filter aktif dan tidak mengubah rekam audit sumber.</p>
                </div>
                <dl className="grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                    <Metric icon={History} label="Event tercatat" value={summary.total} detail="Record append-only sesuai filter" />
                    <Metric icon={Users} label="Actor terlibat" value={summary.actors} detail="Pengguna atau service teridentifikasi" tone="text-violet-700" />
                    <Metric icon={GitCompareArrows} label="Perubahan status" value={summary.state_changes} detail="Transisi before dan after tercatat" tone="text-blue-700" />
                    <Metric icon={ShieldAlert} label="Hasil gagal" value={summary.failures} detail="Event yang memerlukan penelusuran" tone={summary.failures ? 'text-red-700' : 'text-emerald-700'} />
                </dl>
            </section>

            <section aria-labelledby="audit-filters" className="panel mt-5 p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="audit-filters" className="text-sm font-bold text-ink-950">Filter jejak audit</h2>
                        <p className="mt-1 text-[11px] text-muted-600">Gunakan Event ID, Correlation ID, Request ID, authority, actor, unit, atau Object ID untuk investigasi.</p>
                    </div>
                    {activeFilterCount > 0 && <span className="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[11px] font-bold text-primary-700">{activeFilterCount} filter aktif</span>}
                </div>

                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-800">
                        <b>Filter belum dapat diterapkan.</b>
                        <ul className="mt-1 list-disc pl-4">{Object.values(errors).map((message) => <li key={message}>{message}</li>)}</ul>
                    </div>
                )}

                <form onSubmit={applyFilters} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="md:col-span-2">
                        <label className="label" htmlFor="audit-search">Cari seluruh jejak</label>
                        <div className="relative">
                            <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input id="audit-search" className="field pl-9" value={form.q} onChange={(event) => update('q', event.target.value)} placeholder="Event, actor, authority, atau trace ID" />
                        </div>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-object">Object ID</label>
                        <input id="audit-object" className="field" value={form.object} onChange={(event) => update('object', event.target.value)} placeholder="UUID atau bagian ID" />
                    </div>
                    {scope.can_view_all_units && (
                        <div>
                            <label className="label" htmlFor="audit-unit">Unit organisasi</label>
                            <select id="audit-unit" className="field" value={form.unit || ''} onChange={(event) => update('unit', event.target.value)}>
                                <option value="">Seluruh unit</option>
                                {options.units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} · {unit.name}</option>)}
                            </select>
                        </div>
                    )}
                    <div>
                        <label className="label" htmlFor="audit-domain">Domain</label>
                        <select id="audit-domain" className="field" value={form.domain} onChange={(event) => update('domain', event.target.value)}>
                            <option value="">Semua domain</option>
                            {options.domains.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-event">Jenis event</label>
                        <select id="audit-event" className="field" value={form.event} onChange={(event) => update('event', event.target.value)}>
                            <option value="">Semua event</option>
                            {options.events.map((option) => <option key={option.value} value={option.value}>{option.label} · {option.value}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-actor">Actor</label>
                        <select id="audit-actor" className="field" value={form.actor} onChange={(event) => update('actor', event.target.value)}>
                            <option value="">Semua actor</option>
                            {options.actors.map((actor) => <option key={actor.id} value={actor.id}>{actor.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-channel">Channel</label>
                        <select id="audit-channel" className="field" value={form.channel} onChange={(event) => update('channel', event.target.value)}>
                            <option value="">Semua channel</option>
                            {options.channels.map((channel) => <option key={channel} value={channel}>{channel}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-result">Hasil</label>
                        <select id="audit-result" className="field" value={form.result} onChange={(event) => update('result', event.target.value)}>
                            <option value="">Semua hasil</option>
                            <option value="SUCCEEDED">Berhasil</option>
                            <option value="FAILED">Gagal</option>
                        </select>
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-from">Dari tanggal</label>
                        <input id="audit-from" type="date" className="field" value={form.date_from} onChange={(event) => update('date_from', event.target.value)} />
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-to">Sampai tanggal</label>
                        <input id="audit-to" type="date" className="field" value={form.date_to} onChange={(event) => update('date_to', event.target.value)} />
                    </div>
                    <div>
                        <label className="label" htmlFor="audit-sort">Urutan waktu</label>
                        <select id="audit-sort" className="field" value={form.sort} onChange={(event) => update('sort', event.target.value)}>
                            <option value="latest">Terbaru lebih dahulu</option>
                            <option value="oldest">Terlama lebih dahulu</option>
                        </select>
                    </div>
                    <div className="flex items-end gap-2 xl:col-span-4 xl:justify-end">
                        {activeFilterCount > 0 && <Link href="/audit" className="btn-secondary"><RotateCcw aria-hidden="true" className="size-4" />Reset</Link>}
                        <button type="submit" className="btn-primary"><Filter aria-hidden="true" className="size-4" />Terapkan filter</button>
                    </div>
                </form>
            </section>

            <section aria-labelledby="audit-timeline" className="mt-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="audit-timeline" className="text-base font-bold text-ink-950">Timeline kepatuhan</h2>
                        <p className="mt-1 text-xs text-muted-600">{events.total} event ditemukan · urutan {filters.sort === 'oldest' ? 'terlama' : 'terbaru'}.</p>
                    </div>
                    <span className="inline-flex items-center gap-2 text-[11px] font-semibold text-slate-600"><ShieldCheck aria-hidden="true" className="size-4 text-emerald-700" />Tidak dapat diedit atau dihapus</span>
                </div>

                {events.data.length > 0 ? (
                    <ol className="relative mt-4 grid gap-4 before:absolute before:top-6 before:bottom-6 before:left-5 before:w-px before:bg-line-200 sm:before:left-6">
                        {events.data.map((event) => {
                            const EventIcon = eventIcon(event.domain);
                            const hasChange = event.change.before || event.change.after;
                            const hasPayload = Object.keys(event.payload).length > 0;

                            return (
                                <li key={event.id} className="relative pl-12 sm:pl-16">
                                    <span className={cn('absolute top-5 left-1.5 z-10 grid size-8 place-items-center rounded-full border-4 border-canvas-50 sm:left-2.5', event.result === 'FAILED' ? 'bg-red-700 text-white' : 'bg-navy-950 text-white')}>
                                        <EventIcon aria-hidden="true" className="size-3.5" />
                                    </span>
                                    <article className="panel overflow-hidden">
                                        <div className="flex flex-col gap-4 border-b border-line-200 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="text-sm font-bold text-ink-950">{event.event_label}</h3>
                                                    <StatusBadge status={event.result} />
                                                    <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">{event.source_channel}</span>
                                                </div>
                                                <p className="mt-1 break-words text-[11px] font-semibold text-primary-700">{event.event_type}</p>
                                            </div>
                                            <time dateTime={event.created_at || undefined} className="shrink-0 text-xs font-semibold text-slate-600 tabular">{formatDate(event.created_at, true)}</time>
                                        </div>

                                        <div className="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-5 lg:grid-cols-3 xl:grid-cols-6">
                                            <div className="flex min-w-0 gap-2.5"><CircleUserRound aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-slate-400" /><div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Actor</span><b className="mt-1 block truncate text-xs text-slate-800">{event.actor.name}</b></div></div>
                                            <div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Unit</span><b className="mt-1 block truncate text-xs text-slate-800">{event.unit.name}</b><span className="mt-1 block text-[10px] text-muted-600">{event.unit.code || 'Lintas sistem'}</span></div>
                                            <div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Authority</span><b className="mt-1 block truncate text-xs text-slate-800" title={event.authority || undefined}>{event.authority || 'Tidak dicatat'}</b></div>
                                            <div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Object</span>{event.object.url ? <Link href={event.object.url} className="mt-1 flex min-h-7 items-center gap-1 font-bold text-primary-700 hover:underline">{event.object.label}<ArrowRight aria-hidden="true" className="size-3.5" /></Link> : <b className="mt-1 block text-xs text-slate-800">{event.object.label}</b>}<span className="mt-1 block overflow-hidden text-[10px] text-ellipsis whitespace-nowrap text-muted-600 font-mono" title={event.object.id || undefined}>{event.object.id || '—'}</span><span className="mt-1 block text-[10px] text-muted-600">Versi {event.object.version || '—'}</span></div>
                                            <div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Evidence terkait</span><b className="mt-1 block text-xs text-slate-800">{event.evidence_references.length} referensi</b><span className="mt-1 block truncate text-[10px] font-mono text-muted-600" title={event.evidence_references.join(', ') || undefined}>{event.evidence_references[0] || 'Tidak terkait'}</span></div>
                                            <div className="min-w-0"><span className="block text-[10px] font-bold text-muted-600">Lokasi / perangkat</span><b className="mt-1 block text-xs text-slate-800">{event.location.ip_address || 'Tidak dicatat'}</b><span className="mt-1 block truncate text-[10px] text-muted-600" title={event.location.user_agent || undefined}>{event.location.user_agent || 'Tanpa user agent'}</span></div>
                                        </div>

                                        {(hasChange || event.reason) && (
                                            <div className="grid gap-3 border-t border-line-200 bg-slate-50/70 px-4 py-4 sm:px-5 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]">
                                                {hasChange && <div><span className="text-[10px] font-bold text-muted-600">Perubahan status</span><div className="mt-2 flex flex-wrap items-center gap-2"><StatusBadge status={event.change.before || 'NONE'} /><ArrowRight aria-hidden="true" className="size-4 text-slate-400" /><StatusBadge status={event.change.after || 'NONE'} /></div></div>}
                                                {event.reason && <div><span className="text-[10px] font-bold text-muted-600">Alasan / catatan</span><p className="mt-2 text-xs leading-5 text-slate-700">{event.reason}</p></div>}
                                            </div>
                                        )}

                                        <div className="border-t border-line-200 px-4 py-3 sm:px-5">
                                            <div className="grid gap-1 text-[10px] text-muted-600 sm:grid-cols-2 xl:grid-cols-4">
                                                <span className="min-w-0 truncate font-mono" title={event.event_id || undefined}>Event · {event.event_id || '—'}</span>
                                                <span className="min-w-0 truncate font-mono" title={event.correlation_id || undefined}>Correlation · {event.correlation_id || '—'}</span>
                                                <span className="min-w-0 truncate font-mono" title={event.request_id || undefined}>Request · {event.request_id || '—'}</span>
                                                <span className="min-w-0 truncate font-mono">Payload schema · v{event.payload_version}</span>
                                            </div>
                                            {hasPayload && (
                                                <details className="mt-3">
                                                    <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 text-xs font-bold text-primary-700"><Braces aria-hidden="true" className="size-4" />Lihat metadata tersanitasi</summary>
                                                    <pre className="max-h-80 overflow-auto rounded-lg border border-slate-200 bg-navy-950 p-4 text-[11px] leading-5 whitespace-pre-wrap text-slate-100">{JSON.stringify(event.payload, null, 2)}</pre>
                                                </details>
                                            )}
                                        </div>
                                    </article>
                                </li>
                            );
                        })}
                    </ol>
                ) : (
                    <div className="panel mt-4"><EmptyState title="Jejak audit tidak ditemukan" description="Ubah filter, rentang tanggal, atau unit untuk memperluas hasil investigasi." /></div>
                )}
                <div className="panel mt-4 overflow-hidden"><Pagination links={events.links} /></div>
            </section>
        </>
    );
}
