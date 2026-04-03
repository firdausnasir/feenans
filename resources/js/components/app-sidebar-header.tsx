import { usePage } from '@inertiajs/react';
import { Eye, EyeOff, PlusCircle } from 'lucide-react';
import { useState } from 'react';
import { AddTransactionModal } from '@/components/add-transaction-modal';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { appSidebarHeaderClassName } from '@/components/layout-header-classes';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePrivacyMode } from '@/contexts/privacy-mode-context';
import type { BreadcrumbItem as BreadcrumbItemType, Ledger } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { currentLedger, isAdminArea } = usePage().props;
    const { privacyMode, toggling, togglePrivacyMode } = usePrivacyMode();
    const [addTxOpen, setAddTxOpen] = useState(false);

    return (
        <header className={appSidebarHeaderClassName}>
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 shrink-0" />
                <div className="min-w-0 overflow-hidden">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            </div>
            <div className="ml-auto flex items-center gap-2">
                {!isAdminArea && <NotificationBell />}
                {!isAdminArea && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={togglePrivacyMode}
                                disabled={toggling}
                            >
                                {privacyMode ? (
                                    <EyeOff className="size-4" />
                                ) : (
                                    <Eye className="size-4" />
                                )}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {privacyMode ? 'Show amounts' : 'Hide amounts'}
                        </TooltipContent>
                    </Tooltip>
                )}
                {!isAdminArea && currentLedger && (
                    <>
                        <Button
                            size="sm"
                            className="gap-1.5"
                            onClick={() => setAddTxOpen(true)}
                        >
                            <PlusCircle className="size-4" />
                            <span className="hidden sm:inline">
                                Add Transaction
                            </span>
                            <span className="sm:hidden">Add</span>
                        </Button>
                        <AddTransactionModal
                            ledger={currentLedger as Ledger}
                            externalOpen={addTxOpen}
                            onExternalOpenChange={setAddTxOpen}
                        />
                    </>
                )}
            </div>
        </header>
    );
}
