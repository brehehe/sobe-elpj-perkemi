import { formatDate } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, FileCheck2, Fingerprint, LockKeyhole, ShieldCheck, X } from 'lucide-react';

type VerificationRecord = {
    status: 'VALID' | 'INVALID' | 'UNAVAILABLE';
    verification_id: string;
    verification_number: string;
    approval_id: string;
    issued_at: string;
    policy_version: string;
    authority_reference?: string;
    checksum: string;
    algorithm: string;
    checked_at: string;
    checks: Record<string, boolean>;
    lpj: {
        code: string;
        title: string;
        version: number;
        unit?: { code: string; name: string };
    };
};

const checkLabels: Record<string, string> = {
    pack_file_available: 'File Verified Pack tersedia',
    pack_payload_readable: 'Payload dapat dibaca',
    pack_checksum_valid: 'Checksum paket cocok',
    lpj_snapshot_valid: 'Snapshot LPJ utuh',
    approval_signature_valid: 'Tanda tangan approval cocok',
    lineage_valid: 'Lineage ID konsisten',
};

export default function PublicVerification({ verification }: { verification: VerificationRecord }) {
    const isValid = verification.status === 'VALID';
    const isUnavailable = verification.status === 'UNAVAILABLE';
    const statusTitle = isValid ? 'Dokumen terverifikasi' : isUnavailable ? 'Pemeriksaan tidak tersedia' : 'Integritas tidak valid';
    const statusDescription = isValid
        ? 'Referensi ini cocok dengan Verified Pack immutable yang diterbitkan oleh sistem.'
        : isUnavailable
            ? 'File penerbitan tidak dapat diperiksa saat ini. Hubungi pengelola dokumen.'
            : 'Satu atau lebih pemeriksaan integritas gagal. Jangan gunakan dokumen ini sebagai referensi resmi.';

    return <>
        <Head title={`Verifikasi ${verification.verification_number}`} />
        <div className="min-h-screen bg-slate-950 text-slate-100">
            <header className="border-b border-white/10">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                    <div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-blue-600"><ShieldCheck aria-hidden="true" className="size-5" /></span><div><p className="text-sm font-bold">Smart e-LPJ SOBE</p><p className="text-[10px] font-semibold tracking-[0.14em] text-slate-400">PUBLIC VERIFICATION</p></div></div>
                    <span className="hidden rounded-full border border-white/10 px-3 py-1 text-[10px] font-semibold text-slate-400 sm:inline">Read-only reference</span>
                </div>
            </header>

            <main className="mx-auto grid max-w-5xl gap-6 px-5 py-8 sm:px-8 sm:py-12 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="grid content-start gap-6">
                    <section className={`overflow-hidden rounded-2xl border ${isValid ? 'border-emerald-400/25' : 'border-amber-400/30'} bg-white text-slate-950 shadow-2xl shadow-black/20`} aria-labelledby="verification-status">
                        <div className={`p-6 sm:p-8 ${isValid ? 'bg-emerald-50' : 'bg-amber-50'}`}>
                            <div className="flex items-start gap-4">
                                <span className={`grid size-12 shrink-0 place-items-center rounded-full ${isValid ? 'bg-emerald-700 text-white' : 'bg-amber-700 text-white'}`}>{isValid ? <CheckCircle2 aria-hidden="true" className="size-7" /> : <AlertTriangle aria-hidden="true" className="size-7" />}</span>
                                <div><p className={`text-[10px] font-bold tracking-[0.18em] ${isValid ? 'text-emerald-800' : 'text-amber-900'}`}>{verification.status}</p><h1 id="verification-status" className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{statusTitle}</h1><p className="mt-3 max-w-xl text-sm leading-6 text-slate-600">{statusDescription}</p></div>
                            </div>
                        </div>
                        <div className="grid gap-6 p-6 sm:p-8">
                            <div><p className="text-[10px] font-bold tracking-wide text-slate-500">LAPORAN PERTANGGUNGJAWABAN</p><h2 className="mt-2 text-lg font-bold">{verification.lpj.code} · {verification.lpj.title}</h2><p className="mt-2 text-sm text-slate-600">{verification.lpj.unit?.code} · {verification.lpj.unit?.name} · versi {verification.lpj.version}</p></div>
                            <dl className="grid gap-4 border-t border-slate-200 pt-6 sm:grid-cols-2">
                                <div><dt className="flex items-center gap-2 text-[10px] font-bold text-slate-500"><Fingerprint aria-hidden="true" className="size-4" />VERIFICATION-ID</dt><dd className="mt-2 font-mono text-xs font-bold [overflow-wrap:anywhere]">{verification.verification_number}</dd></div>
                                <div><dt className="text-[10px] font-bold text-slate-500">DITERBITKAN</dt><dd className="mt-2 text-xs font-semibold">{formatDate(verification.issued_at, true)}</dd></div>
                                <div><dt className="text-[10px] font-bold text-slate-500">APPROVAL-ID</dt><dd className="mt-2 font-mono text-[10px] [overflow-wrap:anywhere]">{verification.approval_id}</dd></div>
                                <div><dt className="text-[10px] font-bold text-slate-500">REFERENSI OTORITAS</dt><dd className="mt-2 text-xs font-semibold [overflow-wrap:anywhere]">{verification.authority_reference || '—'}</dd></div>
                            </dl>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-white/10 bg-white/5 p-6" aria-labelledby="integrity-title">
                        <div className="flex items-center gap-3"><FileCheck2 aria-hidden="true" className="size-5 text-blue-300" /><div><h2 id="integrity-title" className="text-sm font-bold">Pemeriksaan integritas</h2><p className="mt-1 text-[11px] text-slate-400">Diperiksa {formatDate(verification.checked_at, true)}</p></div></div>
                        <ul className="mt-5 grid gap-2 sm:grid-cols-2">
                            {Object.entries(verification.checks).map(([key, passed]) => <li key={key} className="flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-black/10 px-3 py-2 text-xs"><span className={`grid size-6 shrink-0 place-items-center rounded-full ${passed ? 'bg-emerald-400/15 text-emerald-300' : 'bg-red-400/15 text-red-300'}`}>{passed ? <Check aria-hidden="true" className="size-4" /> : <X aria-hidden="true" className="size-4" />}</span><span>{checkLabels[key] || key}</span></li>)}
                        </ul>
                    </section>
                </div>

                <aside className="grid content-start gap-4">
                    <section className="rounded-2xl border border-blue-400/20 bg-blue-400/10 p-6">
                        <span className="grid size-12 place-items-center rounded-xl border border-blue-300/20 bg-blue-300/10"><LockKeyhole aria-hidden="true" className="size-6 text-blue-200" /></span>
                        <h2 className="mt-5 text-sm font-bold">Sidik jari dokumen</h2>
                        <p className="mt-2 text-[11px] leading-5 text-slate-300">Checksum mengikat isi Verified Pack. Perubahan satu karakter akan menggagalkan validasi.</p>
                        <p className="mt-5 text-[10px] font-bold text-blue-200">{verification.algorithm}</p>
                        <code className="mt-2 block font-mono text-[9px] leading-5 text-slate-300 [overflow-wrap:anywhere]">{verification.checksum}</code>
                    </section>
                    <section className="rounded-2xl border border-white/10 p-5 text-[11px] leading-5 text-slate-400"><p className="font-semibold text-slate-200">{verification.policy_version}</p><p className="mt-2">Halaman ini hanya menampilkan metadata minimum. Isi LPJ dan bukti tetap mengikuti kontrol akses organisasi.</p><p className="mt-3 font-mono text-[9px] [overflow-wrap:anywhere]">Record {verification.verification_id}</p></section>
                </aside>
            </main>
        </div>
    </>;
}
