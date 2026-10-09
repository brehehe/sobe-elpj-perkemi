import { PageHeader } from '@/components/page-header';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';

type UserOption = { id: string; unit_id: string; name: string; unit?: { code: string; name: string } };
type BudgetOption = {
    id: string;
    unit_id: string;
    code: string;
    current_version: number;
    current_version_record: { total: string };
    plan: { code: string; mandate: { number: string; title: string } };
};

export default function CreateActivity({ budgets, users }: { budgets: BudgetOption[]; users: UserOption[] }) {
    const form = useForm({ budget_id: '', pic_id: '', code: '', title: '', description: '', location: '', starts_at: '', ends_at: '' });
    const selected = budgets.find((budget) => budget.id === form.data.budget_id);
    const availableUsers = selected ? users.filter((user) => user.unit_id === selected.unit_id) : users;
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/activities'); };
    return <>
        <Head title="Buat kegiatan" />
        <PageHeader eyebrow="Mandat → Planning → RAB → Activity-ID" title="Buat kegiatan" description="Kegiatan hanya dapat dibuka dari planning dan RAB yang telah disetujui. Snapshot sumber dikunci saat Activity-ID dibuat." actions={<Link href="/activities" className="btn-secondary"><ArrowLeft className="size-4" />Kembali</Link>} />
        <form onSubmit={submit} className="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section className="panel grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div className="sm:col-span-2"><label htmlFor="budget_id" className="label">RAB disetujui <span className="text-danger-600">*</span></label><select id="budget_id" required value={form.data.budget_id} onChange={(event) => { form.setData('budget_id', event.target.value); form.setData('pic_id', ''); }} className="field"><option value="">Pilih RAB yang menjadi sumber kegiatan</option>{budgets.map((item) => <option key={item.id} value={item.id}>{item.code} · {item.plan.code} · {item.plan.mandate.number}</option>)}</select>{form.errors.budget_id && <p className="error">{form.errors.budget_id}</p>}{selected && <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-900"><b className="block">{selected.plan.mandate.title}</b>Versi RAB {selected.current_version} · Pagu IDR {Number(selected.current_version_record.total).toLocaleString('id-ID')}</div>}</div>
                <div><label htmlFor="code" className="label">Activity-ID <span className="text-danger-600">*</span></label><input id="code" required placeholder="KGT-2026-042" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} className="field tabular" />{form.errors.code && <p className="error">{form.errors.code}</p>}</div>
                <div><label htmlFor="pic_id" className="label">PIC kegiatan</label><select id="pic_id" value={form.data.pic_id} onChange={(event) => form.setData('pic_id', event.target.value)} className="field"><option value="">Gunakan akun saya (bila satu unit)</option>{availableUsers.map((item) => <option key={item.id} value={item.id}>{item.name}{item.unit ? ` · ${item.unit.code}` : ''}</option>)}</select>{form.errors.pic_id && <p className="error">{form.errors.pic_id}</p>}</div>
                <div className="sm:col-span-2"><label htmlFor="title" className="label">Nama kegiatan <span className="text-danger-600">*</span></label><input id="title" required value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} className="field" />{form.errors.title && <p className="error">{form.errors.title}</p>}</div>
                <div className="sm:col-span-2"><label htmlFor="description" className="label">Tujuan dan konteks</label><textarea id="description" rows={4} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="field resize-y" />{form.errors.description && <p className="error">{form.errors.description}</p>}</div>
                <div className="sm:col-span-2"><label htmlFor="location" className="label">Lokasi</label><input id="location" value={form.data.location} onChange={(event) => form.setData('location', event.target.value)} className="field" /></div>
                <div><label htmlFor="starts_at" className="label">Mulai <span className="text-danger-600">*</span></label><input id="starts_at" type="datetime-local" required value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} className="field" />{form.errors.starts_at && <p className="error">{form.errors.starts_at}</p>}</div>
                <div><label htmlFor="ends_at" className="label">Selesai <span className="text-danger-600">*</span></label><input id="ends_at" type="datetime-local" required value={form.data.ends_at} onChange={(event) => form.setData('ends_at', event.target.value)} className="field" />{form.errors.ends_at && <p className="error">{form.errors.ends_at}</p>}</div>
            </section>
            <aside className="space-y-5"><section className="panel p-5"><h2 className="text-sm font-bold">Yang terjadi setelah disimpan</h2><ol className="mt-4 grid gap-4 text-xs leading-5 text-muted-600"><li className="flex gap-3"><span className="grid size-6 shrink-0 place-items-center rounded-full bg-blue-50 font-bold text-primary-700">1</span>Planning dan RAB disalin sebagai snapshot immutable.</li><li className="flex gap-3"><span className="grid size-6 shrink-0 place-items-center rounded-full bg-blue-50 font-bold text-primary-700">2</span>Activity-ID menjadi referensi utama seluruh sumber data.</li><li className="flex gap-3"><span className="grid size-6 shrink-0 place-items-center rounded-full bg-blue-50 font-bold text-primary-700">3</span>Draf LPJ, owner, dan audit event dibuat otomatis.</li></ol></section><button disabled={form.processing || budgets.length === 0} className="btn-primary w-full">{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}Simpan kegiatan</button>{budgets.length === 0 && <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">Belum ada RAB yang disetujui. Selesaikan review planning dan RAB terlebih dahulu.</p>}</aside>
        </form>
    </>;
}
