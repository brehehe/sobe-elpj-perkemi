import { AdministrationUnitForm, type UnitOption, type UnitScope } from '@/components/administration-unit-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditUnit({ unit, scope, options }: { unit: UnitOption; scope: UnitScope; options: UnitOption[] }) {
    return <><Head title={`Ubah ${unit.name}`} /><PageHeader eyebrow="Administrasi · struktur" title={`Ubah ${unit.name}`} description="Perubahan induk, tingkat, dan status diperiksa terhadap scope serta dependensi aktif." actions={<Link href={`/administration/units/${unit.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Batal</Link>} /><AdministrationUnitForm unit={unit} scope={scope} options={options} /></>;
}
