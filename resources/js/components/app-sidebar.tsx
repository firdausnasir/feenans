import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BarChart3,
    CreditCard,
    Hash,
    LayoutDashboard,
    LayoutGrid,
    MessageSquare,
    PiggyBank,
    Receipt,
    RefreshCw,
    Settings,
    Shield,
    Tag,
    Upload,
    Users as UsersIcon,
} from 'lucide-react';
import { FeedbackDialog } from '@/components/feedback-dialog';
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
    const { currentLedger, isAdminArea } = usePage().props;

    const { isCurrentOrParentUrl } = useCurrentUrl();

    const ledgerId = currentLedger?.id;

    const navGroups: NavGroup[] = isAdminArea
        ? [
              {
                  label: 'Admin',
                  items: [
                      {
                          title: 'Dashboard',
                          href: '/admin',
                          icon: LayoutDashboard,
                      },
                      {
                          title: 'Users',
                          href: '/admin/users',
                          icon: UsersIcon,
                      },
                      {
                          title: 'Memberships',
                          href: '/admin/memberships',
                          icon: Shield,
                      },
                      {
                          title: 'Feedbacks',
                          href: '/admin/feedbacks',
                          icon: MessageSquare,
                      },
                  ],
              },
          ]
        : ledgerId
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
                            isPremium: true,
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
                            isPremium: true,
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
                            isPremium: true,
                        },
                        {
                            title: 'Payees',
                            href: payeesIndex.url(ledgerId),
                            icon: UsersIcon,
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
                {isAdminArea ? (
                    <div className="flex items-center gap-2 px-2 py-1.5">
                        <div className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <Shield className="size-4" />
                        </div>
                        <span className="truncate text-sm font-semibold">
                            Admin Console
                        </span>
                    </div>
                ) : (
                    <LedgerSwitcher />
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                {isAdminArea ? (
                    <>
                        <SidebarSeparator />
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    tooltip={{ children: 'Back to App' }}
                                >
                                    <Link href="/dashboard">
                                        <ArrowLeft />
                                        <span>Back to App</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </>
                ) : (
                    <>
                        <SidebarSeparator />
                        <FeedbackDialog />
                        {settingsHref && (
                            <SidebarMenu>
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            settingsHref,
                                        )}
                                        tooltip={{
                                            children: 'Workspace Settings',
                                        }}
                                    >
                                        <Link href={settingsHref} prefetch>
                                            <Settings />
                                            <span>Workspace Settings</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        )}
                    </>
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
