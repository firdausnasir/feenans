import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { TooltipProvider } from '@/components/ui/tooltip';
import { PrivacyModeProvider } from '@/contexts/privacy-mode-context';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const render = await createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    pages: './pages',
    withApp: (app) => (
        <TooltipProvider delayDuration={0}>
            <PrivacyModeProvider>{app}</PrivacyModeProvider>
        </TooltipProvider>
    ),
});

if (typeof render !== 'function') {
    throw new Error('SSR entry must resolve to an Inertia render function.');
}

createServer((page) =>
    render(page as Parameters<typeof render>[0], ReactDOMServer.renderToString),
);
