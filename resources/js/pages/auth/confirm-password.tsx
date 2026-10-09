import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/user/confirm-password', { onFinish: () => form.reset() }); };
    return <AuthLayout title="Konfirmasi identitas" description="Masukkan kata sandi untuk melanjutkan aksi sensitif."><Head title="Konfirmasi kata sandi" /><form onSubmit={submit} className="grid gap-5"><div><label htmlFor="password" className="label">Kata sandi</label><input id="password" type="password" className="field" autoFocus required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />{form.errors.password && <p className="error">{form.errors.password}</p>}</div><button className="btn-primary w-full" disabled={form.processing}>{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}Konfirmasi</button></form></AuthLayout>;
}
