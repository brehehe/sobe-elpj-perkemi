import { AdministrationUserForm, type ManagedUserValue, type RoleOption, type UnitOption, type UserFormScope } from '@/components/administration-user-form';
import { PageHeader } from '@/components/page-header';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function EditUser({ managedUser, scope, options }: { managedUser: ManagedUserValue; scope: UserFormScope; options: { units: UnitOption[]; roles: RoleOption[] } }) {
    return <><Head title={`Ubah ${managedUser.name}`} /><PageHeader eyebrow="Administrasi akses" title={`Ubah ${managedUser.name}`} description="Perubahan unit, role, status, dan email dicatat lengkap pada audit trail." actions={<Link href={`/administration/users/${managedUser.id}`} className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Batal</Link>} /><AdministrationUserForm managedUser={managedUser} scope={scope} units={options.units} roles={options.roles} /></>;
}
