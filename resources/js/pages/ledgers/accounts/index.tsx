import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Eye, EyeOff } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useApiQuery } from '@/hooks/use-api-query';
import AppLayout from '@/layouts/app-layout';
import { api } from '@/lib/api-client';
import { formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    show as accountShow,
    index as accountsIndex,
    create,
} from '@/routes/ledgers/accounts';
import type { Account, AccountType, BreadcrumbItem } from '@/types';

type AccountGroup = {
    type: Pick<AccountType, 'id' | 'name' | 'color' | 'is_credit'>;
    accounts: Account[];
    total_balance?: string;
};

type NetWorthData = {
    assets: number;
    liabilities: number;
    net: number;
    trend: Array<{ month: string; net: number }>;
};

export default function AccountsIndex() {
    const { currentLedger: ledger } = usePage().props;

    const base = `/api/v1/ledgers/${ledger!.id}`;

    const [showHidden, setShowHidden] = useState(false);

    const {
        data: groupsResponse,
        loading: groupsLoading,
        refetch: refetchGroups,
    } = useApiQuery<{ data: AccountGroup[] }>(`${base}/accounts`, {
        params: {
            grouped: true,
            show_hidden: showHidden,
            with_type_totals: true,
        },
        deps: [showHidden],
    });

    const { data: netWorthResponse, loading: netWorthLoading } = useApiQuery<{
        data: NetWorthData;
    }>(`${base}/net-worth`);

    const accountGroups = groupsResponse?.data ?? [];
    const netWorth = netWorthResponse?.data ?? null;

    // Flatten all accounts for empty-state check
    const allAccounts = accountGroups.flatMap((g) => g.accounts);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger!.name, href: ledgerDashboard.url(ledger!.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger!.id) },
    ];

    // Drag state
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);
    const [dragTypeId, setDragTypeId] = useState<number | null>(null);
    const isReorderingRef = useRef(false);

    function handleToggleVisibility(e: React.MouseEvent, account: Account) {
        e.preventDefault();
        e.stopPropagation();

        api.patch(`${base}/accounts/${account.id}/toggle-visibility`)
            .then(() => {
                toast.success(
                    account.is_hidden
                        ? `${account.name} is now visible`
                        : `${account.name} is now hidden`,
                );
                refetchGroups();
            })
            .catch(() => {
                toast.error('Failed to toggle visibility');
            });
    }

    // ── Account drag & drop ──────────────────────────────────────────────

    function handleDragStart(
        e: React.DragEvent,
        accountId: number,
        typeId: number,
    ) {
        e.dataTransfer.setData('text/plain', String(accountId));
        e.dataTransfer.effectAllowed = 'move';
        setDragTypeId(typeId);
    }

    function handleDragOver(e: React.DragEvent, accountId: number) {
        e.preventDefault();

        if (dragOverIdRef.current !== accountId) {
            dragOverIdRef.current = accountId;
            setDragOverId(accountId);
        }
    }

    function handleDragLeave() {
        dragOverIdRef.current = null;
        setDragOverId(null);
    }

    function handleDrop(
        e: React.DragEvent,
        targetId: number,
        typeAccounts: Account[],
    ) {
        e.preventDefault();
        dragOverIdRef.current = null;
        setDragOverId(null);
        setDragTypeId(null);

        if (isReorderingRef.current) {
            return;
        }

        const draggedId = Number(e.dataTransfer.getData('text/plain'));

        if (draggedId === targetId) {
            return;
        }

        const reordered = [...typeAccounts];
        const fromIdx = reordered.findIndex((a) => a.id === draggedId);
        const toIdx = reordered.findIndex((a) => a.id === targetId);

        if (fromIdx === -1 || toIdx === -1) {
            return;
        }

        const [moved] = reordered.splice(fromIdx, 1);
        reordered.splice(toIdx, 0, moved);

        const items = reordered.map((a, i) => ({
            id: a.id,
            position: i + 1,
        }));

        isReorderingRef.current = true;

        api.post(`${base}/accounts/reorder`, { body: { items } })
            .then(() => {
                isReorderingRef.current = false;
                refetchGroups();
            })
            .catch(() => {
                isReorderingRef.current = false;
                toast.error('Failed to reorder accounts.');
            });
    }

    function handleDragEnd() {
        dragOverIdRef.current = null;
        setDragOverId(null);
        setDragTypeId(null);
    }

    function getAccountBalance(account: Account): number {
        return parseFloat(
            String(account.current_balance ?? account.initial_balance ?? '0'),
        );
    }

    function renderAccountCard(
        account: Account,
        color: string,
        typeName: string,
        typeId: number,
        typeAccounts: Account[],
        isCredit: boolean,
    ) {
        const balance = getAccountBalance(account);
        const isNegative = balance < 0;
        const isDragOver = dragOverId === account.id;

        return (
            <div
                key={account.id}
                draggable
                onDragStart={(e) => handleDragStart(e, account.id, typeId)}
                onDragOver={(e) => {
                    // Only allow drop within the same type group
                    if (dragTypeId === typeId) {
                        handleDragOver(e, account.id);
                    }
                }}
                onDragLeave={handleDragLeave}
                onDragEnd={handleDragEnd}
                onDrop={(e) => {
                    if (dragTypeId === typeId) {
                        handleDrop(e, account.id, typeAccounts);
                    }
                }}
            >
                <Link
                    href={accountShow.url({
                        ledger: ledger!.id,
                        account: account.id,
                    })}
                    className="block"
                >
                    <Card
                        className={`group py-4 transition-colors hover:bg-muted/30 ${
                            account.is_hidden ? 'opacity-50' : ''
                        } ${isDragOver ? 'ring-2 ring-primary/40' : ''}`}
                    >
                        <CardContent>
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <span
                                        aria-hidden="true"
                                        className="cursor-grab text-muted-foreground opacity-100 select-none sm:opacity-0 sm:group-hover:opacity-100"
                                        onMouseDown={(e) => e.stopPropagation()}
                                    >
                                        &#8942;&#8942;
                                    </span>
                                    <span
                                        className="inline-block h-3 w-3 rounded-full"
                                        style={{
                                            backgroundColor:
                                                account.color ?? '#6B7280',
                                        }}
                                    />
                                    <p className="text-base font-semibold group-hover:underline">
                                        {account.name}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    {account.is_hidden && (
                                        <Badge variant="outline">Hidden</Badge>
                                    )}
                                    {account.statement_day !== null && (
                                        <Badge variant="secondary">
                                            Day {account.statement_day}
                                        </Badge>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        onClick={(e) =>
                                            handleToggleVisibility(e, account)
                                        }
                                        title={
                                            account.is_hidden
                                                ? 'Unhide account'
                                                : 'Hide account'
                                        }
                                    >
                                        {account.is_hidden ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>

                            <p
                                className={`mt-3 text-xl font-semibold tabular-nums ${
                                    isNegative
                                        ? 'text-red-600 dark:text-red-400'
                                        : isCredit && balance > 0
                                          ? 'text-green-600'
                                          : 'text-foreground'
                                }`}
                            >
                                {isNegative ? (
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <span className="inline-flex items-center gap-1.5">
                                                <AlertTriangle className="size-3.5 shrink-0" />
                                                {formatAmount(balance)}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>
                                                This account has a negative
                                                balance, which can happen if
                                                you've logged more expenses than
                                                the initial balance you set.
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                ) : (
                                    formatAmount(balance)
                                )}
                            </p>

                            <div className="mt-3 flex items-center gap-1.5">
                                <span
                                    className="size-2 rounded-full"
                                    style={{ backgroundColor: color }}
                                />
                                <span className="text-xs text-muted-foreground">
                                    {typeName}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger!.name} accounts`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        title="Accounts"
                        description="Track balances across all ledger accounts."
                    />

                    <div className="flex w-full items-center gap-4 md:w-auto">
                        <div className="flex items-center gap-2">
                            <Switch
                                id="show-hidden"
                                size="sm"
                                checked={showHidden}
                                onCheckedChange={setShowHidden}
                            />
                            <Label
                                htmlFor="show-hidden"
                                className="text-sm text-muted-foreground"
                            >
                                Show hidden
                            </Label>
                        </div>

                        <Button className="flex-1 md:flex-initial" asChild>
                            <Link href={create.url(ledger!.id)}>
                                New Account
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Net worth cards */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {netWorthLoading || !netWorth ? (
                        <>
                            {[1, 2, 3].map((i) => (
                                <Card key={i} className="py-4">
                                    <CardContent>
                                        <Skeleton className="mb-2 h-4 w-24" />
                                        <Skeleton className="h-8 w-32" />
                                    </CardContent>
                                </Card>
                            ))}
                        </>
                    ) : (
                        <>
                            <Card className="py-4">
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Total Assets
                                    </p>
                                    <p
                                        className={`mt-1 text-2xl font-semibold tabular-nums ${
                                            netWorth.assets > 0
                                                ? 'text-green-600'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {formatAmount(netWorth.assets)}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card className="py-4">
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Total Liabilities
                                    </p>
                                    <p
                                        className={`mt-1 text-2xl font-semibold tabular-nums ${
                                            netWorth.liabilities !== 0
                                                ? 'text-red-500'
                                                : 'text-foreground'
                                        }`}
                                    >
                                        {formatAmount(netWorth.liabilities)}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card className="py-4">
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Net Worth
                                    </p>
                                    <p
                                        className={`mt-1 text-2xl font-semibold tabular-nums ${
                                            netWorth.net >= 0
                                                ? 'text-green-600'
                                                : 'text-red-500'
                                        }`}
                                    >
                                        {formatAmount(netWorth.net)}
                                    </p>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>

                {/* Loading skeleton for account groups */}
                {groupsLoading && (
                    <div className="space-y-6">
                        {[1, 2].map((i) => (
                            <section key={i}>
                                <Skeleton className="mb-3 h-4 w-32" />
                                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                    {[1, 2, 3].map((j) => (
                                        <Card key={j} className="py-4">
                                            <CardContent>
                                                <Skeleton className="mb-3 h-5 w-40" />
                                                <Skeleton className="mb-3 h-7 w-28" />
                                                <Skeleton className="h-3 w-20" />
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}

                {!groupsLoading && allAccounts.length === 0 && (
                    <EmptyState
                        icon={<CreditCard className="size-6" />}
                        title="No accounts yet"
                        description="Add your bank accounts and wallets to start tracking."
                        action={{
                            label: 'New account',
                            href: create.url(ledger!.id),
                        }}
                    />
                )}

                {!groupsLoading &&
                    accountGroups.map((group) => {
                        const color = group.type.color ?? '#6b7280';
                        const typeAccounts = group.accounts;

                        return (
                            <section key={group.type.id}>
                                <div className="mb-3 flex items-center gap-2">
                                    <span
                                        className="size-3 rounded-full"
                                        style={{ backgroundColor: color }}
                                    />
                                    <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group.type.name}
                                    </h2>
                                </div>

                                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                    {typeAccounts.map((account) =>
                                        renderAccountCard(
                                            account,
                                            color,
                                            group.type.name,
                                            group.type.id,
                                            typeAccounts,
                                            group.type.is_credit,
                                        ),
                                    )}
                                </div>

                                {typeAccounts.length > 1 &&
                                    group.total_balance !== undefined && (
                                        <div className="mt-3 flex items-center justify-end gap-2 px-1">
                                            <span className="text-sm font-medium text-muted-foreground">
                                                Total {group.type.name}:
                                            </span>
                                            <span className="text-sm font-semibold tabular-nums">
                                                {formatAmount(
                                                    parseFloat(
                                                        group.total_balance,
                                                    ),
                                                )}
                                            </span>
                                        </div>
                                    )}
                            </section>
                        );
                    })}
            </div>
        </AppLayout>
    );
}
