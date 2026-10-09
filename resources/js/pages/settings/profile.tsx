import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import type { SharedProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Laptop, LogOut, Save, ShieldCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';

type Session = { id: string; ip_address?: string; user_agent?: string; last_activity: string; is_current: boolean };

export default function Profile({ sessions }: { sessions: Session[] }) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user!;
    const form = useForm({ name: user.name, email: user.email });
    const [sessionToRevoke, setSessionToRevoke] = useState<Session | null>(null);
    const [revoking, setRevoking] = useState(false);
    const submit = (event: FormEvent) => { event.preventDefault(); form.patch('/settings/profile', { preserveScroll: true }); };

    return <>
        <Head title="Profil & keamanan" />
        <PageHeader eyebrow="Pengaturan akun" title="Profil & keamanan" description="Kelola identitas akun dan sesi perangkat aktif." />
        <div className="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_420px]">
            <form onSubmit={submit} className="panel p-5 sm:p-6">
                <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-lg bg-blue-50 text-primary-700"><ShieldCheck className="size-5" /></span><div><h2 className="text-base font-bold">Identitas akun</h2><p className="text-xs text-muted-600">Perubahan email memerlukan verifikasi ulang.</p></div></div>
                <div className="mt-6 grid gap-5"><div><label className="label" htmlFor="name">Nama lengkap</label><input id="name" className="field" autoComplete="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />{form.errors.name && <p className="error" role="alert">{form.errors.name}</p>}</div><div><label className="label" htmlFor="email">Email</label><input id="email" type="email" className="field" autoComplete="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} required />{form.errors.email && <p className="error" role="alert">{form.errors.email}</p>}</div><div className="rounded-lg bg-slate-50 p-4 text-xs leading-5 text-slate-600"><b className="text-slate-800">Unit:</b> {user.unit ? `${user.unit.code} · ${user.unit.name}` : 'Akses lintas unit'}<br /><b className="text-slate-800">Peran:</b> {user.roles.join(', ')}</div><button className="btn-primary justify-self-start" disabled={form.processing}><Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan profil'}</button></div>
            </form>

            <section className="panel overflow-hidden"><div className="border-b border-line-200 px-5 py-4"><h2 className="text-base font-bold">Sesi perangkat</h2><p className="mt-1 text-xs text-muted-600">Cabut akses dari perangkat yang tidak dikenal.</p></div><div className="divide-y divide-slate-100">{sessions.map((session) => <div key={session.id} className="flex items-start gap-3 p-5"><span className="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><Laptop className="size-5" /></span><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><b className="text-xs">{session.ip_address || 'IP tidak tercatat'}</b>{session.is_current && <span className="rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-bold text-success-600">SESI INI</span>}</div><p className="mt-1 line-clamp-2 text-[10px] leading-4 text-muted-600">{session.user_agent || 'Perangkat tidak diketahui'}</p><p className="mt-1 text-[10px] font-semibold text-slate-500">Aktif {session.last_activity}</p></div>{!session.is_current && <button type="button" aria-label={`Cabut sesi ${session.ip_address || ''}`} onClick={() => setSessionToRevoke(session)} className="grid size-11 shrink-0 place-items-center rounded-lg text-danger-600 hover:bg-red-50"><LogOut className="size-4" /></button>}</div>)}</div></section>
        </div>
        <ConfirmDialog open={sessionToRevoke !== null} title="Cabut sesi perangkat?" description={`Sesi ${sessionToRevoke?.ip_address || 'perangkat ini'} akan langsung dikeluarkan dan harus login kembali.`} confirmLabel="Cabut sesi" destructive processing={revoking} onClose={() => setSessionToRevoke(null)} onConfirm={() => { if (!sessionToRevoke) return; setRevoking(true); router.delete(`/settings/sessions/${sessionToRevoke.id}`, { preserveScroll: true, onSuccess: () => setSessionToRevoke(null), onFinish: () => setRevoking(false) }); }} />
    </>;
}
