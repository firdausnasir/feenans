import { createInertiaApp } from '@inertiajs/react';
import { TooltipProvider } from '@/components/ui/tooltip';
import { PrivacyModeProvider } from '@/contexts/privacy-mode-context';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

if (typeof window !== 'undefined') {
    initializeTheme();
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    pages: './pages',
    strictMode: false,
    withApp: (app) => (
        <TooltipProvider delayDuration={0}>
            <PrivacyModeProvider>{app}</PrivacyModeProvider>
        </TooltipProvider>
    ),
    progress: {
        color: '#4B5563',
    },
});
