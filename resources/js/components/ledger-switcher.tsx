import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Crown, Plus, WalletCards } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { premium } from '@/routes';
import { create, dashboard, index } from '@/routes/ledgers';
import type { Ledger } from '@/types';

type LedgerSummary = Pick<Ledger, 'id' | 'name' | 'currency_code'>;

export function LedgerSwitcher() {
    const { currentLedger, availableLedgers, auth } = usePage().props as {
        currentLedger: LedgerSummary | null;
        availableLedgers: LedgerSummary[];
        auth: { user?: { membership: { is_premium: boolean } } };
    };

    const isSingleLedger = availableLedgers.length <= 1;
    const isPremiumUser = auth.user?.membership.is_premium ?? false;
    const canCreateWorkspace = isPremiumUser || availableLedgers.length < 1;
    const createWorkspaceHref = canCreateWorkspace
        ? create.url()
        : premium.url();

    const ledgerCardContent = (
        <>
            <div className="flex size-8 items-center justify-center rounded-md bg-sidebar-accent text-sidebar-accent-foreground">
                <WalletCards className="size-4" />
            </div>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">
                    {currentLedger?.name ?? 'Choose workspace'}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {currentLedger?.currency_code ?? 'No active workspace'}
                </span>
            </div>
        </>
    );

    if (isSingleLedger && currentLedger) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            >
                                {ledgerCardContent}
                                <ChevronDown className="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            className="w-64 rounded-lg"
                            align="start"
                            side="bottom"
                        >
                            <DropdownMenuLabel>Workspace</DropdownMenuLabel>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem asChild>
                                <Link
                                    href={dashboard.url(currentLedger.id)}
                                    className="flex w-full items-center gap-2"
                                >
                                    <span className="flex-1 truncate">
                                        {currentLedger.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {currentLedger.currency_code}
                                    </span>
                                    <Check className="size-4" />
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link
                                    href={createWorkspaceHref}
                                    className="flex w-full items-center gap-2"
                                    prefetch={canCreateWorkspace}
                                >
                                    <Plus className="size-4" />
                                    Create workspace
                                    {!canCreateWorkspace && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-auto gap-1 text-[10px] leading-none"
                                        >
                                            <Crown className="size-2.5" />
                                            Premium
                                        </Badge>
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                        >
                            {ledgerCardContent}
                            <ChevronDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="w-64 rounded-lg"
                        align="start"
                        side="bottom"
                    >
                        <DropdownMenuLabel>Switch workspace</DropdownMenuLabel>
                        <DropdownMenuSeparator />

                        {availableLedgers.map((ledger) => (
                            <DropdownMenuItem key={ledger.id} asChild>
                                <Link
                                    href={dashboard.url(ledger.id)}
                                    className="flex w-full items-center gap-2"
                                    prefetch
                                >
                                    <span className="flex-1 truncate">
                                        {ledger.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {ledger.currency_code}
                                    </span>
                                    {currentLedger?.id === ledger.id && (
                                        <Check className="size-4" />
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        ))}

                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={index.url()} prefetch>
                                View all workspaces
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                href={createWorkspaceHref}
                                prefetch={canCreateWorkspace}
                            >
                                Create workspace
                                {!canCreateWorkspace && (
                                    <Badge
                                        variant="secondary"
                                        className="ml-auto gap-1 text-[10px] leading-none"
                                    >
                                        <Crown className="size-2.5" />
                                        Premium
                                    </Badge>
                                )}
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
