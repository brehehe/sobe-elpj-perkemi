import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Power } from 'lucide-react';
import { useState } from 'react';

type Catalog = { id: string; code: string; name: string; definition: string; unit: string; formula: string; default_weight: string; is_active: boolean; activity_taxonomy: { id: string; code: string; name: string } | null };
export default function ShowKpi({ catalog, canUpdate, canDelete }: { catalog: Catalog; canUpdate: boolean; canDelete: boolean }) {
    const [confirming, setConfirming] = useState(false); const [processing, setProcessing] = useState(false);
    return <><Head title={catalog.name} /><PageHeader eyebrow={catalog.code} title={catalog.name} description={catalog.definition} actions={<><Link href="/administration/kpis" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Daftar</Link>{canUpdate && <Link href={`/administration/kpis/${catalog.id}/edit`} className="btn-secondary"><Pencil aria-hidden="true" className="size-4" />Ubah</Link>}{canDelete && <button type="button" className="btn-danger" onClick={() => setConfirming(true)}><Power aria-hidden="true" className="size-4" />Nonaktifkan</button>}</>} />
        <section className="panel mt-6 grid gap-px overflow-hidden bg-line-200 sm:grid-cols-2 lg:grid-cols-4"><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Status</span><div className="mt-2"><StatusBadge status={catalog.is_active ? 'ACTIVE' : 'INACTIVE'} /></div></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Satuan</span><p className="mt-2 text-sm font-bold">{catalog.unit}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Arah formula</span><p className="mt-2 text-sm font-bold">{catalog.formula === 'HIGHER_IS_BETTER' ? 'Semakin tinggi semakin baik' : 'Semakin rendah semakin baik'}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Bobot default</span><p className="mt-2 text-sm font-bold tabular">{catalog.default_weight}</p></div></section>
        <section className="panel mt-6 p-5"><h2 className="text-base font-bold">Cakupan kegiatan</h2><p className="mt-2 text-sm text-muted-600">{catalog.activity_taxonomy ? <Link href={`/administration/taxonomies/${catalog.activity_taxonomy.id}`} className="font-semibold text-primary-700 hover:underline">{catalog.activity_taxonomy.code} · {catalog.activity_taxonomy.name}</Link> : 'Berlaku untuk semua jenis kegiatan.'}</p></section>
        <ConfirmDialog open={confirming} title="Nonaktifkan template KPI?" description="Template tidak lagi tersedia untuk Planning baru. Semua snapshot KPI pada Planning lama tetap utuh." confirmLabel="Nonaktifkan" destructive processing={processing} onClose={() => setConfirming(false)} onConfirm={() => { setProcessing(true); router.delete(`/administration/kpis/${catalog.id}`, { onFinish: () => setProcessing(false) }); }} />
    </>;
}
