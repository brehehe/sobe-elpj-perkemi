import { RefreshCw, Wifi, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type ConnectionState = 'online' | 'offline' | 'restored';

export function NetworkStatus() {
    const [connectionState, setConnectionState] = useState<ConnectionState>(() => (
        typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'online'
    ));

    useEffect(() => {
        let restoredTimer: number | undefined;

        const handleOffline = () => {
            window.clearTimeout(restoredTimer);
            setConnectionState('offline');
        };
        const handleOnline = () => {
            setConnectionState('restored');
            restoredTimer = window.setTimeout(() => setConnectionState('online'), 5_000);
        };

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            window.clearTimeout(restoredTimer);
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, []);

    if (connectionState === 'online') {
        return null;
    }

    const isOffline = connectionState === 'offline';
    const Icon = isOffline ? WifiOff : Wifi;

    return (
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            className={cn(
                'flex min-h-12 flex-wrap items-center gap-x-3 gap-y-2 border-b px-4 py-2.5 text-xs sm:px-6',
                isOffline
                    ? 'border-amber-300 bg-amber-50 text-amber-950'
                    : 'border-emerald-300 bg-emerald-50 text-emerald-950',
            )}
        >
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <strong className="font-bold">{isOffline ? 'Koneksi terputus' : 'Koneksi pulih'}</strong>
                <span className="ml-1.5">
                    {isOffline
                        ? 'Data tetap terlihat, tetapi perubahan baru belum dapat dikirim.'
                        : 'Muat ulang untuk mengambil data terbaru dari sistem.'}
                </span>
            </div>
            <button
                type="button"
                onClick={() => window.location.reload()}
                className={cn(
                    'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border bg-white px-3 font-bold shadow-sm transition hover:bg-white/70 focus-visible:outline-2 focus-visible:outline-offset-2',
                    isOffline
                        ? 'border-amber-400 text-amber-950 focus-visible:outline-amber-700'
                        : 'border-emerald-400 text-emerald-950 focus-visible:outline-emerald-700',
                )}
            >
                <RefreshCw aria-hidden="true" className="size-3.5" />
                Muat ulang data
            </button>
        </div>
    );
}
