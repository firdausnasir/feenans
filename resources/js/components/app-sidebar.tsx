import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CreditCard,
    Hash,
    LayoutGrid,
    PiggyBank,
    Receipt,
    RefreshCw,
    Settings,
    Tag,
    Upload,
    Users,
} from 'lucide-react';
import { LedgerSwitcher } from '@/components/ledger-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import { index as billsIndex } from '@/routes/ledgers/bills';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { index as categoriesIndex } from '@/routes/ledgers/categories';
import { index as payeesIndex } from '@/routes/ledgers/payees';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import { index as tagsIndex } from '@/routes/ledgers/tags';
import { index as settingsIndex } from '@/routes/ledgers/settings';
import { create as importCreate } from '@/routes/ledgers/import';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentLedger } = usePage().props as {
        currentLedger: {
            id: number;
            name: string;
            currency_code: string;
        } | null;
    };

    const { isCurrentUrl } = useCurrentUrl();

    const ledgerId = currentLedger?.id;

    const mainNavItems: NavItem[] = ledgerId
        ? [
              {
                  title: 'Dashboard',
                  href: dashboard.url(ledgerId),
                  icon: LayoutGrid,
              },
              {
                  title: 'Accounts',
                  href: accountsIndex.url(ledgerId),
                  icon: CreditCard,
              },
              {
                  title: 'Transactions',
                  href: transactionsIndex.url(ledgerId),
                  icon: Receipt,
              },
              {
                  title: 'Import',
                  href: importCreate.url(ledgerId),
                  icon: Upload,
              },
              {
                  title: 'Categories',
                  href: categoriesIndex.url(ledgerId),
                  icon: Tag,
              },
              {
                  title: 'Tags',
                  href: tagsIndex.url(ledgerId),
                  icon: Hash,
              },
              {
                  title: 'Recurring',
                  href: billsIndex.url(ledgerId),
                  icon: RefreshCw,
              },
              {
                  title: 'Budgets',
                  href: budgetsIndex.url(ledgerId),
                  icon: PiggyBank,
              },
              {
                  title: 'Payees',
                  href: payeesIndex.url(ledgerId),
                  icon: Users,
              },
              {
                  title: 'Reports',
                  href: reportsIndex.url(ledgerId),
                  icon: BarChart3,
              },
          ]
        : [];

    const settingsHref = ledgerId ? settingsIndex.url(ledgerId) : null;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <LedgerSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {settingsHref && (
                    <>
                        <SidebarSeparator />
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentUrl(settingsHref)}
                                    tooltip={{ children: 'Settings' }}
                                >
                                    <Link href={settingsHref} prefetch>
                                        <Settings />
                                        <span>Settings</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
