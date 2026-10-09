import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { ComponentType } from 'react';
import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Smart e-LPJ SOBE';
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
    title: (title) => title ? `${title} · ${appName}` : appName,
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];
        if (!page) throw new Error(`Halaman Inertia tidak ditemukan: ${name}`);
        return page().then((module) => module.default);
    },
    layout: (name) => name.startsWith('auth/') || name.startsWith('public/') || name.startsWith('errors/') ? null : AppLayout,
    progress: { color: '#1769E0', showSpinner: true },
    strictMode: true,
});
