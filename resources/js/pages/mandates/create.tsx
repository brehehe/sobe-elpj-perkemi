import { MandateForm } from '@/components/mandate-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateMandate() {
    return <><Head title="Buat mandat" /><PageHeader eyebrow="Mandat → Planning" title="Registrasi mandat" description="Daftarkan sumber kewenangan kegiatan. Mandat aktif akan langsung tersedia pada formulir Planning." actions={<Link href="/mandates" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Daftar mandat</Link>} /><MandateForm /></>;
}
