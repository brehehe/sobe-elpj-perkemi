import { PageHeader } from '@/components/page-header';
import { PlanForm, type ActivityTypeOption, type KpiCatalogOption, type MandateOption, type PlanFormValue } from '@/components/plan-form';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditPlanning({ plan, mandates, activityTypes, kpiCatalogs }: { plan: PlanFormValue; mandates: MandateOption[]; activityTypes: ActivityTypeOption[]; kpiCatalogs: KpiCatalogOption[] }) {
    return <><Head title={`Ubah ${plan.code}`} /><PageHeader eyebrow={`Planning ${plan.code} · v${plan.current_version}`} title="Ajukan versi Planning baru" description="Versi sebelumnya tetap immutable. Jelaskan alasan perubahan agar reviewer dapat membandingkan versi." actions={<Link href={`/planning/${plan.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Detail Planning</Link>} /><PlanForm plan={plan} mandates={mandates} activityTypes={activityTypes} kpiCatalogs={kpiCatalogs} /></>;
}
