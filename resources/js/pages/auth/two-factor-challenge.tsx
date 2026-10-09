import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post('/two-factor-challenge'); };
    return <AuthLayout title="Verifikasi dua langkah" description={recovery ? 'Masukkan salah satu kode pemulihan Anda.' : 'Masukkan kode enam digit dari aplikasi autentikator.'}><Head title="Verifikasi dua langkah" /><form onSubmit={submit} className="grid gap-5">{recovery ? <div><label className="label" htmlFor="recovery_code">Kode pemulihan</label><input id="recovery_code" className="field" autoFocus value={form.data.recovery_code} onChange={(event) => form.setData('recovery_code', event.target.value)} />{form.errors.recovery_code && <p className="error">{form.errors.recovery_code}</p>}</div> : <div><label className="label" htmlFor="code">Kode autentikator</label><input id="code" inputMode="numeric" autoComplete="one-time-code" className="field text-center text-lg tracking-[0.3em]" autoFocus value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} />{form.errors.code && <p className="error">{form.errors.code}</p>}</div>}<button className="btn-primary w-full" disabled={form.processing}>{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <KeyRound className="size-4" />}Verifikasi</button><button type="button" onClick={() => { setRecovery((value) => !value); form.reset(); }} className="min-h-11 text-xs font-semibold text-primary-700 hover:underline">{recovery ? 'Gunakan kode autentikator' : 'Gunakan kode pemulihan'}</button></form></AuthLayout>;
}
