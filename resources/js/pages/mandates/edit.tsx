import { MandateForm, type MandateFormValue } from '@/components/mandate-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditMandate({ mandate }: { mandate: MandateFormValue }) {
    return <><Head title={`Ubah ${mandate.number}`} /><PageHeader eyebrow="Mandat · perubahan tercatat" title="Ubah mandat" description="Perubahan metadata dicatat pada audit trail. Planning yang telah menjadi snapshot tidak ikut ditimpa." actions={<Link href={`/mandates/${mandate.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Detail mandat</Link>} /><MandateForm mandate={mandate} /></>;
}
