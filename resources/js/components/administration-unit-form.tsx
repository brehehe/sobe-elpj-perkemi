import { useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';

export type UnitOption = { id: string; parent_id: string | null; code: string; name: string; type: string; is_active: boolean; can_update?: boolean; can_delete?: boolean };
export type UnitScope = { is_global: boolean; root_unit_id: string | null; label: string };
const unitTypes = [['PUSAT', 'Pusat'], ['PROVINSI', 'Provinsi'], ['KOTA', 'Kota'], ['KABUPATEN', 'Kabupaten'], ['DOJO', 'Dojo'], ['UNIT', 'Unit khusus']];

export function AdministrationUnitForm({ unit, scope, options }: { unit?: UnitOption; scope: UnitScope; options: UnitOption[] }) {
    const form = useForm({ parent_id: unit?.parent_id ?? (scope.is_global ? '' : scope.root_unit_id ?? ''), code: unit?.code ?? '', name: unit?.name ?? '', type: unit?.type ?? 'UNIT', is_active: unit?.is_active ?? true });
    const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); if (unit) form.patch(`/administration/units/${unit.id}`); else form.post('/administration/units'); };
    return <form onSubmit={submit} className="panel mt-6 grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
        <div><label className="label" htmlFor="unit-code">Kode unit</label><input id="unit-code" required className="field font-mono" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} placeholder="PENGPROV-JABAR" />{form.errors.code && <p className="error">{form.errors.code}</p>}</div>
        <div><label className="label" htmlFor="unit-name">Nama resmi</label><input id="unit-name" required className="field" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />{form.errors.name && <p className="error">{form.errors.name}</p>}</div>
        <div><label className="label" htmlFor="unit-type">Tingkat organisasi</label><select id="unit-type" className="field" value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>{unitTypes.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{form.errors.type && <p className="error">{form.errors.type}</p>}</div>
        <div><label className="label" htmlFor="unit-parent">Unit induk</label><select id="unit-parent" className="field" value={form.data.parent_id} onChange={(event) => form.setData('parent_id', event.target.value)}><option value="">Tanpa induk</option>{options.filter((option) => option.id !== unit?.id && option.is_active).map((option) => <option key={option.id} value={option.id}>{option.code} — {option.name}</option>)}</select>{form.errors.parent_id && <p className="error">{form.errors.parent_id}</p>}</div>
        <label className="flex min-h-12 items-center gap-3 rounded-lg border border-line-200 px-4 text-sm font-semibold sm:col-span-2"><input type="checkbox" className="size-4 accent-primary-600" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />Unit aktif</label>{form.errors.is_active && <p className="error sm:col-span-2">{form.errors.is_active}</p>}
        <div className="flex justify-end border-t border-line-200 pt-5 sm:col-span-2"><button className="btn-primary" disabled={form.processing}>{form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Save aria-hidden="true" className="size-4" />}{unit ? 'Simpan perubahan' : 'Simpan unit'}</button></div>
    </form>;
}
