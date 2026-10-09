import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Mail } from 'lucide-react';
import type { FormEvent } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const form = useForm({ email: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/forgot-password'); };
    return <AuthLayout title="Pulihkan akses" description="Kami akan mengirim tautan reset ke email akun yang terdaftar."><Head title="Lupa kata sandi" />{status && <p className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">{status}</p>}<form onSubmit={submit} className="grid gap-5"><div><label htmlFor="email" className="label">Email organisasi</label><input id="email" type="email" required autoFocus value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className="field" />{form.errors.email && <p className="error">{form.errors.email}</p>}</div><button disabled={form.processing} className="btn-primary w-full">{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Mail className="size-4" />}Kirim tautan reset</button><Link href="/login" className="flex min-h-11 items-center justify-center gap-2 text-xs font-semibold text-primary-700"><ArrowLeft className="size-4" />Kembali ke halaman masuk</Link></form></AuthLayout>;
}
