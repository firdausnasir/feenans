import { Link, usePage } from '@inertiajs/react';
import { Check, ChevronDown, WalletCards } from 'lucide-react';
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
import { create, dashboard, index } from '@/routes/ledgers';
import type { Ledger } from '@/types';

type LedgerSummary = Pick<Ledger, 'id' | 'name' | 'currency_code'>;

export function LedgerSwitcher() {
    const { currentLedger, availableLedgers } = usePage().props as {
        currentLedger: LedgerSummary | null;
        availableLedgers: LedgerSummary[];
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                        >
                            <div className="flex size-8 items-center justify-center rounded-md bg-sidebar-accent text-sidebar-accent-foreground">
                                <WalletCards className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-medium">
                                    {currentLedger?.name ?? 'Choose ledger'}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    {currentLedger?.currency_code ??
                                        'No active ledger'}
                                </span>
                            </div>
                            <ChevronDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        className="w-64 rounded-lg"
                        align="start"
                        side="bottom"
                    >
                        <DropdownMenuLabel>Switch ledger</DropdownMenuLabel>
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
                                View all ledgers
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={create.url()} prefetch>
                                Create ledger
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
