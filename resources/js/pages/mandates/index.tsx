import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type MandateRow = {
    id: string; number: string; title: string; issued_at: string; expires_at: string | null; status: string;
    unit: { code: string; name: string } | null; creator: { name: string } | null;
    plans_count: number; activities_count: number; can_update: boolean; can_delete: boolean;
};

export default function MandateIndex({ mandates, canCreate }: { mandates: Paginated<MandateRow>; canCreate: boolean }) {
    const [selected, setSelected] = useState<MandateRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const destroy = () => {
        if (!selected) return;
        setProcessing(true);
        router.delete(`/mandates/${selected.id}`, { onFinish: () => { setProcessing(false); setSelected(null); } });
    };

    return <><Head title="Mandat" /><PageHeader eyebrow="Sumber kewenangan kegiatan" title="Mandat & dasar kegiatan" description="Register mandat ditampilkan sebagai tabel. Pilih detail untuk melihat lineage Planning dan Activity-ID." actions={canCreate && <Link href="/mandates/create" className="btn-primary"><Plus aria-hidden="true" className="size-4" />Registrasi mandat</Link>} />
        <section className="panel mt-6 overflow-hidden" aria-labelledby="mandate-table-title">
            <div className="border-b border-line-200 px-5 py-4"><h2 id="mandate-table-title" className="text-base font-bold">Register mandat</h2><p className="mt-1 text-xs text-muted-600">{mandates.total} mandat dalam lingkup kewenangan Anda.</p></div>
            {mandates.data.length ? <div className="overflow-x-auto" role="region" aria-label="Daftar mandat yang dapat digulir" tabIndex={0}><table className="w-full min-w-[980px] text-left text-sm"><thead className="bg-slate-50 text-xs text-slate-600"><tr><th className="px-5 py-3">Nomor & judul</th><th className="px-4 py-3">Unit</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Lineage</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody>{mandates.data.map((mandate) => <tr key={mandate.id} className="border-t border-line-200 hover:bg-slate-50/70"><td className="max-w-md px-5 py-4"><Link href={`/mandates/${mandate.id}`} className="font-bold text-primary-700 hover:underline">{mandate.number}</Link><span className="mt-1 block text-xs text-muted-600">{mandate.title}</span></td><td className="px-4 py-4"><b className="block text-xs">{mandate.unit?.code ?? '—'}</b><span className="text-xs text-muted-600">{mandate.unit?.name ?? 'Tanpa unit'}</span></td><td className="px-4 py-4 text-xs"><span className="block">Terbit {formatDate(mandate.issued_at)}</span><span className="text-muted-600">Berlaku {mandate.expires_at ? formatDate(mandate.expires_at) : 'tanpa batas'}</span></td><td className="px-4 py-4 text-xs"><span className="block">{mandate.plans_count} planning</span><span className="text-muted-600">{mandate.activities_count} kegiatan</span></td><td className="px-4 py-4"><StatusBadge status={mandate.status} /></td><td className="px-4 py-4"><div className="flex justify-end gap-1"><Link href={`/mandates/${mandate.id}`} aria-label={`Lihat ${mandate.number}`} className="grid size-11 place-items-center rounded-lg text-primary-700 hover:bg-blue-50"><Eye aria-hidden="true" className="size-4" /></Link>{mandate.can_update && <Link href={`/mandates/${mandate.id}/edit`} aria-label={`Ubah ${mandate.number}`} className="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100"><Pencil aria-hidden="true" className="size-4" /></Link>}{mandate.can_delete && <button type="button" aria-label={`Hapus ${mandate.number}`} onClick={() => setSelected(mandate)} className="grid size-11 place-items-center rounded-lg text-danger-600 hover:bg-red-50"><Trash2 aria-hidden="true" className="size-4" /></button>}</div></td></tr>)}</tbody></table></div> : <EmptyState title="Belum ada mandat" description="Registrasikan mandat pertama agar Planning dapat dibuat dari sumber kewenangan yang valid." />}
            <Pagination links={mandates.links} />
        </section>
        <ConfirmDialog open={selected !== null} title="Hapus mandat draft?" description={<>Mandat <b>{selected?.number}</b> akan dikeluarkan dari daftar aktif. Tindakan hanya tersedia bila belum memiliki Planning atau kegiatan.</>} confirmLabel="Hapus mandat" destructive processing={processing} onClose={() => setSelected(null)} onConfirm={destroy} />
    </>;
}
