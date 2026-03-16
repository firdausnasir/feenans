import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CreditCard, Eye, EyeOff } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { toggleVisibility } from '@/actions/App/Http/Controllers/Ledger/AccountController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { formatAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create,
    index as accountsIndex,
    reorder as reorderRoute,
    show as accountShow,
} from '@/routes/ledgers/accounts';
import type { Account, AccountType, BreadcrumbItem, Ledger } from '@/types';

export default function AccountsIndex({
    ledger,
    accounts,
    accountTypes,
    netWorth,
    showHidden,
}: {
    ledger: Ledger;
    accounts: Account[];
    accountTypes: AccountType[];
    netWorth: { assets: number; liabilities: number; net: number };
    showHidden: boolean;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger.id) },
    ];

    // Drag state
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);
    const [dragTypeId, setDragTypeId] = useState<number | null>(null);
    const isReorderingRef = useRef(false);

    // Group accounts by account type, preserving accountTypes order
    const grouped = accountTypes
        .map((type) => ({
            type,
            accounts: accounts.filter((a) => a.account_type_id === type.id),
        }))
        .filter((group) => group.accounts.length > 0);

    // Accounts with no matching type (safety net)
    const ungrouped = accounts.filter(
        (a) => !accountTypes.find((t) => t.id === a.account_type_id),
    );

    function handleToggleShowHidden(checked: boolean) {
        router.get(
            accountsIndex.url(ledger.id),
            checked ? { show_hidden: 1 } : {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleToggleVisibility(e: React.MouseEvent, account: Account) {
        e.preventDefault();
        e.stopPropagation();

        router.patch(
            toggleVisibility.url({
                ledger: ledger.id,
                account: account.id,
            }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(
                        account.is_hidden
                            ? `${account.name} is now visible`
                            : `${account.name} is now hidden`,
                    );
                },
            },
        );
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

        router.post(
            reorderRoute.url(ledger.id),
            { items },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isReorderingRef.current = false;
                },
                onError: () => {
                    isReorderingRef.current = false;
                    toast.error('Failed to reorder accounts.');
                },
            },
        );
    }

    function handleDragEnd() {
        dragOverIdRef.current = null;
        setDragOverId(null);
        setDragTypeId(null);
    }

    function renderAccountCard(
        account: Account,
        color: string,
        typeName: string,
        typeId: number,
        typeAccounts: Account[],
    ) {
        const balance = parseFloat(
            String(account.current_balance ?? account.initial_balance ?? '0'),
        );
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
                        ledger: ledger.id,
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
            <Head title={`${ledger.name} accounts`} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Accounts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track balances across all ledger accounts.
                        </p>
                    </div>

                    <div className="flex w-full items-center gap-4 md:w-auto">
                        <div className="flex items-center gap-2">
                            <Switch
                                id="show-hidden"
                                size="sm"
                                checked={showHidden}
                                onCheckedChange={handleToggleShowHidden}
                            />
                            <Label
                                htmlFor="show-hidden"
                                className="text-sm text-muted-foreground"
                            >
                                Show hidden
                            </Label>
                        </div>

                        <Button className="flex-1 md:flex-initial" asChild>
                            <Link href={create.url(ledger.id)}>
                                New Account
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
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
                </div>

                {accounts.length === 0 && (
                    <EmptyState
                        icon={<CreditCard className="size-6" />}
                        title="No accounts yet"
                        description="Add your bank accounts and wallets to start tracking."
                        action={{
                            label: 'New account',
                            href: create.url(ledger.id),
                        }}
                    />
                )}

                {grouped.map(({ type, accounts: typeAccounts }) => {
                    const color = type.color ?? '#6b7280';

                    return (
                        <section key={type.id}>
                            <div className="mb-3 flex items-center gap-2">
                                <span
                                    className="size-3 rounded-full"
                                    style={{ backgroundColor: color }}
                                />
                                <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    {type.name}
                                </h2>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {typeAccounts.map((account) =>
                                    renderAccountCard(
                                        account,
                                        color,
                                        type.name,
                                        type.id,
                                        typeAccounts,
                                    ),
                                )}
                            </div>
                        </section>
                    );
                })}

                {ungrouped.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Other
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {ungrouped.map((account) =>
                                renderAccountCard(
                                    account,
                                    '#6b7280',
                                    'Other',
                                    0,
                                    ungrouped,
                                ),
                            )}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
