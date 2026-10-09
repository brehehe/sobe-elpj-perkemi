import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, LogOut, Send } from 'lucide-react';

export default function VerifyEmail({ status }: { status?: string }) {
    const form = useForm({});
    return <AuthLayout title="Verifikasi email" description="Buka tautan verifikasi yang dikirim ke email organisasi Anda."><Head title="Verifikasi email" />{status === 'verification-link-sent' && <p className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">Tautan verifikasi baru telah dikirim.</p>}<div className="grid gap-3"><button onClick={() => form.post('/email/verification-notification')} disabled={form.processing} className="btn-primary w-full">{form.processing ? <LoaderCircle className="size-4 animate-spin" /> : <Send className="size-4" />}Kirim ulang email</button><button onClick={() => router.post('/logout')} className="btn-secondary w-full"><LogOut className="size-4" />Keluar</button></div></AuthLayout>;
}
