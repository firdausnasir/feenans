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
import type { NavGroup } from '@/types';
import { dashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import { index as billsIndex } from '@/routes/ledgers/bills';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { index as categoriesIndex } from '@/routes/ledgers/categories';
import { create as importCreate } from '@/routes/ledgers/import';
import { index as payeesIndex } from '@/routes/ledgers/payees';
import { index as reportsIndex } from '@/routes/ledgers/reports';
import { index as settingsIndex } from '@/routes/ledgers/settings';
import { index as tagsIndex } from '@/routes/ledgers/tags';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';

export function AppSidebar() {
    const { currentLedger } = usePage().props as {
        currentLedger: {
            id: number;
            name: string;
            currency_code: string;
        } | null;
    };

    const { isCurrentOrParentUrl } = useCurrentUrl();

    const ledgerId = currentLedger?.id;

    const navGroups: NavGroup[] = ledgerId
        ? [
              {
                  label: 'Overview',
                  items: [
                      {
                          title: 'Dashboard',
                          href: dashboard.url(ledgerId),
                          icon: LayoutGrid,
                      },
                      {
                          title: 'Reports',
                          href: reportsIndex.url(ledgerId),
                          icon: BarChart3,
                      },
                  ],
              },
              {
                  label: 'Activity',
                  items: [
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
                          title: 'Recurring',
                          href: billsIndex.url(ledgerId),
                          icon: RefreshCw,
                      },
                  ],
              },
              {
                  label: 'Plan',
                  items: [
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
                  ],
              },
              {
                  label: 'Manage',
                  items: [
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
                          title: 'Import',
                          href: importCreate.url(ledgerId),
                          icon: Upload,
                      },
                  ],
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
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                {settingsHref && (
                    <>
                        <SidebarSeparator />
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(
                                        settingsHref,
                                    )}
                                    tooltip={{ children: 'Workspace Settings' }}
                                >
                                    <Link href={settingsHref} prefetch>
                                        <Settings />
                                        <span>Workspace Settings</span>
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
