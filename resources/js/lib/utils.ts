import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCurrency(value: number | string | null | undefined): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export function formatDate(value: string | null | undefined, withTime = false): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(value));
}

export function initials(name: string): string {
    return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}
