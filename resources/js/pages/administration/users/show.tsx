import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, UserX } from 'lucide-react';
import { useState } from 'react';

type ManagedUser = { id: string; name: string; email: string; is_active: boolean; email_verified_at: string | null; last_login_at: string | null; created_at: string | null; unit: { id: string; code: string; name: string } | null; role: string | null };

export default function ShowUser({ managedUser, canUpdate, canDelete }: { managedUser: ManagedUser; canUpdate: boolean; canDelete: boolean }) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    return <><Head title={managedUser.name} /><PageHeader eyebrow="Administrasi akses" title={managedUser.name} description={managedUser.email} actions={<><Link href="/administration/users" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Daftar</Link>{canUpdate && <Link href={`/administration/users/${managedUser.id}/edit`} className="btn-secondary"><Pencil aria-hidden="true" className="size-4" />Ubah</Link>}{canDelete && managedUser.is_active && <button type="button" className="btn-danger" onClick={() => setConfirming(true)}><UserX aria-hidden="true" className="size-4" />Nonaktifkan</button>}</>} />
        <section className="panel mt-6 overflow-hidden"><div className="grid gap-px bg-line-200 sm:grid-cols-2 lg:grid-cols-3"><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Status</span><div className="mt-2"><StatusBadge status={managedUser.is_active ? 'ACTIVE' : 'INACTIVE'} /></div></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Role</span><p className="mt-2 text-sm font-bold">{managedUser.role ?? 'Tanpa role'}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Unit</span><p className="mt-2 text-sm font-bold">{managedUser.unit ? `${managedUser.unit.code} · ${managedUser.unit.name}` : 'Akses lintas unit'}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Verifikasi email</span><p className="mt-2 text-sm font-semibold">{managedUser.email_verified_at ? formatDate(managedUser.email_verified_at, true) : 'Belum diverifikasi'}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Login terakhir</span><p className="mt-2 text-sm font-semibold">{managedUser.last_login_at ? formatDate(managedUser.last_login_at, true) : 'Belum pernah login'}</p></div><div className="bg-white p-5"><span className="text-xs font-semibold text-muted-600">Dibuat</span><p className="mt-2 text-sm font-semibold">{managedUser.created_at ? formatDate(managedUser.created_at, true) : '—'}</p></div></div></section>
        <ConfirmDialog open={confirming} title="Nonaktifkan pengguna?" description={<>Akses login <b>{managedUser.name}</b> akan dicabut. Record kegiatan, persetujuan, dan audit atas nama pengguna ini tetap dipertahankan.</>} confirmLabel="Nonaktifkan" destructive processing={processing} onClose={() => setConfirming(false)} onConfirm={() => { setProcessing(true); router.delete(`/administration/users/${managedUser.id}`, { onFinish: () => setProcessing(false) }); }} />
    </>;
}
