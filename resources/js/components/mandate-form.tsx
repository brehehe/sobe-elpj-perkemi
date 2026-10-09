import { useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';

export type MandateFormValue = {
    id: string;
    number: string;
    title: string;
    description: string | null;
    issued_at: string;
    expires_at: string | null;
    status: string;
};

export function MandateForm({ mandate }: { mandate?: MandateFormValue }) {
    const form = useForm({
        number: mandate?.number ?? '',
        title: mandate?.title ?? '',
        description: mandate?.description ?? '',
        issued_at: mandate?.issued_at?.slice(0, 10) ?? '',
        expires_at: mandate?.expires_at?.slice(0, 10) ?? '',
        status: mandate?.status ?? 'ACTIVE',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (mandate) form.patch(`/mandates/${mandate.id}`);
        else form.post('/mandates');
    };

    const error = (name: keyof typeof form.data) => form.errors[name];

    return (
        <form onSubmit={submit} className="panel mt-6 grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
            <div>
                <label htmlFor="mandate-number" className="label">Nomor dokumen <span className="text-danger-600">*</span></label>
                <input id="mandate-number" required className="field tabular" value={form.data.number} onChange={(event) => form.setData('number', event.target.value)} placeholder="ST-001/PB/X/2026" />
                {error('number') && <p className="error">{error('number')}</p>}
            </div>
            <div>
                <label htmlFor="mandate-status" className="label">Status</label>
                <select id="mandate-status" className="field" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                    <option value="ACTIVE">Aktif / terdaftar</option><option value="INACTIVE">Nonaktif</option><option value="EXPIRED">Kedaluwarsa</option>
                </select>
                {error('status') && <p className="error">{error('status')}</p>}
            </div>
            <div className="sm:col-span-2">
                <label htmlFor="mandate-title" className="label">Nama kegiatan / mandat <span className="text-danger-600">*</span></label>
                <input id="mandate-title" required className="field" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                {error('title') && <p className="error">{error('title')}</p>}
            </div>
            <div>
                <label htmlFor="mandate-issued" className="label">Tanggal dokumen <span className="text-danger-600">*</span></label>
                <input id="mandate-issued" type="date" required className="field" value={form.data.issued_at} onChange={(event) => form.setData('issued_at', event.target.value)} />
                {error('issued_at') && <p className="error">{error('issued_at')}</p>}
            </div>
            <div>
                <label htmlFor="mandate-expires" className="label">Berlaku sampai</label>
                <input id="mandate-expires" type="date" className="field" value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} />
                {error('expires_at') && <p className="error">{error('expires_at')}</p>}
            </div>
            <div className="sm:col-span-2">
                <label htmlFor="mandate-description" className="label">Dasar, ruang lingkup, dan catatan</label>
                <textarea id="mandate-description" rows={5} className="field" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                {error('description') && <p className="error">{error('description')}</p>}
            </div>
            <div className="flex justify-end border-t border-line-200 pt-5 sm:col-span-2">
                <button type="submit" disabled={form.processing} className="btn-primary">
                    {form.processing ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Save aria-hidden="true" className="size-4" />}
                    {mandate ? 'Simpan perubahan' : 'Validasi & registrasi'}
                </button>
            </div>
        </form>
    );
}
