import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { ActivitySummary, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function ActivitiesIndex({ activities, canCreate }: { activities: Paginated<ActivitySummary>; canCreate: boolean }) {
    const [selected, setSelected] = useState<ActivitySummary | null>(null);
    const [processing, setProcessing] = useState(false);
    return <>
        <Head title="Kegiatan" />
        <PageHeader eyebrow="Activity-ID sebagai anchor" title="Kegiatan" description="Mandat, rencana, anggaran, peserta, transaksi, bukti, evaluasi, dan LPJ bertemu pada satu identitas kegiatan." actions={canCreate && <Link href="/activities/create" className="btn-primary"><Plus className="size-4" />Buat kegiatan</Link>} />
        <section className="panel mt-6 overflow-hidden">
            {activities.data.length ? <div className="overflow-x-auto"><table className="w-full min-w-[980px] text-left text-xs"><thead className="bg-slate-50 text-[10px] font-bold text-slate-500"><tr><th className="px-5 py-3">Activity-ID</th><th className="px-4 py-3">Kegiatan</th><th className="px-4 py-3">Mandat</th><th className="px-4 py-3">Waktu</th><th className="px-4 py-3">Pagu</th><th className="px-4 py-3">Kelengkapan</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody>{activities.data.map((activity) => <tr key={activity.id} className="border-t border-slate-100 hover:bg-slate-50/70"><td className="px-5 py-4"><Link href={`/activities/${activity.id}`} className="tabular font-bold text-primary-700 hover:underline">{activity.code}</Link></td><td className="max-w-72 px-4 py-4"><b className="block truncate text-slate-800">{activity.title}</b><small className="mt-1 block text-[10px] text-muted-600">PIC · {activity.pic?.name || 'Belum ditetapkan'}</small></td><td className="max-w-52 px-4 py-4"><span className="block truncate">{activity.mandate?.number || '—'}</span></td><td className="px-4 py-4">{formatDate(activity.starts_at)}</td><td className="tabular px-4 py-4 font-semibold">{formatCurrency(activity.budget_ceiling)}</td><td className="px-4 py-4"><div className="flex gap-1.5 text-[10px] text-slate-600"><span className="rounded bg-slate-100 px-1.5 py-1">P {activity.participants_count || 0}</span><span className="rounded bg-slate-100 px-1.5 py-1">T {activity.transactions_count || 0}</span><span className="rounded bg-slate-100 px-1.5 py-1">B {activity.evidence_count || 0}</span></div></td><td className="px-4 py-4"><StatusBadge status={activity.lpj?.status || activity.status} /></td><td className="px-4 py-4"><div className="flex justify-end gap-1"><Link href={`/activities/${activity.id}`} aria-label={`Buka ${activity.code}`} className="grid size-11 place-items-center rounded-lg text-primary-700 hover:bg-blue-50"><Eye className="size-4" /></Link>{activity.can_update && <Link href={`/activities/${activity.id}/edit`} aria-label={`Ubah ${activity.code}`} className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100"><Pencil className="size-4" /></Link>}{activity.can_delete && <button type="button" aria-label={`Hapus ${activity.code}`} onClick={() => setSelected(activity)} className="grid size-11 place-items-center rounded-lg text-danger-600 hover:bg-red-50"><Trash2 className="size-4" /></button>}</div></td></tr>)}</tbody></table></div> : <EmptyState title="Belum ada kegiatan" description="Buat kegiatan pertama dari mandat aktif untuk memulai garis data LPJ." />}
            <Pagination links={activities.links} />
        </section>
        <ConfirmDialog open={selected !== null} title="Hapus kegiatan draft?" description={<>Kegiatan <b>{selected?.code}</b> dan LPJ draft kosongnya akan dihapus. Tindakan diblokir jika pelaksanaan atau data sumber sudah tercatat.</>} confirmLabel="Hapus kegiatan" destructive processing={processing} onClose={() => setSelected(null)} onConfirm={() => { if (!selected) return; setProcessing(true); router.delete(`/activities/${selected.id}`, { onFinish: () => { setProcessing(false); setSelected(null); } }); }} />
    </>;
}
import { ConfirmDialog } from '@/components/confirm-dialog';
