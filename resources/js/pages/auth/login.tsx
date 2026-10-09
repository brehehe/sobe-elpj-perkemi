import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LockKeyhole } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export default function Login({ canResetPassword, status }: { canResetPassword: boolean; status?: string }) {
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({ email: '', password: '', remember: false });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/login', { onFinish: () => form.reset('password') }); };

    return <AuthLayout title="Masuk ke ruang kerja" description="Gunakan akun organisasi yang telah diberikan administrator.">
        <Head title="Masuk" />
        {status && <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">{status}</div>}
        <form onSubmit={submit} className="grid gap-5">
            <div><label htmlFor="email" className="label">Email organisasi</label><input id="email" type="email" autoComplete="email" autoFocus required aria-invalid={Boolean(form.errors.email)} aria-describedby={form.errors.email ? 'email-error' : undefined} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className="field" placeholder="nama@perkemi.or.id" />{form.errors.email && <p id="email-error" className="error" role="alert">{form.errors.email}</p>}</div>
            <div><div className="mb-1.5 flex items-center justify-between"><label htmlFor="password" className="text-sm font-semibold text-slate-700">Kata sandi</label>{canResetPassword && <Link href="/forgot-password" className="text-xs font-semibold text-primary-700 hover:underline">Lupa kata sandi?</Link>}</div><div className="relative"><input id="password" type={showPassword ? 'text' : 'password'} autoComplete="current-password" required aria-invalid={Boolean(form.errors.password)} aria-describedby={form.errors.password ? 'password-error' : undefined} value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className="field pr-12" /><button type="button" aria-pressed={showPassword} aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'} onClick={() => setShowPassword((value) => !value)} className="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-500">{showPassword ? <EyeOff aria-hidden="true" className="size-4" /> : <Eye aria-hidden="true" className="size-4" />}</button></div>{form.errors.password && <p id="password-error" className="error" role="alert">{form.errors.password}</p>}</div>
            <label className="flex min-h-11 items-center gap-2 text-xs text-slate-600"><input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} className="size-4 rounded border-slate-300 text-primary-600" />Pertahankan sesi di perangkat ini</label>
            <button disabled={form.processing} className="btn-primary w-full">{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <LockKeyhole className="size-4" />}Masuk</button>
        </form>
        <p className="mt-5 border-t border-slate-100 pt-4 text-center text-[11px] leading-5 text-muted-600">Registrasi publik dinonaktifkan. Hubungi administrator unit untuk memperoleh akses.</p>
    </AuthLayout>;
}
