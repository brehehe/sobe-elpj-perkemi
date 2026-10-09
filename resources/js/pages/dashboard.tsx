import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { LpjSummary } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Bot, CalendarClock, Check, CircleDollarSign, FileClock, GitCompareArrows, ListTodo, Plus, ShieldCheck, Waypoints } from 'lucide-react';

type Task = { id: string; type: string; title: string; description?: string; priority: string; status: string; due_at?: string; activity?: { id: string; code: string; title: string } };
type Upcoming = { id: string; code: string; title: string; starts_at: string; status: string };
type IntelligenceInsight = {
    id: string;
    type: string;
    model: string | null;
    engine: 'OPENAI' | 'RULE_BASED';
    human_review_status: string;
    summary: string | null;
    recommendation: string | null;
    confidence: number | null;
    activity: { id: string; code: string; title: string };
};

const workflow = [
    ['DRAFT', 'Draf'], ['IN_VERIFICATION', 'Verifikasi'], ['FINDING_OPEN', 'Temuan'],
    ['READY_FOR_APPROVAL', 'Persetujuan'], ['APPROVED', 'Disetujui'], ['PB_VERIFIED', 'PB verified'], ['ARCHIVED', 'Arsip'],
];

export default function Dashboard({ metrics, pipeline, tasks, recentLpjs, upcomingActivities, canViewIntelligence, intelligenceBriefs, nextActivityRecommendations }: {
    metrics: { activities: number; lpjs_in_process: number; open_findings: number; accounted_amount: number };
    pipeline: Record<string, number>;
    tasks: Task[];
    recentLpjs: LpjSummary[];
    upcomingActivities: Upcoming[];
    canViewIntelligence: boolean;
    intelligenceBriefs: IntelligenceInsight[];
    nextActivityRecommendations: IntelligenceInsight[];
}) {
    return <>
        <Head title="Dashboard" />
        <PageHeader eyebrow="Ruang kendali" title="Akuntabilitas kegiatan hari ini" description="Pantau tugas, status LPJ, pengecualian, dan garis data lintas proses berdasarkan unit serta kewenangan Anda." actions={<Link href="/activities/create" className="btn-primary"><Plus className="size-4" />Buat kegiatan</Link>} />

        <section aria-label="Ringkasan" className="mt-6 grid overflow-hidden rounded-xl border border-line-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
            {[
                { label: 'Kegiatan', value: metrics.activities, detail: 'dalam ruang lingkup', icon: CalendarClock, tone: 'text-primary-600' },
                { label: 'LPJ diproses', value: metrics.lpjs_in_process, detail: 'di luar draf & arsip', icon: FileClock, tone: 'text-blue-600' },
                { label: 'Temuan terbuka', value: metrics.open_findings, detail: 'memerlukan tindak lanjut', icon: AlertTriangle, tone: 'text-danger-600' },
                { label: 'Nilai dipertanggungjawabkan', value: formatCurrency(metrics.accounted_amount), detail: 'berdasarkan transaksi sumber', icon: CircleDollarSign, tone: 'text-success-600' },
            ].map((item, index) => <article key={item.label} className={`relative p-5 ${index > 0 ? 'border-t border-line-200 sm:border-t-0 sm:border-l' : ''} ${index === 2 ? 'sm:border-l-0 xl:border-l' : ''}`}>
                <div className="flex items-center justify-between gap-3"><p className="text-xs font-semibold text-muted-600">{item.label}</p><item.icon className={`size-5 ${item.tone}`} /></div><strong className="tabular mt-3 block text-2xl font-bold tracking-[-0.04em] text-ink-950">{item.value}</strong><span className="mt-1 block text-[11px] text-muted-600">{item.detail}</span>
            </article>)}
        </section>

        <section className="panel mt-5 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-base font-bold text-ink-950">Jalur pertanggungjawaban</h2><p className="mt-1 text-xs text-muted-600">Jumlah LPJ pada gerbang utama workflow.</p></div><Link href="/lpjs" className="flex min-h-9 items-center gap-1 text-xs font-bold text-primary-700 hover:underline">Lihat seluruh LPJ<ArrowRight className="size-4" /></Link></div>
            <div tabIndex={0} role="region" aria-label="Ringkasan status jalur pertanggungjawaban" className="mt-5 flex overflow-x-auto pb-2">
                {workflow.map(([status, label], index) => <div key={status} className="flex min-w-0 flex-1 items-start">
                    <div className="flex min-w-24 flex-col items-center text-center"><span className={`grid size-9 place-items-center rounded-full border-2 text-xs font-bold ${Number(pipeline[status] || 0) > 0 ? 'border-primary-600 bg-blue-50 text-primary-700 ring-4 ring-blue-50' : status === 'ARCHIVED' ? 'border-success-600 bg-success-600 text-white' : 'border-slate-200 bg-white text-slate-400'}`}>{status === 'ARCHIVED' ? <Check className="size-4" /> : pipeline[status] || 0}</span><b className="mt-2 text-[10px] text-slate-700">{label}</b></div>{index < workflow.length - 1 && <span className="mt-[17px] h-0.5 min-w-5 flex-1 bg-slate-200" />}
                </div>)}
            </div>
        </section>

        {canViewIntelligence && <section aria-label="Ringkasan intelligence" className="mt-5 grid gap-5 xl:grid-cols-2">
            <div className="panel overflow-hidden">
                <div className="flex items-start justify-between gap-3 border-b border-line-200 bg-slate-50/70 px-5 py-4"><div><h2 className="text-base font-bold text-ink-950">AI Accountability Brief</h2><p className="mt-1 text-xs text-muted-600">Hasil analisis terbaru sesuai scope; seluruh output tetap advisory.</p></div><Bot aria-hidden="true" className="size-5 text-primary-700" /></div>
                {intelligenceBriefs.length ? <div className="divide-y divide-line-200">{intelligenceBriefs.map((insight) => <article key={insight.id} className="p-5"><div className="flex flex-wrap items-center gap-2"><span className="inline-flex items-center gap-1 text-[10px] font-bold text-primary-700">{insight.engine === 'OPENAI' ? <Bot aria-hidden="true" className="size-3.5" /> : <GitCompareArrows aria-hidden="true" className="size-3.5" />}{insight.type.replaceAll('_', ' ')}</span><StatusBadge status={insight.human_review_status} /></div><Link href="/intelligence" className="mt-2 block text-sm font-bold text-ink-950 hover:text-primary-700 hover:underline">{insight.activity.code} · {insight.activity.title}</Link><p className="mt-2 text-xs leading-5 text-slate-600">{insight.summary || 'Ringkasan belum tersedia.'}</p><p className="mt-2 text-[10px] font-semibold text-muted-600">{insight.engine === 'OPENAI' ? insight.model : 'Rule-based fallback'} · confidence {insight.confidence === null ? '—' : `${insight.confidence}%`}</p></article>)}</div> : <EmptyState title="Belum ada brief" description="Jalankan pemeriksaan pendukung pada Intelligence Center." />}
                <div className="border-t border-line-200 p-4"><Link href="/intelligence" className="flex min-h-9 items-center justify-center gap-1 text-xs font-bold text-primary-700 hover:underline">Buka Intelligence Center<ArrowRight aria-hidden="true" className="size-4" /></Link></div>
            </div>

            <div className="panel overflow-hidden">
                <div className="flex items-start justify-between gap-3 border-b border-line-200 bg-slate-50/70 px-5 py-4"><div><h2 className="text-base font-bold text-ink-950">Rekomendasi kegiatan berikutnya</h2><p className="mt-1 text-xs text-muted-600">Berbasis cohort LPJ terverifikasi dan tetap menunggu keputusan manusia.</p></div><Waypoints aria-hidden="true" className="size-5 text-primary-700" /></div>
                {nextActivityRecommendations.length ? <div className="divide-y divide-line-200">{nextActivityRecommendations.map((insight) => <article key={insight.id} className="p-5"><div className="flex flex-wrap items-center gap-2"><span className="text-[10px] font-bold text-primary-700">{insight.activity.code}</span><StatusBadge status={insight.human_review_status} /></div><p className="mt-2 text-sm font-bold leading-6 text-ink-950">{insight.summary || 'Rekomendasi belum tersedia.'}</p>{insight.recommendation && <p className="mt-2 text-xs leading-5 text-slate-600">{insight.recommendation}</p>}</article>)}</div> : <EmptyState title="Belum ada rekomendasi" description="Bangun cohort sejenis yang eligible untuk membuat paket rekomendasi." />}
                <div className="border-t border-line-200 p-4"><Link href="/next-activity" className="flex min-h-9 items-center justify-center gap-1 text-xs font-bold text-primary-700 hover:underline">Analisis kegiatan berikutnya<ArrowRight aria-hidden="true" className="size-4" /></Link></div>
            </div>
        </section>}

        <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,.7fr)]">
            <section className="panel overflow-hidden">
                <div className="flex items-start justify-between gap-3 border-b border-line-200 px-5 py-4"><div><h2 className="text-base font-bold">LPJ terbaru</h2><p className="mt-1 text-xs text-muted-600">Versi dan status terkini per Activity-ID.</p></div><ShieldCheck className="size-5 text-primary-600" /></div>
                {recentLpjs.length ? <div className="overflow-x-auto"><table className="w-full text-left text-xs"><thead className="bg-slate-50 text-[10px] font-bold text-slate-500"><tr><th className="px-5 py-3">Activity-ID</th><th className="px-4 py-3">Kegiatan</th><th className="px-4 py-3">Versi</th><th className="px-4 py-3">Nilai</th><th className="px-4 py-3">Status</th></tr></thead><tbody>{recentLpjs.map((lpj) => <tr key={lpj.id} className="border-t border-slate-100 hover:bg-slate-50/70"><td className="px-5 py-3.5"><Link href={`/lpjs/${lpj.id}`} className="font-bold text-primary-700 hover:underline">{lpj.activity.code}</Link></td><td className="max-w-64 px-4 py-3.5"><b className="block truncate text-slate-800">{lpj.activity.title}</b><small className="text-[10px] text-muted-600">{lpj.owner.name}</small></td><td className="tabular px-4 py-3.5">v{lpj.current_version || '—'}</td><td className="tabular px-4 py-3.5">{formatCurrency(lpj.total_amount)}</td><td className="px-4 py-3.5"><StatusBadge status={lpj.status} /></td></tr>)}</tbody></table></div> : <EmptyState />}
            </section>

            <section className="panel overflow-hidden">
                <div className="flex items-start justify-between gap-3 border-b border-line-200 px-5 py-4"><div><h2 className="text-base font-bold">Perlu perhatian</h2><p className="mt-1 text-xs text-muted-600">Tugas berdasarkan prioritas dan tenggat.</p></div><ListTodo className="size-5 text-warning-600" /></div>
                {tasks.length ? <div>{tasks.map((task) => <Link key={task.id} href={task.activity ? `/activities/${task.activity.id}` : '/dashboard'} className="flex min-h-18 items-start gap-3 border-b border-slate-100 px-5 py-3.5 hover:bg-slate-50"><span className={`mt-1 h-8 w-1 rounded-full ${task.priority === 'CRITICAL' || task.priority === 'HIGH' ? 'bg-danger-600' : task.priority === 'MEDIUM' ? 'bg-warning-600' : 'bg-primary-600'}`} /><span className="min-w-0 flex-1"><b className="block truncate text-xs text-slate-800">{task.title}</b><small className="mt-1 block truncate text-[10px] text-muted-600">{task.activity?.code || task.type} · {task.due_at ? formatDate(task.due_at) : 'Tanpa tenggat'}</small></span><ArrowRight className="mt-1 size-4 shrink-0 text-slate-400" /></Link>)}</div> : <EmptyState title="Tidak ada tugas terbuka" description="Semua tindak lanjut pada ruang lingkup Anda sudah selesai." />}
            </section>
        </div>

        <section className="panel mt-5 p-5"><div className="flex items-center justify-between gap-3"><div><h2 className="text-base font-bold">Kegiatan mendatang</h2><p className="mt-1 text-xs text-muted-600">Rencana terdekat yang akan memasuki fase pelaksanaan.</p></div><Link href="/activities" className="text-xs font-bold text-primary-700 hover:underline">Buka kegiatan</Link></div><div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">{upcomingActivities.length ? upcomingActivities.map((activity) => <Link href={`/activities/${activity.id}`} key={activity.id} className="rounded-lg border border-line-200 p-3.5 transition hover:border-blue-300 hover:bg-blue-50/40"><span className="text-[10px] font-bold text-primary-700">{activity.code}</span><b className="mt-1 block text-xs leading-5 text-slate-800">{activity.title}</b><span className="mt-3 block text-[10px] text-muted-600">{formatDate(activity.starts_at)}</span></Link>) : <p className="col-span-full py-4 text-xs text-muted-600">Belum ada kegiatan mendatang.</p>}</div></section>
    </>;
}
