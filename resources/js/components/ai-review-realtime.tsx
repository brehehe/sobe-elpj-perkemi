import { useEcho } from '@laravel/echo-react';
import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { toast } from 'sonner';

type AiReviewStatusUpdated = {
    review_id: string;
    status: 'RUNNING' | 'REVIEW_REQUIRED' | 'SUCCEEDED' | 'FAILED' | 'CANCELLED';
    type: string;
};

export function AiReviewRealtime({ userId }: { userId: string }) {
    const path = usePage().url.split('?')[0];

    const handleStatusUpdate = useCallback((event: AiReviewStatusUpdated) => {
        const toastId = `ai-review-${event.review_id}`;

        if (event.status === 'RUNNING') {
            toast.loading(`Pemeriksaan ${event.type.replaceAll('_', ' ')} sedang diproses.`, { id: toastId });
        } else if (event.status === 'REVIEW_REQUIRED') {
            toast.success(`Pemeriksaan ${event.type.replaceAll('_', ' ')} siap direview manusia.`, { id: toastId });
        } else if (event.status === 'SUCCEEDED') {
            toast.success('Hasil AI telah diterima reviewer manusia.', { id: toastId });
        } else if (event.status === 'CANCELLED') {
            toast.warning('Pemeriksaan AI dibatalkan. Workflow utama tetap dapat dilanjutkan.', { id: toastId });
        } else {
            toast.error('Pemeriksaan AI gagal. Workflow utama tetap dapat dilanjutkan.', { id: toastId });
        }

        if (path === '/intelligence' || path.startsWith('/lpjs/')) {
            router.reload();
        }
    }, [path]);

    useEcho<AiReviewStatusUpdated>(`users.${userId}.ai-reviews`, 'AiReviewStatusUpdated', handleStatusUpdate);

    return null;
}
