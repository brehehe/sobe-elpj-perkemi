import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity, Archive, Bell, Bot, Calculator, ChartNoAxesCombined, CheckCheck, ChevronDown, CircleDollarSign, ClipboardCheck,
    FileCheck2, FileClock, FileSearch, FolderLock, Gauge, Info, Landmark, Layers3, ListChecks, LogOut, Menu,
    Megaphone, Network, Search, Settings, ShieldCheck, SquareKanban, Users, Waypoints, X, type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Toaster, toast } from 'sonner';
import { GlobalSearch, type GlobalSearchShortcut } from '@/components/global-search';
import { AiReviewRealtime } from '@/components/ai-review-realtime';
import { NetworkStatus } from '@/components/network-status';
import { cn, initials } from '@/lib/utils';
import type { SharedProps } from '@/types';

type NavItem = { label: string; href: string; icon: LucideIcon; permission?: string; permissions?: string[] };
type NavGroup = { label: string; items: NavItem[] };

const groups: NavGroup[] = [
    { label: 'Kendali', items: [
        { label: 'Dashboard', href: '/dashboard', icon: Gauge },
        { label: 'Analisis Kegiatan', href: '/activity-intelligence', icon: ChartNoAxesCombined, permission: 'activities.view' },
        { label: 'Action Center', href: '/action-center', icon: SquareKanban, permission: 'action-center.view' },
        { label: 'Portfolio LPJ', href: '/portfolio', icon: Layers3, permission: 'lpjs.view' },
    ] },
    { label: 'Perencanaan', items: [
        { label: 'Mandat', href: '/mandates', icon: FileCheck2, permission: 'mandates.view' },
        { label: 'Planning', href: '/planning', icon: ClipboardCheck, permission: 'planning.view' },
        { label: 'RAB', href: '/budgets', icon: Calculator, permission: 'budgets.view' },
        { label: 'Kegiatan', href: '/activities', icon: Activity, permission: 'activities.view' },
    ] },
    { label: 'Pelaksanaan', items: [
        { label: 'Peserta', href: '/workspace/participants', icon: Users, permission: 'activities.view' },
        { label: 'Keuangan', href: '/finance', icon: CircleDollarSign, permission: 'activities.view' },
        { label: 'Bukti', href: '/workspace/evidence', icon: FolderLock, permission: 'evidence.view' },
        { label: 'Evaluasi', href: '/workspace/evaluations', icon: ClipboardCheck, permission: 'activities.view' },
    ] },
    { label: 'Pertanggungjawaban', items: [
        { label: 'e-LPJ', href: '/lpjs', icon: FileClock, permission: 'lpjs.view' },
        { label: 'Verifikasi', href: '/workspace/verifications', icon: FileSearch, permission: 'lpjs.verify' },
        { label: 'Temuan & CAPA', href: '/workspace/findings', icon: ListChecks, permission: 'findings.view' },
        { label: 'Persetujuan', href: '/workspace/approvals', icon: CheckCheck, permission: 'lpjs.view' },
        { label: 'Arsip', href: '/workspace/archives', icon: Archive, permission: 'lpjs.view' },
    ] },
    { label: 'Governance', items: [
        { label: 'Intelligence', href: '/intelligence', icon: Bot, permissions: ['ai.request', 'ai.review'] },
        { label: 'Kegiatan Berikutnya', href: '/next-activity', icon: Waypoints, permission: 'activities.view' },
        { label: 'Integration Hub', href: '/integrations', icon: Landmark, permission: 'integrations.manage' },
        { label: 'Jejak audit', href: '/audit', icon: ShieldCheck, permission: 'audit.view' },
        { label: 'Hierarki', href: '/organization/hierarchy', icon: Network, permission: 'hierarchy.view' },
        { label: 'Administrasi', href: '/administration', icon: Settings, permission: 'admin.manage' },
    ] },
];

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, notifications, flash, system, errors } = usePage<SharedProps>().props;
    const path = usePage().url.split('?')[0];
    const user = auth.user!;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [userOpen, setUserOpen] = useState(false);
    const [desktop, setDesktop] = useState(false);

    const visibleGroups = useMemo(() => groups.map((group) => ({
        ...group,
        items: group.items.filter((item) => (
            (!item.permission || user.permissions.includes(item.permission))
            && (!item.permissions || item.permissions.some((permission) => user.permissions.includes(permission)))
        )),
    })).filter((group) => group.items.length > 0), [user.permissions]);
    const searchShortcuts = useMemo<GlobalSearchShortcut[]>(() => visibleGroups.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label }))), [visibleGroups]);

    useEffect(() => {
        const handler = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault(); setSearchOpen(true);
            }
            if (event.key === 'Escape') { setSearchOpen(false); setNotificationsOpen(false); setUserOpen(false); }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    useEffect(() => {
        const media = window.matchMedia('(min-width: 1024px)');
        const update = () => setDesktop(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    }, [flash.success, flash.error]);

    const firstValidationError = Object.values(errors ?? {})[0];
    useEffect(() => {
        if (firstValidationError) toast.error(firstValidationError);
    }, [firstValidationError]);

    const isActive = (href: string) => href === '/dashboard' ? path === href : path.startsWith(href);

    return (
        <div className="min-h-screen bg-canvas-50">
            <a href="#main-content" className="fixed top-2 left-2 z-[80] -translate-y-20 rounded-lg bg-white px-4 py-2 text-sm font-bold text-primary-700 shadow-lg transition focus:translate-y-0">Lewati ke konten</a>
            {sidebarOpen && <button aria-label="Tutup navigasi" onClick={() => setSidebarOpen(false)} className="fixed inset-0 z-40 bg-navy-950/50 lg:hidden" />}
            <aside aria-hidden={!desktop && !sidebarOpen} inert={!desktop && !sidebarOpen ? true : undefined} className={cn('fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-navy-950 text-slate-200 shadow-2xl transition-transform lg:translate-x-0', sidebarOpen ? 'translate-x-0' : '-translate-x-full')}>
                <div className="flex min-h-20 items-center gap-3 border-b border-white/10 px-5">
                    <span className="grid size-11 shrink-0 place-items-center [clip-path:polygon(50%_0,92%_16%,86%_76%,50%_100%,14%_76%,8%_16%)] bg-amber-400 text-sm font-black text-navy-950">LPJ</span>
                    <div className="min-w-0"><strong className="block truncate text-base text-white">Smart e-LPJ</strong><span className="block truncate text-[11px] text-slate-400">SOBE PB PERKEMI</span></div>
                    <button aria-label="Tutup menu" className="ml-auto grid size-11 place-items-center rounded-lg text-slate-300 hover:bg-white/10 lg:hidden" onClick={() => setSidebarOpen(false)}><X className="size-5" /></button>
                </div>
                <div className="mx-3 mt-4 rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2.5">
                    <div className="flex items-center gap-2"><span className="size-2 rounded-full bg-emerald-400 ring-4 ring-emerald-400/10" /><div className="min-w-0"><b className="block truncate text-xs text-white">{user.unit?.name || 'Lintas unit'}</b><span className="block truncate text-[10px] text-slate-400">{user.roles.join(', ')}</span></div></div>
                </div>
                <nav aria-label="Navigasi utama" className="mt-2 flex-1 overflow-y-auto px-3 pb-5">
                    {visibleGroups.map((group) => <div key={group.label} className="mt-5">
                        <p className="mb-1.5 px-2 text-[10px] font-bold tracking-wide text-slate-400">{group.label}</p>
                        <div className="grid gap-0.5">{group.items.map((item) => {
                            const Icon = item.icon;
                            return <Link key={item.href} href={item.href} aria-current={isActive(item.href) ? 'page' : undefined} onClick={() => setSidebarOpen(false)} className={cn('relative flex min-h-10 items-center gap-3 rounded-lg px-2.5 text-[13px] font-medium text-slate-300 transition hover:bg-white/[0.07] hover:text-white', isActive(item.href) && 'bg-primary-600/25 text-white before:absolute before:inset-y-2 before:-left-3 before:w-0.5 before:rounded-r before:bg-blue-400')}><Icon aria-hidden="true" className="size-[18px] shrink-0" /><span className="truncate">{item.label}</span></Link>;
                        })}</div>
                    </div>)}
                </nav>
                <div className="border-t border-white/10 px-4 py-3 text-[10px] text-slate-400"><b className="block text-slate-200">PB PERKEMI</b>Akuntabilitas berbasis Activity-ID</div>
            </aside>

            <div className="min-w-0 lg:pl-72">
                {system.maintenance_message && <div role="status" className="flex min-h-11 items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900 sm:px-6"><Megaphone aria-hidden="true" className="size-4 shrink-0" /><span>{system.maintenance_message}</span></div>}
                <header className="sticky top-0 z-30 flex min-h-17 items-center gap-3 border-b border-line-200 bg-white/95 px-4 backdrop-blur-md sm:px-6">
                    <button aria-label="Buka menu" onClick={() => setSidebarOpen(true)} className="grid size-11 shrink-0 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden"><Menu className="size-5" /></button>
                    <button aria-haspopup="dialog" aria-expanded={searchOpen} onClick={() => setSearchOpen(true)} className="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 text-left text-xs text-slate-500 transition hover:border-slate-400 hover:bg-white sm:max-w-xl"><Search aria-hidden="true" className="size-4 shrink-0" /><span className="truncate">Cari Activity-ID, LPJ-ID, transaksi, evidence…</span><kbd className="ml-auto hidden rounded border border-slate-300 bg-white px-1.5 py-0.5 text-[9px] font-bold sm:inline">⌘K</kbd></button>
                    <div className="relative ml-auto">
                        <button aria-label="Notifikasi" aria-expanded={notificationsOpen} onClick={() => setNotificationsOpen((value) => !value)} className="relative grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100"><Bell aria-hidden="true" className="size-5" />{notifications.length > 0 && <span className="absolute top-1.5 right-1.5 grid size-4 place-items-center rounded-full border-2 border-white bg-danger-600 text-[8px] font-bold text-white">{notifications.length}</span>}</button>
                        {notificationsOpen && <div className="absolute top-12 right-0 w-[min(360px,calc(100vw-24px))] overflow-hidden rounded-xl border border-line-200 bg-white shadow-2xl"><div className="border-b border-line-200 px-4 py-3"><b className="text-sm">Notifikasi</b><p className="text-[11px] text-muted-600">Tugas dan perubahan workflow terbaru</p></div>{notifications.length ? notifications.map((item) => <Link key={item.id} href={item.data.url || '#'} className="block border-b border-slate-100 px-4 py-3 hover:bg-slate-50"><b className="block text-xs text-slate-800">{item.data.title || 'Pembaruan workflow'}</b><span className="mt-1 block text-[11px] text-muted-600">{item.data.message || item.created_at}</span></Link>) : <p className="px-4 py-8 text-center text-xs text-muted-600">Tidak ada notifikasi baru.</p>}</div>}
                    </div>
                    <div className="relative">
                        <button aria-expanded={userOpen} onClick={() => setUserOpen((value) => !value)} className="flex min-h-11 items-center gap-2 rounded-lg px-1.5 hover:bg-slate-100"><span className="grid size-8 place-items-center rounded-full bg-blue-100 text-[10px] font-black text-primary-700">{initials(user.name)}</span><span className="hidden max-w-32 text-left sm:block"><b className="block truncate text-xs text-slate-800">{user.name}</b><span className="block truncate text-[9px] text-muted-600">{user.roles[0]}</span></span><ChevronDown aria-hidden="true" className="hidden size-4 text-slate-500 sm:block" /></button>
                        {userOpen && <div className="absolute top-12 right-0 w-56 rounded-xl border border-line-200 bg-white p-1.5 shadow-2xl"><Link href="/settings/profile" className="flex min-h-10 items-center gap-2 rounded-lg px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"><Settings aria-hidden="true" className="size-4" />Profil & keamanan</Link><Link href="/about" className="flex min-h-10 items-center gap-2 rounded-lg px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"><Info aria-hidden="true" className="size-4" />Informasi sistem</Link><button onClick={() => router.post('/logout')} className="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 text-xs font-semibold text-danger-600 hover:bg-red-50"><LogOut aria-hidden="true" className="size-4" />Keluar</button></div>}
                    </div>
                </header>
                <NetworkStatus />
                <main id="main-content" className="mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">{children}</main>
            </div>

            <GlobalSearch open={searchOpen} onClose={() => setSearchOpen(false)} shortcuts={searchShortcuts} />
            <AiReviewRealtime userId={user.id} />
            <Toaster richColors position="top-right" />
        </div>
    );
}
