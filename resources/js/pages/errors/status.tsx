import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, LifeBuoy, TriangleAlert } from 'lucide-react';

type ErrorStatusProps = {
    status: number;
    requestId: string;
    authenticated: boolean;
    supportMessage: string;
};

const errorContent: Record<number, { eyebrow: string; title: string; description: string }> = {
    403: {
        eyebrow: 'Kewenangan diperlukan',
        title: 'Akses dibatasi',
        description: 'Akun Anda tidak memiliki kewenangan untuk membuka area ini. Kembali ke ruang kerja atau hubungi administrator bila akses tersebut diperlukan.',
    },
    404: {
        eyebrow: 'Tujuan tidak ditemukan',
        title: 'Halaman tidak ditemukan',
        description: 'Alamat mungkin berubah, sudah tidak tersedia, atau tidak termasuk dalam ruang kerja Anda.',
    },
    429: {
        eyebrow: 'Batas permintaan tercapai',
        title: 'Terlalu banyak permintaan',
        description: 'Sistem sedang membatasi permintaan berulang. Tunggu sebentar, lalu coba kembali.',
    },
    500: {
        eyebrow: 'Insiden aplikasi',
        title: 'Gangguan sistem',
        description: 'Permintaan belum dapat diselesaikan. Data yang sudah tersimpan tetap aman dan tim dukungan dapat menelusuri insiden melalui Request ID.',
    },
    503: {
        eyebrow: 'Layanan belum tersedia',
        title: 'Layanan sementara tidak tersedia',
        description: 'Sistem sedang menjalani pemeliharaan atau pemulihan. Coba kembali beberapa saat lagi.',
    },
};

export default function ErrorStatus({ status, requestId, authenticated, supportMessage }: ErrorStatusProps) {
    const content = errorContent[status] ?? {
        eyebrow: 'Permintaan terhenti',
        title: 'Halaman belum dapat ditampilkan',
        description: 'Sistem tidak dapat menyelesaikan permintaan ini. Coba kembali atau gunakan Request ID saat menghubungi dukungan.',
    };
    const destination = authenticated ? '/dashboard' : '/login';
    const destinationLabel = authenticated ? 'Kembali ke dashboard' : 'Kembali ke halaman masuk';

    return (
        <>
            <Head title={`${status} — ${content.title}`} />
            <main className="grid min-h-screen place-items-center bg-canvas-50 px-4 py-8 sm:px-6 lg:px-8">
                <section
                    role="alert"
                    aria-labelledby="error-title"
                    className="w-full max-w-4xl overflow-hidden rounded-2xl border border-line-200 bg-white shadow-xl shadow-navy-950/10 md:grid md:grid-cols-[minmax(220px,0.75fr)_minmax(0,1.5fr)]"
                >
                    <div className="relative flex min-h-56 flex-col justify-between overflow-hidden bg-navy-950 p-6 text-white sm:p-8 md:min-h-[440px]">
                        <div aria-hidden="true" className="absolute -top-16 -right-16 size-56 rounded-full border-[40px] border-primary-500/20" />
                        <Link href={destination} className="relative flex w-fit items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                            <span className="grid size-11 place-items-center [clip-path:polygon(50%_0,92%_16%,86%_76%,50%_100%,14%_76%,8%_16%)] bg-amber-400 text-sm font-black text-navy-950">LPJ</span>
                            <span>
                                <strong className="block text-sm">Smart e-LPJ</strong>
                                <span className="block text-[11px] text-slate-300">SOBE PB PERKEMI</span>
                            </span>
                        </Link>
                        <div className="relative mt-10">
                            <p className="text-xs font-bold tracking-wide text-blue-300">Status permintaan</p>
                            <p className="mt-2 font-mono text-7xl font-black tracking-tight text-white tabular-nums">{status}</p>
                            <div className="mt-5 h-1 w-16 rounded-full bg-amber-400" />
                        </div>
                    </div>

                    <div className="flex flex-col justify-center p-6 sm:p-9 lg:p-12">
                        <span className="mb-5 grid size-12 place-items-center rounded-xl bg-amber-100 text-amber-800">
                            <TriangleAlert aria-hidden="true" className="size-6" />
                        </span>
                        <p className="text-xs font-bold tracking-wide text-primary-700">{content.eyebrow}</p>
                        <h1 id="error-title" className="mt-2 text-3xl font-black tracking-tight text-navy-950 sm:text-4xl">{content.title}</h1>
                        <p className="mt-4 max-w-xl text-sm leading-6 text-muted-600 sm:text-base">{content.description}</p>

                        <div className="mt-6 rounded-xl border border-line-200 bg-slate-50 p-4">
                            <div className="flex items-start gap-3">
                                <LifeBuoy aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-primary-700" />
                                <div className="min-w-0">
                                    <p className="text-xs font-bold text-slate-900">Bantuan penelusuran</p>
                                    <p className="mt-1 text-xs leading-5 text-muted-600">{supportMessage}</p>
                                    <p className="mt-3 text-[11px] font-semibold text-muted-600">Request ID</p>
                                    <code className="mt-1 block overflow-x-auto rounded-md border border-slate-200 bg-white px-2.5 py-2 font-mono text-[11px] text-slate-800">{requestId}</code>
                                </div>
                            </div>
                        </div>

                        <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                            >
                                <ArrowLeft aria-hidden="true" className="size-4" />
                                Halaman sebelumnya
                            </button>
                            <Link
                                href={destination}
                                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-bold text-white transition hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                            >
                                {destinationLabel}
                                <ArrowRight aria-hidden="true" className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
