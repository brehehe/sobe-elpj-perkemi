import { BudgetForm, type BudgetPlanOption } from '@/components/budget-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateBudget({ plans, selectedPlanId }: { plans: BudgetPlanOption[]; selectedPlanId?: string }) {
    return <><Head title="Buat RAB" /><PageHeader eyebrow="Planning diajukan → RAB" title="Buat dan ajukan RAB" description="Pilih Planning, masukkan rincian biaya, lalu ajukan sebagai versi pertama." actions={<Link href="/budgets" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link>} /><BudgetForm plans={plans} selectedPlanId={selectedPlanId} /></>;
}
