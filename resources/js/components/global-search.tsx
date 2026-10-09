import { router } from '@inertiajs/react';
import {
    Activity as ActivityIcon,
    AlertCircle,
    AlertTriangle,
    Building2,
    FileClock,
    FolderLock,
    LoaderCircle,
    ReceiptText,
    Search,
    SearchX,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

export type GlobalSearchShortcut = {
    label: string;
    href: string;
    group: string;
    icon: LucideIcon;
};

type ServerSearchResultType = 'activity' | 'lpj' | 'transaction' | 'evidence' | 'finding' | 'unit';

type ServerSearchResult = {
    id: string;
    type: ServerSearchResultType;
    type_label: string;
    title: string;
    subtitle: string;
    meta: string;
    status: string;
    href: string;
};

type DisplaySearchResult = Omit<ServerSearchResult, 'type'> & { type: ServerSearchResultType | 'navigation'; icon: LucideIcon };

const resultIcons: Record<ServerSearchResultType, LucideIcon> = {
    activity: ActivityIcon,
    lpj: FileClock,
    transaction: ReceiptText,
    evidence: FolderLock,
    finding: AlertTriangle,
    unit: Building2,
};

export function GlobalSearch({ open, onClose, shortcuts }: { open: boolean; onClose: () => void; shortcuts: GlobalSearchShortcut[] }) {
    const dialogRef = useRef<HTMLDialogElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [serverResults, setServerResults] = useState<ServerSearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [retryNonce, setRetryNonce] = useState(0);
    const normalizedQuery = query.trim().toLocaleLowerCase('id-ID');

    const shortcutResults = useMemo<DisplaySearchResult[]>(() => shortcuts
        .filter((shortcut) => normalizedQuery.length === 0
            || `${shortcut.label} ${shortcut.group}`.toLocaleLowerCase('id-ID').includes(normalizedQuery))
        .map((shortcut) => ({
            id: `navigation-${shortcut.href}`,
            type: 'navigation' as const,
            type_label: 'Navigasi',
            title: shortcut.label,
            subtitle: shortcut.group,
            meta: 'Buka modul',
            status: '',
            href: shortcut.href,
            icon: shortcut.icon,
        })), [normalizedQuery, shortcuts]);

    const results = useMemo<DisplaySearchResult[]>(() => [
        ...shortcutResults,
        ...serverResults.map((result) => ({ ...result, icon: resultIcons[result.type] })),
    ], [serverResults, shortcutResults]);

    useEffect(() => {
        const dialog = dialogRef.current;

        if (open && dialog && !dialog.open) {
            dialog.showModal();
            requestAnimationFrame(() => inputRef.current?.focus());
        }

        if (!open && dialog?.open) {
            dialog.close();
        }
    }, [open]);

    useEffect(() => {
        if (open) {
            return;
        }

        setQuery('');
        setServerResults([]);
        setLoading(false);
        setError(null);
        setActiveIndex(-1);
    }, [open]);

    useEffect(() => {
        if (!open || normalizedQuery.length < 2) {
            setServerResults([]);
            setLoading(false);
            setError(null);

            return;
        }

        const controller = new AbortController();
        setServerResults([]);
        setError(null);
        setLoading(true);
        const debounce = window.setTimeout(async () => {
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(query.trim())}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(response.status === 429
                        ? 'Terlalu banyak pencarian. Tunggu sebentar lalu coba lagi.'
                        : 'Pencarian belum dapat dimuat. Periksa koneksi lalu coba lagi.');
                }

                const payload = await response.json() as { data: ServerSearchResult[] };
                setServerResults(payload.data);
            } catch (requestError) {
                if (requestError instanceof DOMException && requestError.name === 'AbortError') {
                    return;
                }

                setError(requestError instanceof Error ? requestError.message : 'Pencarian belum dapat dimuat.');
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(debounce);
            controller.abort();
        };
    }, [normalizedQuery, open, query, retryNonce]);

    useEffect(() => {
        setActiveIndex(results.length > 0 ? 0 : -1);
    }, [results]);

    useEffect(() => {
        if (activeIndex >= 0) {
            document.getElementById(`global-search-option-${activeIndex}`)?.scrollIntoView({ block: 'nearest' });
        }
    }, [activeIndex]);

    const close = () => {
        onClose();
    };

    const openResult = (result: DisplaySearchResult) => {
        close();
        router.visit(result.href);
    };

    const handleInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' && results.length > 0) {
            event.preventDefault();
            setActiveIndex((current) => current >= results.length - 1 ? 0 : current + 1);
        }

        if (event.key === 'ArrowUp' && results.length > 0) {
            event.preventDefault();
            setActiveIndex((current) => current <= 0 ? results.length - 1 : current - 1);
        }

        if (event.key === 'Enter' && activeIndex >= 0 && results[activeIndex]) {
            event.preventDefault();
            openResult(results[activeIndex]);
        }
    };

    const liveMessage = loading
        ? 'Mencari data ruang kerja.'
        : error
            ? error
            : normalizedQuery.length >= 2
                ? `${results.length} hasil ditemukan.`
                : normalizedQuery.length === 1
                    ? 'Ketik minimal 2 karakter untuk mencari data.'
                    : 'Pilih modul atau ketik kata kunci pencarian.';

    return (
        <dialog
            ref={dialogRef}
            onCancel={(event) => { event.preventDefault(); close(); }}
            onClose={close}
            aria-labelledby="global-search-title"
            className="m-auto h-full max-h-none w-full max-w-none border-0 bg-transparent p-0 backdrop:bg-navy-950/55 backdrop:backdrop-blur-sm"
        >
            <div className="flex h-full items-start justify-center px-3 pt-[7vh] sm:pt-[10vh]" onMouseDown={(event) => event.currentTarget === event.target && close()}>
                <div className="w-full max-w-2xl overflow-hidden rounded-2xl border border-white/15 bg-white shadow-2xl">
                    <h2 id="global-search-title" className="sr-only">Pencarian global</h2>
                    <div className="flex items-center gap-3 border-b border-line-200 px-4">
                        <Search aria-hidden="true" className="size-5 shrink-0 text-slate-400" />
                        <input
                            ref={inputRef}
                            type="search"
                            role="combobox"
                            aria-label="Cari seluruh ruang kerja"
                            aria-expanded={open}
                            aria-controls="global-search-results"
                            aria-autocomplete="list"
                            aria-activedescendant={activeIndex >= 0 ? `global-search-option-${activeIndex}` : undefined}
                            autoComplete="off"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            onKeyDown={handleInputKeyDown}
                            placeholder="Cari Activity-ID, LPJ-ID, transaksi, evidence, unit, finding…"
                            className="min-h-14 flex-1 border-0 bg-transparent text-base text-ink-950 outline-none placeholder:text-slate-400 sm:text-sm"
                        />
                        {loading && <LoaderCircle aria-hidden="true" className="size-4 shrink-0 animate-spin text-primary-600 motion-reduce:animate-none" />}
                        <button type="button" aria-label="Tutup pencarian" onClick={close} className="grid size-11 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"><X aria-hidden="true" className="size-4" /></button>
                    </div>

                    <p aria-live="polite" className="sr-only">{liveMessage}</p>
                    {normalizedQuery.length === 0 && <div className="border-b border-line-200 bg-slate-50 px-4 py-2 text-[10px] font-semibold text-slate-500">PILIH MODUL ATAU CARI DATA YANG DAPAT ANDA AKSES</div>}

                    <div className="max-h-[min(62vh,520px)] overflow-y-auto p-2">
                        {error && <div role="alert" className="m-2 rounded-xl border border-red-200 bg-red-50 p-4 text-center"><AlertCircle aria-hidden="true" className="mx-auto size-5 text-danger-600" /><p className="mt-2 text-xs font-semibold text-red-900">{error}</p><button type="button" onClick={() => setRetryNonce((value) => value + 1)} className="mt-3 min-h-10 rounded-lg border border-red-300 bg-white px-4 text-xs font-bold text-red-700 hover:bg-red-100">Coba lagi</button></div>}

                        <div id="global-search-results" role="listbox" aria-label="Hasil pencarian" aria-busy={loading} className="grid gap-1">
                            {!error && results.map((result, index) => {
                                const Icon = result.icon;

                                return <button
                                    key={result.id}
                                    id={`global-search-option-${index}`}
                                    type="button"
                                    role="option"
                                    aria-selected={activeIndex === index}
                                    onMouseMove={() => setActiveIndex(index)}
                                    onClick={() => openResult(result)}
                                    className={cn('flex min-h-16 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-600', activeIndex === index ? 'bg-blue-50 ring-1 ring-inset ring-blue-200' : 'hover:bg-slate-50')}
                                >
                                    <span className={cn('grid size-9 shrink-0 place-items-center rounded-lg', result.type === 'finding' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-primary-700')}><Icon aria-hidden="true" className="size-4" /></span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[9px] font-bold tracking-wide text-slate-600">{result.type_label}</span>
                                        <b className="block truncate text-xs text-slate-800">{result.title}</b>
                                        <span className="mt-0.5 block truncate text-[10px] text-muted-600">{result.subtitle} · {result.meta}</span>
                                    </span>
                                    {result.status && <span className="max-w-24 shrink-0 truncate rounded-full border border-slate-200 bg-white px-2 py-1 text-[9px] font-bold text-slate-600">{result.status.replaceAll('_', ' ')}</span>}
                                </button>;
                            })}
                        </div>

                        {!error && !loading && results.length === 0 && normalizedQuery.length >= 2 && <div className="px-5 py-12 text-center"><SearchX aria-hidden="true" className="mx-auto size-7 text-slate-300" /><p className="mt-3 text-sm font-bold text-slate-700">Tidak ada hasil yang cocok</p><p className="mt-1 text-xs text-muted-600">Coba Activity-ID, nama unit, referensi transaksi, atau kode temuan lain.</p></div>}
                        {!error && !loading && results.length === 0 && normalizedQuery.length === 1 && <p className="px-5 py-10 text-center text-xs text-muted-600">Ketik minimal 2 karakter untuk mencari data ruang kerja.</p>}
                    </div>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line-200 bg-slate-50 px-4 py-2 text-[9px] font-semibold text-slate-500"><span>↑↓ pilih</span><span>↵ buka</span><span>Esc tutup</span><span className="ml-auto">Hasil dibatasi sesuai akses Anda</span></div>
                </div>
            </div>
        </dialog>
    );
}
