import { AdministrationUserForm, type RoleOption, type UnitOption, type UserFormScope } from '@/components/administration-user-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function CreateUser({ scope, options }: { scope: UserFormScope; options: { units: UnitOption[]; roles: RoleOption[] } }) {
    return <><Head title="Tambah pengguna" /><PageHeader eyebrow="Administrasi akses" title="Tambah pengguna" description="Akun dibuat tanpa password yang dibagikan; sistem mengirimkan tautan verifikasi dan atur password." actions={<Link href="/administration/users" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Kembali</Link>} /><AdministrationUserForm scope={scope} units={options.units} roles={options.roles} /></>;
}
