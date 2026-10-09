import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AuthLayout({ title, description, children }: { title: string; description: string; children: ReactNode }) {
    return (
        <main className="grid min-h-screen bg-canvas-50 lg:grid-cols-[minmax(380px,0.8fr)_minmax(520px,1.2fr)]">
            <section className="hidden bg-navy-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <Link href="/" className="flex items-center gap-3"><span className="grid size-12 place-items-center [clip-path:polygon(50%_0,92%_16%,86%_76%,50%_100%,14%_76%,8%_16%)] bg-amber-400 text-sm font-black text-navy-950">LPJ</span><div><b className="block text-lg">Smart e-LPJ</b><span className="text-xs text-slate-400">SOBE PB PERKEMI</span></div></Link>
                <div className="max-w-lg"><p className="text-sm font-semibold text-blue-300">Mandat sampai arsip, satu garis data</p><h1 className="mt-4 text-4xl font-bold leading-tight tracking-[-0.04em]">Pertanggungjawaban yang bisa ditelusuri, bukan sekadar dikumpulkan.</h1><p className="mt-5 text-sm leading-7 text-slate-300">Setiap keputusan terikat pada Activity-ID, versi sumber, pemeriksa, dan bukti yang sama.</p></div>
                <p className="text-xs text-slate-400">Akses dibatasi berdasarkan peran dan unit organisasi.</p>
            </section>
            <section className="flex items-center justify-center px-5 py-10 sm:px-8"><div className="w-full max-w-md"><div className="mb-8 flex items-center gap-3 lg:hidden"><span className="grid size-10 place-items-center bg-amber-400 text-xs font-black text-navy-950">LPJ</span><b>Smart e-LPJ</b></div><h2 className="text-2xl font-bold tracking-[-0.03em] text-ink-950">{title}</h2><p className="mt-2 text-sm leading-6 text-muted-600">{description}</p><div className="mt-7 rounded-2xl border border-line-200 bg-white p-5 shadow-sm sm:p-7">{children}</div></div></section>
        </main>
    );
}
