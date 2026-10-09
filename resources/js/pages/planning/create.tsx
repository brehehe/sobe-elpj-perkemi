import { PageHeader } from '@/components/page-header';
import { PlanForm, type ActivityTypeOption, type KpiCatalogOption, type MandateOption } from '@/components/plan-form';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreatePlanning({ mandates, activityTypes, kpiCatalogs, selectedMandateId }: { mandates: MandateOption[]; activityTypes: ActivityTypeOption[]; kpiCatalogs: KpiCatalogOption[]; selectedMandateId?: string | null }) {
    return <><Head title="Buat Planning" /><PageHeader eyebrow="Mandat → Planning" title="Buat Planning kegiatan" description="Tetapkan tujuan, output, outcome, KPI, jadwal, sumber daya, dan risiko. Pengajuan membentuk versi immutable." actions={<Link href="/planning" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Daftar Planning</Link>} /><PlanForm mandates={mandates} activityTypes={activityTypes} kpiCatalogs={kpiCatalogs} selectedMandateId={selectedMandateId} /></>;
}
