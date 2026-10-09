import { BudgetForm, type BudgetFormValue } from '@/components/budget-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditBudget({ budget }: { budget: BudgetFormValue }) {
    return <><Head title={`Revisi ${budget.code}`} /><PageHeader eyebrow="Revisi terversi" title={`Perbaiki ${budget.code}`} description="Versi lama tidak ditimpa; perubahan akan disimpan sebagai versi RAB baru." actions={<Link href={`/budgets/${budget.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Batal</Link>} /><BudgetForm plans={[]} budget={budget} /></>;
}
