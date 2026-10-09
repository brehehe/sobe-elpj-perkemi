import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatCurrency } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type BudgetRow = {
    id: string; code: string; currency: string; status: string; current_version: number;
    owner: { name: string }; unit: { code: string; name: string };
    plan: { id: string; code: string; status: string; current_version_record: { activity_type: string; objective: string } };
    current_version_record: { total: string; lines: unknown[] };
    can_update: boolean; can_delete: boolean;
};

export default function BudgetIndex({ budgets, canCreate }: { budgets: Paginated<BudgetRow>; canCreate: boolean }) {
    const [selected, setSelected] = useState<BudgetRow | null>(null);
    const [processing, setProcessing] = useState(false);

    return <><Head title="RAB" /><PageHeader eyebrow="Planning → RAB terversi" title="Rencana anggaran biaya" description="Register RAB ditampilkan sebagai tabel. Total dihitung server dan setiap revisi membentuk versi immutable." actions={canCreate && <Link href="/budgets/create" className="btn-primary"><Plus aria-hidden="true" className="size-4" />Buat RAB</Link>} />
        <section className="panel mt-6 overflow-hidden"><div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Register RAB</h2><p className="mt-1 text-xs text-muted-600">{budgets.total} RAB dalam lingkup kewenangan Anda.</p></div>
            {budgets.data.length ? <div className="overflow-x-auto" role="region" aria-label="Daftar RAB yang dapat digulir" tabIndex={0}><table className="w-full min-w-[1040px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-600"><tr><th className="px-5 py-3">Kode RAB</th><th className="px-4 py-3">Planning sumber</th><th className="px-4 py-3">Unit / owner</th><th className="px-4 py-3">Baris</th><th className="px-4 py-3 text-right">Total</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody>{budgets.data.map((budget) => <tr key={budget.id} className="border-t border-line-200 hover:bg-slate-50/70"><td className="px-5 py-4"><Link href={`/budgets/${budget.id}`} className="font-bold text-primary-700 hover:underline">{budget.code}</Link><span className="mt-1 block text-xs text-muted-600">v{budget.current_version} · {budget.currency}</span></td><td className="max-w-md px-4 py-4"><Link href={`/planning/${budget.plan.id}`} className="font-semibold text-primary-700 hover:underline">{budget.plan.code}</Link><span className="mt-1 block truncate text-xs text-muted-600">{budget.plan.current_version_record.activity_type} · {budget.plan.status}</span></td><td className="px-4 py-4"><b className="block text-xs">{budget.unit.code}</b><span className="text-xs text-muted-600">{budget.owner.name}</span></td><td className="px-4 py-4 tabular">{budget.current_version_record.lines.length}</td><td className="px-4 py-4 text-right font-bold tabular">{formatCurrency(Number(budget.current_version_record.total))}</td><td className="px-4 py-4"><StatusBadge status={budget.status} /></td><td className="px-4 py-4"><div className="flex justify-end gap-1"><Link href={`/budgets/${budget.id}`} aria-label={`Lihat ${budget.code}`} className="grid size-11 place-items-center rounded-lg text-primary-700 hover:bg-blue-50"><Eye aria-hidden="true" className="size-4" /></Link>{budget.can_update && ['DRAFT', 'RETURNED'].includes(budget.status) && <Link href={`/budgets/${budget.id}/edit`} aria-label={`Ubah ${budget.code}`} className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100"><Pencil aria-hidden="true" className="size-4" /></Link>}{budget.can_delete && <button type="button" aria-label={`Hapus ${budget.code}`} onClick={() => setSelected(budget)} className="grid size-11 place-items-center rounded-lg text-danger-600 hover:bg-red-50"><Trash2 aria-hidden="true" className="size-4" /></button>}</div></td></tr>)}</tbody></table></div> : <EmptyState title="Belum ada RAB" description="Susun RAB dari Planning yang sudah diajukan atau disetujui." />}
            <Pagination links={budgets.links} />
        </section>
        <ConfirmDialog open={selected !== null} title="Hapus RAB?" description={<>RAB <b>{selected?.code}</b> akan di-soft-delete. Riwayat versi tetap dipertahankan dan penghapusan diblokir bila sudah dipakai kegiatan.</>} confirmLabel="Hapus RAB" destructive processing={processing} onClose={() => setSelected(null)} onConfirm={() => { if (!selected) return; setProcessing(true); router.delete(`/budgets/${selected.id}`, { onFinish: () => { setProcessing(false); setSelected(null); } }); }} />
    </>;
}
