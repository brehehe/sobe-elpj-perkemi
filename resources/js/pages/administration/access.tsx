import { PageHeader } from '@/components/page-header';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, KeyRound, LockKeyhole, Save, ShieldCheck, UsersRound } from 'lucide-react';
import { type FormEvent } from 'react';

type RoleItem = {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
    is_protected: boolean;
};

type PermissionGroup = { domain: string; label: string; permissions: string[] };

type Props = {
    summary: { roles: number; permissions: number; assignments: number };
    can_manage_roles: boolean;
    roles: RoleItem[];
    permission_groups: PermissionGroup[];
};

function PermissionEditor({ role, groups, canManage }: { role: RoleItem; groups: PermissionGroup[]; canManage: boolean }) {
    const form = useForm({ permissions: role.permissions });
    const editable = canManage && !role.is_protected;

    const toggle = (permission: string, checked: boolean) => {
        form.setData('permissions', checked
            ? [...form.data.permissions, permission]
            : form.data.permissions.filter((item) => item !== permission));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/administration/access/roles/${role.id}`, { preserveScroll: true });
    };

    return (
        <details className="border-b border-line-200 last:border-b-0">
            <summary className="grid min-h-20 cursor-pointer list-none gap-3 px-5 py-4 marker:hidden hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                <span className="flex min-w-0 items-center gap-3">
                    <span className={`grid size-10 shrink-0 place-items-center rounded-lg ${role.is_protected ? 'bg-navy-950 text-white' : 'bg-blue-50 text-primary-700'}`}>
                        {role.is_protected ? <LockKeyhole aria-hidden="true" className="size-[18px]" /> : <ShieldCheck aria-hidden="true" className="size-[18px]" />}
                    </span>
                    <span className="min-w-0"><b className="block truncate text-sm text-ink-950">{role.name}</b><span className="mt-1 block text-[11px] text-muted-600">{role.is_protected ? 'Seluruh capability · dikelola sistem' : editable ? 'Dapat diubah oleh Super Admin' : 'Matriks hanya-baca'}</span></span>
                </span>
                <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><UsersRound aria-hidden="true" className="size-4" />{role.users_count} pengguna</span>
                <span className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700">{role.permissions.length} permission</span>
            </summary>

            <form onSubmit={submit} className="border-t border-line-200 bg-slate-50/60 px-5 py-5">
                <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    {groups.map((group) => (
                        <fieldset key={group.domain} className="rounded-xl border border-line-200 bg-white p-3">
                            <legend className="px-1 text-xs font-bold text-ink-950">{group.label}</legend>
                            <div className="mt-1 grid gap-1">
                                {group.permissions.map((permission) => {
                                    const checked = form.data.permissions.includes(permission);
                                    const globallyLocked = permission === 'admin.manage-roles';
                                    return (
                                        <label key={permission} className={`flex min-h-11 items-center gap-3 rounded-lg px-2.5 text-xs ${checked ? 'bg-blue-50 text-primary-700' : 'text-slate-600'} ${editable && !globallyLocked ? 'cursor-pointer hover:bg-slate-50' : 'cursor-default'}`}>
                                            <input
                                                type="checkbox"
                                                className="size-4 shrink-0 accent-primary-600"
                                                checked={checked}
                                                disabled={!editable || globallyLocked}
                                                onChange={(event) => toggle(permission, event.target.checked)}
                                            />
                                            <span className="min-w-0 flex-1 break-words font-mono text-[11px]">{permission}</span>
                                            {checked && <Check aria-hidden="true" className="size-3.5 shrink-0 text-primary-700" />}
                                            {globallyLocked && !role.is_protected && <LockKeyhole aria-hidden="true" className="size-3.5 shrink-0 text-slate-400" />}
                                        </label>
                                    );
                                })}
                            </div>
                        </fieldset>
                    ))}
                </div>

                {form.errors.permissions && <p className="mt-4 text-xs font-semibold text-danger-600">{form.errors.permissions}</p>}
                {editable && (
                    <div className="mt-5 flex flex-col gap-3 border-t border-line-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p className="max-w-2xl text-xs leading-5 text-muted-600">Perubahan berlaku pada seluruh pengguna dengan role ini dan akan direkam sebagai audit event.</p>
                        <button type="submit" disabled={form.processing || !form.isDirty} className="btn-primary shrink-0 disabled:cursor-not-allowed disabled:opacity-50"><Save aria-hidden="true" className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan matriks'}</button>
                    </div>
                )}
            </form>
        </details>
    );
}

export default function AdministrationAccess({ summary, can_manage_roles, roles, permission_groups }: Props) {
    return (
        <>
            <Head title="Role & Permission" />
            <PageHeader
                eyebrow="Administrasi · RBAC"
                title="Role & Permission"
                description="Matriks capability menjadi sumber kewenangan role. Policy dan scope unit tetap menentukan akses terhadap record tertentu."
                actions={<><Link href="/administration" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Administrasi</Link><Link href="/administration/users" className="btn-primary">Kelola pengguna</Link></>}
            />

            <section aria-label="Ringkasan role dan permission" className="panel mt-6 grid divide-y divide-line-200 overflow-hidden sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <div className="flex items-start justify-between gap-4 p-5"><div><p className="text-xs font-semibold text-slate-600">Role</p><p className="mt-2 text-2xl font-black text-ink-950 tabular-nums">{summary.roles}</p><p className="mt-1 text-[11px] text-muted-600">Kelompok tanggung jawab</p></div><ShieldCheck aria-hidden="true" className="size-5 text-primary-700" /></div>
                <div className="flex items-start justify-between gap-4 p-5"><div><p className="text-xs font-semibold text-slate-600">Permission</p><p className="mt-2 text-2xl font-black text-ink-950 tabular-nums">{summary.permissions}</p><p className="mt-1 text-[11px] text-muted-600">Capability terdaftar</p></div><KeyRound aria-hidden="true" className="size-5 text-primary-700" /></div>
                <div className="flex items-start justify-between gap-4 p-5"><div><p className="text-xs font-semibold text-slate-600">Assignment</p><p className="mt-2 text-2xl font-black text-ink-950 tabular-nums">{summary.assignments}</p><p className="mt-1 text-[11px] text-muted-600">Relasi role–permission</p></div><UsersRound aria-hidden="true" className="size-5 text-primary-700" /></div>
            </section>

            <section aria-labelledby="access-guardrail" className={`mt-5 rounded-xl border px-5 py-4 ${can_manage_roles ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50'}`}>
                <div className="flex items-start gap-3">
                    <LockKeyhole aria-hidden="true" className={`mt-0.5 size-5 shrink-0 ${can_manage_roles ? 'text-primary-700' : 'text-slate-600'}`} />
                    <div><h2 id="access-guardrail" className="text-sm font-bold text-ink-950">{can_manage_roles ? 'Perubahan matriks tersedia' : 'Mode hanya-baca'}</h2><p className="mt-1 text-xs leading-5 text-slate-600">{can_manage_roles ? 'Role Super Admin dan permission admin.manage-roles tetap dikunci untuk mencegah eskalasi privilege.' : 'Hanya Super Admin dengan permission khusus yang dapat mengubah matriks. Administrator unit tetap dapat meninjau sumber kewenangan.'}</p></div>
                </div>
            </section>

            <section aria-labelledby="role-matrix" className="mt-6">
                <div><h2 id="role-matrix" className="text-base font-bold text-ink-950">Matriks role</h2><p className="mt-1 text-xs text-muted-600">Buka satu role untuk melihat capability per domain.</p></div>
                <div className="panel mt-3 overflow-hidden">
                    {roles.map((role) => <PermissionEditor key={role.id} role={role} groups={permission_groups} canManage={can_manage_roles} />)}
                </div>
            </section>
        </>
    );
}
