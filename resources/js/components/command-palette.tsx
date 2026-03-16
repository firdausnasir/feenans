import { router } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    LayoutGrid,
    PiggyBank,
    Plus,
    Receipt,
    RefreshCw,
    Settings,
    Tag,
} from 'lucide-react';
import { useCallback } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { dashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import { index as billsIndex } from '@/routes/ledgers/bills';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { index as categoriesIndex } from '@/routes/ledgers/categories';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import { index as settingsIndex } from '@/routes/ledgers/settings';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';

type CommandPaletteProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ledgerId: number | null;
};

export function CommandPalette({
    open,
    onOpenChange,
    ledgerId,
}: CommandPaletteProps) {
    const navigate = useCallback(
        (url: string) => {
            onOpenChange(false);
            router.visit(url);
        },
        [onOpenChange],
    );

    const navigationItems = ledgerId
        ? [
              {
                  label: 'Dashboard',
                  icon: LayoutGrid,
                  url: dashboard.url(ledgerId),
              },
              {
                  label: 'Transactions',
                  icon: Receipt,
                  url: transactionsIndex.url(ledgerId),
              },
              {
                  label: 'Accounts',
                  icon: CreditCard,
                  url: accountsIndex.url(ledgerId),
              },
              {
                  label: 'Recurring',
                  icon: RefreshCw,
                  url: billsIndex.url(ledgerId),
              },
              {
                  label: 'Budgets',
                  icon: PiggyBank,
                  url: budgetsIndex.url(ledgerId),
              },
              {
                  label: 'Reports',
                  icon: BarChart3,
                  url: reportsIndex.url(ledgerId),
              },
              {
                  label: 'Categories',
                  icon: Tag,
                  url: categoriesIndex.url(ledgerId),
              },
              {
                  label: 'Workspace Settings',
                  icon: Settings,
                  url: settingsIndex.url(ledgerId),
              },
          ]
        : [];

    const actionItems = ledgerId
        ? [
              {
                  label: 'New Transaction',
                  icon: Plus,
                  url: transactionsIndex.url(ledgerId, {
                      query: { create: '1' },
                  }),
              },
          ]
        : [];

    return (
        <CommandDialog
            open={open}
            onOpenChange={onOpenChange}
            showCloseButton={false}
        >
            <CommandInput placeholder="Type a command or search..." />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>
                {navigationItems.length > 0 && (
                    <CommandGroup heading="Navigation">
                        {navigationItems.map((item) => (
                            <CommandItem
                                key={item.label}
                                onSelect={() => navigate(item.url)}
                            >
                                <item.icon />
                                <span>{item.label}</span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}
                {actionItems.length > 0 && (
                    <>
                        <CommandSeparator />
                        <CommandGroup heading="Actions">
                            {actionItems.map((item) => (
                                <CommandItem
                                    key={item.label}
                                    onSelect={() => navigate(item.url)}
                                >
                                    <item.icon />
                                    <span>{item.label}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </>
                )}
            </CommandList>
        </CommandDialog>
    );
}
