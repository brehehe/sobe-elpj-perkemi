import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { Accessibility, ArrowUpRight, BookOpen, Building2, FileKey2, Keyboard, LifeBuoy, Scale, ShieldCheck, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

type Application = {
    name: string;
    purpose: string;
    backend_version: string;
    frontend_version: string;
    build_id: string;
    released_at: string | null;
    environment: string;
};
type Service = { name: string; status: string; detail: string; href?: string };
type Governance = {
    classification: string;
    privacy: string;
    accessibility: string;
    owner: string;
    support_email: string | null;
    security_email: string | null;
    documentation_url: string | null;
    privacy_url: string | null;
};
type BackupSummary = {
    status: string;
    newest_backup_at: string | null;
    restore_drill: { status: string; last_verified_at: string | null; maximum_age_days: number; guidance: string };
    href: string;
};
type Props = {
    application: Application;
    services: Service[];
    release_notes: string[];
    governance: Governance;
    shortcuts: Array<{ keys: string; action: string }>;
    licenses: Array<{ name: string; license: string }>;
    backup: BackupSummary | null;
};

export default function SystemAbout({ application, services, release_notes, governance, shortcuts, licenses, backup }: Props) {
    return (
        <>
            <Head title="Informasi sistem" />
            <PageHeader
                eyebrow="Transparansi produk"
                title="Informasi sistem"
                description={application.purpose}
                actions={<span className="inline-flex min-h-10 items-center rounded-lg border border-line-200 bg-white px-3 font-mono text-xs font-bold text-slate-700">Build {application.build_id}</span>}
            />

            <section aria-label="Identitas rilis" className="mt-6 grid gap-px overflow-hidden rounded-xl border border-line-200 bg-line-200 sm:grid-cols-2 xl:grid-cols-4">
                <ReleaseFact label="Backend" value={`v${application.backend_version}`} />
                <ReleaseFact label="Frontend" value={`v${application.frontend_version}`} />
                <ReleaseFact label="Environment" value={application.environment} />
                <ReleaseFact label="Tanggal rilis" value={formatDate(application.released_at, true)} />
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
                <div className="grid content-start gap-6">
                    <section className="panel overflow-hidden" aria-labelledby="service-status-title">
                        <div className="border-b border-line-200 px-5 py-4"><h2 id="service-status-title" className="text-base font-bold">Status layanan</h2><p className="mt-1 text-xs text-muted-600">Status aman yang dapat dilihat pengguna tanpa membuka detail infrastruktur sensitif.</p></div>
                        <div className="divide-y divide-slate-100">
                            {services.map((service) => {
                                const content = <><span className="min-w-0 flex-1"><span className="block text-sm font-bold text-ink-950">{service.name}</span><span className="mt-1 block text-xs leading-5 text-muted-600">{service.detail}</span></span><span className="flex shrink-0 items-center gap-2"><StatusBadge status={service.status} />{service.href && <ArrowUpRight aria-hidden="true" className="size-4 text-slate-500" />}</span></>;
                                return service.href
                                    ? <Link key={service.name} href={service.href} className="flex min-h-18 items-center gap-4 px-5 py-4 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600">{content}</Link>
                                    : <div key={service.name} className="flex min-h-18 items-center gap-4 px-5 py-4">{content}</div>;
                            })}
                        </div>
                    </section>

                    {backup && <section className="panel p-5" aria-labelledby="about-backup-title">
                        <div className="flex flex-wrap items-center justify-between gap-3"><div><h2 id="about-backup-title" className="text-base font-bold">Ringkasan backup khusus admin</h2><p className="mt-1 text-xs text-muted-600">Tidak ditampilkan kepada pengguna tanpa permission administrasi.</p></div><StatusBadge status={backup.status} /></div>
                        <dl className="mt-5 grid gap-4 text-xs sm:grid-cols-2"><div><dt className="font-semibold text-slate-500">Backup terbaru</dt><dd className="mt-1 font-bold text-slate-900">{formatDate(backup.newest_backup_at, true)}</dd></div><div><dt className="font-semibold text-slate-500">Restore drill</dt><dd className="mt-1 flex items-center gap-2"><StatusBadge status={backup.restore_drill.status} /><span>{formatDate(backup.restore_drill.last_verified_at, true)}</span></dd></div></dl>
                        <Link href={backup.href} className="btn-secondary mt-5">Lihat Backup Health<ArrowUpRight aria-hidden="true" className="size-4" /></Link>
                    </section>}

                    <section className="panel overflow-hidden" aria-labelledby="release-notes-title">
                        <div className="border-b border-line-200 px-5 py-4"><h2 id="release-notes-title" className="text-base font-bold">Ringkasan rilis</h2></div>
                        <ul className="divide-y divide-slate-100">{release_notes.map((note) => <li key={note} className="flex gap-3 px-5 py-4 text-sm leading-6 text-slate-700"><ShieldCheck aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-primary-700" /><span>{note}</span></li>)}</ul>
                    </section>
                </div>

                <aside className="grid content-start gap-6">
                    <InfoSection icon={Building2} title="Pemilik & bantuan">
                        <p>{governance.owner}</p>
                        <div className="mt-3 grid gap-2">
                            {governance.support_email ? <a className="text-primary-700 underline underline-offset-4" href={`mailto:${governance.support_email}`}>Hubungi dukungan</a> : <p className="text-muted-600">Email dukungan belum dikonfigurasi.</p>}
                            {governance.documentation_url && <a className="inline-flex items-center gap-1 text-primary-700 underline underline-offset-4" href={governance.documentation_url} target="_blank" rel="noreferrer">Dokumentasi<BookOpen aria-hidden="true" className="size-3.5" /></a>}
                        </div>
                    </InfoSection>
                    <InfoSection icon={FileKey2} title="Privasi & klasifikasi">
                        <p><b>Klasifikasi:</b> {governance.classification}</p><p className="mt-3">{governance.privacy}</p>
                        {governance.privacy_url && <a className="mt-3 inline-flex items-center gap-1 text-primary-700 underline underline-offset-4" href={governance.privacy_url} target="_blank" rel="noreferrer">Kebijakan lengkap<ArrowUpRight aria-hidden="true" className="size-3.5" /></a>}
                    </InfoSection>
                    <InfoSection icon={Accessibility} title="Aksesibilitas">
                        <p>{governance.accessibility}</p>
                    </InfoSection>
                    <InfoSection icon={Keyboard} title="Pintasan keyboard">
                        <dl className="grid gap-3">{shortcuts.map((shortcut) => <div key={shortcut.keys}><dt><kbd className="rounded border border-slate-300 bg-slate-50 px-2 py-1 font-mono text-[11px] font-bold text-slate-800">{shortcut.keys}</kbd></dt><dd className="mt-1 text-muted-600">{shortcut.action}</dd></div>)}</dl>
                    </InfoSection>
                    <InfoSection icon={Scale} title="Lisensi utama">
                        <ul className="grid grid-cols-2 gap-x-4 gap-y-2">{licenses.map((license) => <li key={license.name} className="flex items-center justify-between gap-2 border-b border-slate-100 pb-2"><span>{license.name}</span><b>{license.license}</b></li>)}</ul>
                    </InfoSection>
                    {governance.security_email && <a href={`mailto:${governance.security_email}`} className="btn-secondary w-full"><LifeBuoy aria-hidden="true" className="size-4" />Laporkan isu keamanan</a>}
                </aside>
            </div>
        </>
    );
}

function ReleaseFact({ label, value }: { label: string; value: string }) {
    return <div className="bg-white p-4"><p className="text-xs font-semibold text-slate-500">{label}</p><p className="mt-2 break-words font-mono text-sm font-bold text-ink-950">{value}</p></div>;
}

function InfoSection({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
    return <section className="panel p-5"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-lg bg-blue-50 text-primary-700"><Icon aria-hidden="true" className="size-4.5" /></span><h2 className="text-sm font-bold text-ink-950">{title}</h2></div><div className="mt-4 text-xs leading-5 text-slate-700">{children}</div></section>;
}
