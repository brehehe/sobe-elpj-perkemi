import { AdministrationUnitForm, type UnitOption, type UnitScope } from '@/components/administration-unit-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateUnit({ scope, options }: { scope: UnitScope; options: UnitOption[] }) {
    return <><Head title="Tambah unit" /><PageHeader eyebrow={`Administrasi · ${scope.label}`} title="Tambah unit organisasi" description="Struktur induk dan tingkat organisasi divalidasi sebelum unit disimpan." actions={<Link href="/administration/units" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link>} /><AdministrationUnitForm scope={scope} options={options} /></>;
}
