import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Building2, KeyRound, ServerCog, ShieldCheck, UsersRound } from 'lucide-react';

type Summary = {
    users: number;
    active_users: number;
    units: number;
    active_units: number;
    roles: number;
    permissions: number;
};

type Policy = { label: string; version: string; scope: string };
type Capability = { title: string; description: string; status: string; href: string | null };

export default function AdministrationIndex({ summary, policies, capabilities }: { summary: Summary; policies: Policy[]; capabilities: Capability[] }) {
    const metrics = [
        { label: 'Pengguna aktif', value: summary.active_users, detail: `${summary.users} pengguna terdaftar`, icon: UsersRound },
        { label: 'Unit aktif', value: summary.active_units, detail: `${summary.units} unit terdaftar`, icon: Building2 },
        { label: 'Role', value: summary.roles, detail: 'Kelompok kewenangan', icon: ShieldCheck },
        { label: 'Permission', value: summary.permissions, detail: 'Capability terdaftar', icon: KeyRound },
    ];

    return (
        <>
            <Head title="Administrasi" />
            <PageHeader
                eyebrow="Kendali sistem"
                title="Administrasi"
                description="Ringkasan akses, organisasi, kebijakan, integrasi, audit, dan kesiapan pemulihan berdasarkan konfigurasi serta data aktual."
                actions={<><Link href="/administration/users" className="btn-primary">Kelola pengguna</Link><Link href="/administration/access" className="btn-secondary">Role & permission</Link></>}
            />

            <section aria-label="Ringkasan administrasi" className="mt-6 grid gap-px overflow-hidden rounded-xl border border-line-200 bg-line-200 sm:grid-cols-2 xl:grid-cols-4">
                {metrics.map(({ label, value, detail, icon: Icon }) => (
                    <article key={label} className="bg-white p-5">
                        <div className="flex items-start justify-between gap-4">
                            <div><p className="text-xs font-semibold text-slate-600">{label}</p><p className="mt-2 text-3xl font-bold tracking-tight text-ink-950 tabular-nums">{value}</p></div>
                            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-primary-700"><Icon aria-hidden="true" className="size-5" /></span>
                        </div>
                        <p className="mt-3 text-xs text-muted-600">{detail}</p>
                    </article>
                ))}
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(20rem,0.7fr)]">
                <section className="panel overflow-hidden" aria-labelledby="administration-capabilities">
                    <div className="border-b border-line-200 px-5 py-4">
                        <h2 id="administration-capabilities" className="text-base font-bold text-ink-950">Area operasional</h2>
                        <p className="mt-1 text-xs leading-5 text-muted-600">Status hanya menyatakan kemampuan yang benar-benar tersedia saat ini.</p>
                    </div>
                    <div className="divide-y divide-slate-100">
                        {capabilities.map((capability) => {
                            const content = <>
                                <span className="min-w-0 flex-1"><span className="block text-sm font-bold text-ink-950">{capability.title}</span><span className="mt-1 block text-xs leading-5 text-muted-600">{capability.description}</span></span>
                                <span className="flex shrink-0 items-center gap-2"><StatusBadge status={capability.status} />{capability.href && <ArrowUpRight aria-hidden="true" className="size-4 text-slate-500" />}</span>
                            </>;

                            return capability.href
                                ? <Link key={capability.title} href={capability.href} className="flex min-h-20 items-center gap-4 px-5 py-4 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600">{content}</Link>
                                : <div key={capability.title} className="flex min-h-20 items-center gap-4 px-5 py-4">{content}</div>;
                        })}
                    </div>
                </section>

                <section className="panel overflow-hidden" aria-labelledby="policy-contracts">
                    <div className="flex items-center gap-3 border-b border-line-200 px-5 py-4">
                        <span className="grid size-9 place-items-center rounded-lg bg-slate-100 text-slate-700"><ServerCog aria-hidden="true" className="size-5" /></span>
                        <div><h2 id="policy-contracts" className="text-base font-bold text-ink-950">Kontrak kebijakan</h2><p className="mt-0.5 text-xs text-muted-600">Versi aktif pada backend.</p></div>
                    </div>
                    <dl className="divide-y divide-slate-100">
                        {policies.map((policy) => <div key={policy.label} className="px-5 py-4"><dt className="text-xs font-semibold text-slate-600">{policy.label}</dt><dd className="mt-1 break-words font-mono text-xs font-bold text-ink-950">{policy.version}</dd><dd className="mt-1 text-xs text-muted-600">{policy.scope}</dd></div>)}
                    </dl>
                </section>
            </div>
        </>
    );
}
