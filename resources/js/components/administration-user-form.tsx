import { useForm } from '@inertiajs/react';
import { LoaderCircle, Save, Send } from 'lucide-react';
import type { FormEvent } from 'react';

export type UnitOption = { id: string; code: string; name: string; is_active: boolean };
export type RoleOption = { id: number; name: string };
export type ManagedUserValue = { id: string; name: string; email: string; is_active: boolean; unit: { id: string; code: string; name: string } | null; role: string | null };
export type UserFormScope = { can_view_all_units: boolean; default_unit_id: string | null };

export function AdministrationUserForm({ managedUser, scope, units, roles }: { managedUser?: ManagedUserValue; scope: UserFormScope; units: UnitOption[]; roles: RoleOption[] }) {
    const firstRole = roles.find((role) => role.name !== 'Super Admin')?.name ?? roles[0]?.name ?? '';
    const form = useForm({ name: managedUser?.name ?? '', email: managedUser?.email ?? '', unit_id: managedUser?.unit?.id ?? scope.default_unit_id ?? '', role: managedUser?.role ?? firstRole, is_active: managedUser?.is_active ?? true });
    const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); if (managedUser) form.patch(`/administration/users/${managedUser.id}`); else form.post('/administration/users'); };
    return <form onSubmit={submit} className="panel mt-6 grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
        <div><label htmlFor="managed-name" className="label">Nama lengkap</label><input id="managed-name" required className="field" autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name && <p className="error">{form.errors.name}</p>}</div>
        <div><label htmlFor="managed-email" className="label">Email organisasi</label><input id="managed-email" required type="email" className="field" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />{form.errors.email && <p className="error">{form.errors.email}</p>}</div>
        <div><label htmlFor="managed-unit" className="label">Unit organisasi</label><select id="managed-unit" required className="field" disabled={!scope.can_view_all_units} value={form.data.unit_id} onChange={(event) => form.setData('unit_id', event.target.value)}><option value="">Pilih unit</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code} — {unit.name}{unit.is_active ? '' : ' (nonaktif)'}</option>)}</select>{form.errors.unit_id && <p className="error">{form.errors.unit_id}</p>}</div>
        <div><label htmlFor="managed-role" className="label">Role</label><select id="managed-role" required className="field" value={form.data.role} onChange={(event) => form.setData('role', event.target.value)}>{roles.map((role) => <option key={role.id} value={role.name}>{role.name}</option>)}</select>{form.errors.role && <p className="error">{form.errors.role}</p>}</div>
        <label className="flex min-h-12 items-center gap-3 rounded-lg border border-line-200 px-4 text-sm font-semibold sm:col-span-2"><input type="checkbox" className="size-4 accent-primary-600" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} /><span>Akun aktif dan dapat login</span></label>{form.errors.is_active && <p className="error sm:col-span-2">{form.errors.is_active}</p>}
        <div className="flex justify-end border-t border-line-200 pt-5 sm:col-span-2"><button className="btn-primary" disabled={form.processing}>{form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : managedUser ? <Save aria-hidden="true" className="size-4" /> : <Send aria-hidden="true" className="size-4" />}{managedUser ? 'Simpan perubahan' : 'Buat & kirim undangan'}</button></div>
    </form>;
}
