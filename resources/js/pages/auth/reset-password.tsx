import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ email, token, password: '', password_confirmation: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') }); };
    return <AuthLayout title="Buat kata sandi baru" description="Gunakan minimal 12 karakter dengan kombinasi yang tidak mudah ditebak."><Head title="Reset kata sandi" /><form onSubmit={submit} className="grid gap-4"><div><label className="label" htmlFor="email">Email</label><input id="email" type="email" className="field" value={form.data.email} readOnly />{form.errors.email && <p className="error">{form.errors.email}</p>}</div><div><label className="label" htmlFor="password">Kata sandi baru</label><input id="password" type="password" className="field" autoFocus required value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />{form.errors.password && <p className="error">{form.errors.password}</p>}</div><div><label className="label" htmlFor="password_confirmation">Konfirmasi kata sandi</label><input id="password_confirmation" type="password" className="field" required value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} /></div><button disabled={form.processing} className="btn-primary mt-2 w-full">{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}Simpan kata sandi</button></form></AuthLayout>;
}
