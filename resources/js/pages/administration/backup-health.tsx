import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, Clock3, DatabaseBackup, HardDrive, ShieldAlert, type LucideIcon } from 'lucide-react';

type Failure = { check: string; guidance: string };
type Destination = {
    name: string;
    disk: string;
    storage_type: string;
    reachable: boolean;
    healthy: boolean;
    status: string;
    backup_count: number;
    newest_backup_at: string | null;
    age_hours: number | null;
    used_storage_bytes: number | null;
    failures: Failure[];
};
type RestoreDrill = { status: string; last_verified_at: string | null; maximum_age_days: number; guidance: string };
type Props = { status: string; healthy: boolean; checked_at: string; newest_backup_at: string | null; destinations: Destination[]; restore_drill: RestoreDrill };

function formatBytes(bytes: number | null): string {
    if (bytes === null) return 'Tidak tersedia';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
}

export default function BackupHealth({ status, checked_at, newest_backup_at, destinations, restore_drill }: Props) {
    const healthyDestinations = destinations.filter((destination) => destination.healthy).length;
    const statusMessage = status === 'HEALTHY'
        ? 'Seluruh destinasi sehat dan bukti restore drill masih berlaku.'
        : status === 'ATTENTION'
            ? 'Backup tersedia, tetapi bukti restore drill perlu diperbarui.'
            : 'Satu atau lebih kontrak backup belum terpenuhi.';

    return (
        <>
            <Head title="Backup Health" />
            <PageHeader
                eyebrow="Administrasi · kontinuitas"
                title="Backup Health"
                description="Pemeriksaan read-only atas destinasi backup, umur salinan terbaru, pemakaian storage, dan bukti restore drill staging."
                actions={<Link href="/administration" className="btn-secondary"><ArrowLeft aria-hidden="true" className="size-4" />Administrasi</Link>}
            />

            <section className={`mt-6 rounded-xl border p-5 ${status === 'HEALTHY' ? 'border-emerald-200 bg-emerald-50' : status === 'ATTENTION' ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50'}`} aria-labelledby="backup-overall-status">
                <div className="flex items-start gap-4">
                    <span className={`grid size-11 shrink-0 place-items-center rounded-lg bg-white ${status === 'HEALTHY' ? 'text-emerald-700' : status === 'ATTENTION' ? 'text-amber-800' : 'text-red-700'}`}>
                        {status === 'HEALTHY' ? <CheckCircle2 aria-hidden="true" className="size-6" /> : <ShieldAlert aria-hidden="true" className="size-6" />}
                    </span>
                    <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h2 id="backup-overall-status" className="text-base font-bold text-ink-950">Kesiapan pemulihan</h2><StatusBadge status={status} /></div><p className="mt-2 text-sm leading-6 text-slate-700">{statusMessage}</p><p className="mt-2 text-xs text-slate-600">Diperiksa {formatDate(checked_at, true)}</p></div>
                </div>
            </section>

            <section aria-label="Metrik backup" className="mt-5 grid gap-px overflow-hidden rounded-xl border border-line-200 bg-line-200 sm:grid-cols-2 xl:grid-cols-4">
                <Metric icon={HardDrive} label="Destinasi sehat" value={`${healthyDestinations}/${destinations.length}`} detail="Hasil seluruh health check" />
                <Metric icon={DatabaseBackup} label="Backup terbaru" value={formatDate(newest_backup_at, true)} detail="Terbaru dari semua destinasi" />
                <Metric icon={Clock3} label="Restore drill" value={restore_drill.status.replaceAll('_', ' ')} detail={`Maksimum ${restore_drill.maximum_age_days} hari`} />
                <Metric icon={ShieldAlert} label="Mode pemeriksaan" value="Read-only" detail="Tidak membuat atau menghapus backup" />
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.6fr)]">
                <section className="panel overflow-hidden" aria-labelledby="backup-destinations">
                    <div className="border-b border-line-200 px-5 py-4"><h2 id="backup-destinations" className="text-base font-bold">Destinasi terpantau</h2><p className="mt-1 text-xs text-muted-600">Nama file, path, dan exception mentah tidak ditampilkan.</p></div>
                    <div className="divide-y divide-slate-100">
                        {destinations.map((destination) => <article key={`${destination.name}-${destination.disk}`} className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0"><h3 className="break-words text-sm font-bold text-ink-950">{destination.name}</h3><p className="mt-1 text-xs text-muted-600">Disk {destination.disk} · {destination.storage_type}</p></div>
                                <StatusBadge status={destination.status} />
                            </div>
                            <dl className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 text-xs sm:grid-cols-3">
                                <div><dt className="font-semibold text-slate-500">Jumlah backup</dt><dd className="mt-1 font-bold tabular-nums text-slate-900">{destination.backup_count}</dd></div>
                                <div><dt className="font-semibold text-slate-500">Backup terbaru</dt><dd className="mt-1 font-bold text-slate-900">{formatDate(destination.newest_backup_at, true)}</dd></div>
                                <div><dt className="font-semibold text-slate-500">Pemakaian</dt><dd className="mt-1 font-bold tabular-nums text-slate-900">{formatBytes(destination.used_storage_bytes)}</dd></div>
                            </dl>
                            {destination.failures.length > 0 && <div className="mt-4 grid gap-2">{destination.failures.map((failure) => <div key={failure.check} className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-950"><AlertTriangle aria-hidden="true" className="mt-0.5 size-4 shrink-0" /><p><b>{failure.check}:</b> {failure.guidance}</p></div>)}</div>}
                        </article>)}
                    </div>
                </section>

                <aside className="grid content-start gap-5">
                    <section className="panel p-5" aria-labelledby="restore-drill-title">
                        <div className="flex flex-wrap items-center justify-between gap-2"><h2 id="restore-drill-title" className="text-base font-bold">Restore drill staging</h2><StatusBadge status={restore_drill.status} /></div>
                        <p className="mt-3 text-sm leading-6 text-slate-700">{restore_drill.guidance}</p>
                        <dl className="mt-4 border-t border-line-200 pt-4 text-xs"><dt className="font-semibold text-slate-500">Terakhir diverifikasi</dt><dd className="mt-1 font-bold text-slate-900">{formatDate(restore_drill.last_verified_at, true)}</dd></dl>
                    </section>
                    <section className="panel p-5" aria-labelledby="backup-operations-title">
                        <h2 id="backup-operations-title" className="text-base font-bold">Kontrak operasional</h2>
                        <ol className="mt-4 grid gap-3 text-xs leading-5 text-slate-700">
                            <li><b>01.</b> Backup database dijadwalkan pukul 01.00.</li>
                            <li><b>02.</b> Cleanup dijadwalkan pukul 02.00.</li>
                            <li><b>03.</b> Monitor kesehatan dijalankan pukul 07.00.</li>
                            <li><b>04.</b> Restore drill harus dilakukan di staging, bukan database production.</li>
                        </ol>
                        <div className="mt-4 rounded-lg bg-navy-950 p-3 font-mono text-[11px] leading-5 text-slate-200 [overflow-wrap:anywhere]">php artisan lpj:backup-health --json</div>
                    </section>
                </aside>
            </div>
        </>
    );
}

function Metric({ icon: Icon, label, value, detail }: { icon: LucideIcon; label: string; value: string; detail: string }) {
    return <article className="bg-white p-4"><div className="flex items-center gap-2 text-slate-600"><Icon aria-hidden="true" className="size-4" /><p className="text-xs font-semibold">{label}</p></div><p className="mt-2 break-words text-lg font-bold text-ink-950">{value}</p><p className="mt-1 text-[11px] leading-5 text-muted-600">{detail}</p></article>;
}
