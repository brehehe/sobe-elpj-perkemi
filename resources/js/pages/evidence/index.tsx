import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Download, FileCheck2, FolderLock, Link2, ScanLine, Search, ShieldCheck, Upload } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';

type EvidenceItem = {
    id: string;
    title: string;
    type: string;
    version: number;
    original_name: string;
    size: number;
    sha256: string;
    scan_status: string;
    review_status: string;
    lifecycle_status: string;
    access_classification: string;
    detected_mime_type?: string;
    scan_engine?: string;
    scan_notes?: string;
    scanned_at?: string;
    created_at: string;
    activity: { id: string; code: string; title: string };
    uploader: { id: string; name: string };
    reviewer?: { id: string; name: string };
    download_url?: string;
};

type Summary = { total: number; uploading: number; scan_failed: number; verified: number };

const stageIcons = [Upload, ScanLine, Link2, ShieldCheck];
const stages = [
    ['Unggah privat', 'Checksum dibuat sebelum file masuk antrean.'],
    ['Pemeriksaan', 'Tipe file, integritas, dan malware diperiksa.'],
    ['Tautkan sumber', 'Bukti bersih ditautkan ke Activity-ID.'],
    ['Review manusia', 'Verifier menerima atau menolak bukti.'],
] as const;

function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function EvidenceIndex({ evidence, summary }: { evidence: EvidenceItem[]; summary: Summary }) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('ALL');
    const deferredQuery = useDeferredValue(query);
    const filteredEvidence = useMemo(() => {
        const normalizedQuery = deferredQuery.trim().toLowerCase();

        return evidence.filter((item) => {
            const matchesStatus = status === 'ALL' || item.lifecycle_status === status;
            const haystack = `${item.title} ${item.type} ${item.original_name} ${item.activity.code} ${item.activity.title} ${item.uploader.name}`.toLowerCase();

            return matchesStatus && (! normalizedQuery || haystack.includes(normalizedQuery));
        });
    }, [deferredQuery, evidence, status]);
    const summaryItems: Array<{ label: string; value: number; icon: LucideIcon }> = [
        { label: 'Total bukti', value: summary.total, icon: FolderLock },
        { label: 'Dalam pemeriksaan', value: summary.uploading, icon: ScanLine },
        { label: 'Perlu diganti', value: summary.scan_failed, icon: FileCheck2 },
        { label: 'Terverifikasi', value: summary.verified, icon: ShieldCheck },
    ];

    return <>
        <Head title="Evidence Center" />
        <PageHeader
            eyebrow="Chain of custody"
            title="Evidence Center"
            description="Pantau bukti privat sejak unggah, pemeriksaan keamanan, linkage Activity-ID, hingga keputusan verifier."
            actions={<Link href="/activities" className="btn-primary"><Upload aria-hidden="true" className="size-4" />Pilih kegiatan untuk unggah</Link>}
        />

        <section className="panel mt-6 overflow-hidden" aria-labelledby="evidence-pipeline-title">
            <div className="border-b border-line-200 px-5 py-4">
                <h2 id="evidence-pipeline-title" className="text-base font-bold">Alur bukti yang dapat diaudit</h2>
                <p className="mt-1 max-w-3xl text-xs leading-5 text-muted-600">File tetap berada di storage privat. Unduhan baru tersedia setelah pemeriksaan keamanan selesai.</p>
            </div>
            <ol className="grid divide-y divide-line-200 md:grid-cols-4 md:divide-x md:divide-y-0">
                {stages.map(([label, description], index) => {
                    const Icon = stageIcons[index];
                    return <li key={label} className="flex gap-3 p-4">
                        <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-blue-50 text-primary-700"><Icon aria-hidden="true" className="size-5" /></span>
                        <div><span className="text-xs font-bold text-slate-800">{index + 1}. {label}</span><p className="mt-1 text-[11px] leading-5 text-muted-600">{description}</p></div>
                    </li>;
                })}
            </ol>
        </section>

        <section className="panel mt-5 grid divide-y divide-line-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4" aria-label="Ringkasan Evidence Center">
            {summaryItems.map(({ label, value, icon: Icon }) => <div key={label} className="flex items-center justify-between gap-4 p-5">
                <div><span className="text-xs text-muted-600">{label}</span><strong className="tabular mt-1 block text-2xl text-ink-950">{value}</strong></div>
                <Icon aria-hidden="true" className="size-5 text-primary-600" />
            </div>)}
        </section>

        <section className="panel mt-5 overflow-hidden" aria-labelledby="evidence-list-title">
            <div className="grid gap-4 border-b border-line-200 p-4 lg:grid-cols-[minmax(0,1fr)_280px_220px] lg:items-end lg:p-5">
                <div><h2 id="evidence-list-title" className="text-base font-bold">Register evidence</h2><p className="mt-1 text-xs text-muted-600">Menampilkan paling banyak 100 bukti terbaru sesuai unit dan kewenangan.</p></div>
                <div><label className="label" htmlFor="evidence-search">Cari bukti</label><div className="relative"><Search aria-hidden="true" className="pointer-events-none absolute top-3.5 left-3 size-4 text-slate-400" /><input id="evidence-search" type="search" className="field pl-10" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Judul, file, atau Activity-ID" /></div></div>
                <div><label className="label" htmlFor="evidence-status-filter">Status lifecycle</label><select id="evidence-status-filter" className="field" value={status} onChange={(event) => setStatus(event.target.value)}><option value="ALL">Semua status</option><option value="UPLOADING">Dalam pemeriksaan</option><option value="SCAN_FAILED">Scan gagal</option><option value="LINKED">Menunggu review</option><option value="VERIFIED">Terverifikasi</option><option value="REJECTED">Ditolak</option><option value="SUPERSEDED">Digantikan</option></select></div>
            </div>

            <p className="sr-only" role="status" aria-live="polite" aria-atomic="true">{filteredEvidence.length} bukti ditampilkan.</p>
            {filteredEvidence.length ? <div className="divide-y divide-slate-100">
                {filteredEvidence.map((item) => <article key={item.id} className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(220px,0.45fr)_auto] lg:items-center lg:p-5">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2"><h3 className="break-words text-sm font-bold text-ink-950">{item.title}</h3><StatusBadge status={item.lifecycle_status} /></div>
                        <p className="mt-1 break-words text-xs leading-5 text-muted-600">{item.activity.code} · {item.activity.title}</p>
                        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-600"><span>{item.type}</span><span>Versi {item.version}</span><span>{item.access_classification}</span><span>{formatFileSize(item.size)}</span><span>Oleh {item.uploader.name}</span></div>
                    </div>
                    <div className="min-w-0 text-[11px] leading-5 text-slate-600">
                        <p><span className="font-semibold text-slate-700">File:</span> <span className="break-all">{item.original_name}</span></p>
                        <p><span className="font-semibold text-slate-700">Diterima:</span> {formatDate(item.created_at, true)}</p>
                        <p><span className="font-semibold text-slate-700">Scan:</span> {item.scanned_at ? `${item.scan_engine || 'ENGINE'} · ${formatDate(item.scanned_at, true)}` : 'Menunggu antrean'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 lg:justify-end">
                        <Link href={`/activities/${item.activity.id}`} className="btn-secondary">Activity-ID<ArrowRight aria-hidden="true" className="size-4" /></Link>
                        {item.download_url ? <a href={item.download_url} className="btn-secondary"><Download aria-hidden="true" className="size-4" />Unduh</a> : <span className="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-600"><FolderLock aria-hidden="true" className="size-4" />Dikarantina</span>}
                    </div>
                    <details className="lg:col-span-3 rounded-lg border border-line-200 bg-slate-50/70 px-4 py-3">
                        <summary className="cursor-pointer text-xs font-bold text-primary-700">Lihat metadata integritas</summary>
                        <dl className="mt-3 grid gap-3 text-[11px] sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt className="font-semibold text-slate-700">Checksum SHA-256</dt><dd className="mt-1 break-all text-slate-600">{item.sha256}</dd></div>
                            <div><dt className="font-semibold text-slate-700">MIME terdeteksi</dt><dd className="mt-1 break-words text-slate-600">{item.detected_mime_type || 'Belum tersedia'}</dd></div>
                            <div><dt className="font-semibold text-slate-700">Catatan scan</dt><dd className="mt-1 break-words text-slate-600">{item.scan_notes || 'Belum tersedia'}</dd></div>
                            <div><dt className="font-semibold text-slate-700">Review</dt><dd className="mt-1 text-slate-600">{item.reviewer ? `${item.review_status} · ${item.reviewer.name}` : item.review_status}</dd></div>
                        </dl>
                    </details>
                </article>)}
            </div> : <EmptyState title="Tidak ada bukti yang cocok" description={query || status !== 'ALL' ? 'Ubah kata kunci atau status filter.' : 'Unggah bukti melalui detail kegiatan.'} />}
        </section>
    </>;
}
