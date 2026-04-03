import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { sidebarInsetClassName } from '@/components/layout-header-classes';
import { ScrollToTopButton } from '@/components/scroll-to-top-button';
import { Toaster } from '@/components/ui/sonner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className={sidebarInsetClassName}>
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="min-w-0 overflow-x-hidden">{children}</div>
            </AppContent>
            <Toaster />
            <ScrollToTopButton />
        </AppShell>
    );
}
