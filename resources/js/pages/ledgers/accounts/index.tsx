import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CreditCard,
    Crown,
    ExternalLink,
    Pencil,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ColorPicker } from '@/components/color-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import {
    amountColor,
    formatAbsAmount,
    formatAmount,
    formatDate,
} from '@/lib/format';
import { mapInertiaErrorsArray } from '@/lib/utils';
import { premium } from '@/routes';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { Account, AccountType, BreadcrumbItem } from '@/types';

type AccountWithStatement = Account & {
    statement_balance?: number | null;
    statement_start?: string | null;
    statement_end?: string | null;
    current_spending?: number | null;
    payment_due_date?: string | null;
    outstanding?: number | null;
};

type AccountGroup = {
    type: Pick<AccountType, 'id' | 'name' | 'color' | 'is_credit'>;
    accounts: AccountWithStatement[];
    total_balance?: string;
};

type NetWorthData = {
    assets: number;
    liabilities: number;
    net: number;
    trend: Array<{ month: string; net: number }>;
};

type EditFormData = {
    account_type_id: string;
    name: string;
    color: string;
    initial_balance: string;
    statement_day: string;
    payment_due_day: string;
    include_in_totals: boolean;
};

function NetWorthCards() {
    const { netWorth } = usePage<{ netWorth: NetWorthData }>().props;

    return (
        <div className="grid grid-cols-3 gap-3">
            <Card className="py-3">
                <CardContent className="px-3">
                    <p className="text-xs text-muted-foreground">Assets</p>
                    <p
                        className={`mt-0.5 text-base font-semibold tabular-nums sm:text-lg md:text-xl ${amountColor(netWorth.assets)}`}
                    >
                        {formatAbsAmount(netWorth.assets)}
                    </p>
                </CardContent>
            </Card>

            <Card className="py-3">
                <CardContent className="px-3">
                    <p className="text-xs text-muted-foreground">Liabilities</p>
                    <p
                        className={`mt-0.5 text-base font-semibold tabular-nums sm:text-lg md:text-xl ${amountColor(-Math.abs(netWorth.liabilities))}`}
                    >
                        {formatAbsAmount(netWorth.liabilities)}
                    </p>
                </CardContent>
            </Card>

            <Card className="py-3">
                <CardContent className="px-3">
                    <p className="text-xs text-muted-foreground">Net Worth</p>
                    <p
                        className={`mt-0.5 text-base font-semibold tabular-nums sm:text-lg md:text-xl ${amountColor(netWorth.net)}`}
                    >
                        {formatAbsAmount(netWorth.net)}
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}

// ── Create Account Modal ────────────────────────────────────────────────

function CreateAccountModal({
    open,
    onOpenChange,
    ledgerId,
    accountTypes,
    onCreated,
}: {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly ledgerId: number;
    readonly accountTypes: AccountType[];
    readonly onCreated: () => void;
}) {
    const [formData, setFormData] = useState({
        account_type_id: '',
        name: '',
        color: '#6B7280',
        initial_balance: '0',
        statement_day: '',
        payment_due_day: '',
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [processing, setProcessing] = useState(false);
    const [defaultTypeSet, setDefaultTypeSet] = useState(false);

    // Set default account type when data loads
    if (
        !defaultTypeSet &&
        accountTypes.length > 0 &&
        !formData.account_type_id
    ) {
        setFormData((prev) => ({
            ...prev,
            account_type_id: String(accountTypes[0].id),
        }));
        setDefaultTypeSet(true);
    }

    const selectedType = accountTypes.find(
        (t) => String(t.id) === formData.account_type_id,
    );
    const isCreditCard = selectedType?.is_credit ?? false;

    function updateField(field: string, value: string) {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => {
            const updated = { ...prev };
            delete updated[field];

            return updated;
        });
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post(
            `/ledgers/${ledgerId}/accounts`,
            {
                account_type_id: formData.account_type_id,
                name: formData.name,
                color: formData.color,
                initial_balance: formData.initial_balance,
                include_in_totals: true,
                ...(isCreditCard && formData.statement_day
                    ? { statement_day: formData.statement_day }
                    : {}),
                ...(isCreditCard && formData.payment_due_day
                    ? { payment_due_day: formData.payment_due_day }
                    : {}),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    toast.success('Account created');
                    onOpenChange(false);
                    setFormData({
                        account_type_id: '',
                        name: '',
                        color: '#6B7280',
                        initial_balance: '0',
                        statement_day: '',
                        payment_due_day: '',
                    });
                    setDefaultTypeSet(false);
                    onCreated();
                },
                onError: (errors) => {
                    setProcessing(false);
                    setErrors(mapInertiaErrorsArray(errors));
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create account</DialogTitle>
                    <DialogDescription>
                        Add a new account to this ledger.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="create_account_type_id">
                            Account type
                        </Label>
                        <Select
                            value={formData.account_type_id}
                            onValueChange={(value) => {
                                const newType = accountTypes.find(
                                    (t) => String(t.id) === value,
                                );
                                setFormData((prev) => ({
                                    ...prev,
                                    account_type_id: value,
                                    statement_day: newType?.is_credit
                                        ? prev.statement_day
                                        : '',
                                    payment_due_day: newType?.is_credit
                                        ? prev.payment_due_day
                                        : '',
                                }));
                                setErrors((prev) => {
                                    const updated = { ...prev };
                                    delete updated.account_type_id;

                                    return updated;
                                });
                            }}
                        >
                            <SelectTrigger
                                id="create_account_type_id"
                                className="w-full"
                            >
                                <SelectValue placeholder="Select a type" />
                            </SelectTrigger>
                            <SelectContent>
                                {accountTypes.map((accountType) => (
                                    <SelectItem
                                        key={accountType.id}
                                        value={String(accountType.id)}
                                    >
                                        {accountType.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.account_type_id?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create_name">Account name</Label>
                        <Input
                            id="create_name"
                            name="name"
                            value={formData.name}
                            onChange={(e) =>
                                updateField('name', e.target.value)
                            }
                            required
                            placeholder="e.g., Maybank Savings, Cash Wallet"
                        />
                        <InputError message={errors.name?.[0]} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Color</Label>
                        <ColorPicker
                            value={formData.color}
                            onChange={(color) => updateField('color', color)}
                        />
                        <InputError message={errors.color?.[0]} />
                    </div>

                    {isCreditCard && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create_statement_day">
                                    Statement day{' '}
                                    <span className="text-muted-foreground">
                                        (1-31)
                                    </span>
                                </Label>
                                <Input
                                    id="create_statement_day"
                                    name="statement_day"
                                    type="number"
                                    inputMode="decimal"
                                    min="1"
                                    max="31"
                                    value={formData.statement_day}
                                    onChange={(e) =>
                                        updateField(
                                            'statement_day',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 15"
                                />
                                <InputError
                                    message={errors.statement_day?.[0]}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create_payment_due_day">
                                    Payment due day{' '}
                                    <span className="text-muted-foreground">
                                        (1-31)
                                    </span>
                                </Label>
                                <Input
                                    id="create_payment_due_day"
                                    name="payment_due_day"
                                    type="number"
                                    inputMode="decimal"
                                    min="1"
                                    max="31"
                                    value={formData.payment_due_day}
                                    onChange={(e) =>
                                        updateField(
                                            'payment_due_day',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 25"
                                />
                                <InputError
                                    message={errors.payment_due_day?.[0]}
                                />
                            </div>
                        </>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="create_initial_balance">
                            Initial balance
                        </Label>
                        <Input
                            id="create_initial_balance"
                            name="initial_balance"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            value={formData.initial_balance}
                            onChange={(e) =>
                                updateField('initial_balance', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.initial_balance?.[0]} />
                        <p className="text-xs text-muted-foreground">
                            Enter your current account balance. This is your
                            starting point for tracking.
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : 'Create account'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ── Edit Account Modal ──────────────────────────────────────────────────

function EditAccountModal({
    account,
    open,
    onOpenChange,
    ledgerId,
    accountTypes,
    onSaved,
}: {
    readonly account: AccountWithStatement;
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly ledgerId: number;
    readonly accountTypes: AccountType[];
    readonly onSaved: () => void;
}) {
    const [formData, setFormData] = useState<EditFormData>({
        account_type_id: String(account.account_type_id),
        name: account.name,
        color: account.color ?? '#6B7280',
        initial_balance: account.initial_balance,
        statement_day:
            account.statement_day != null ? String(account.statement_day) : '',
        payment_due_day:
            account.payment_due_day != null
                ? String(account.payment_due_day)
                : '',
        include_in_totals: account.include_in_totals,
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [processing, setProcessing] = useState(false);

    // Balance adjustment state
    const currentBalance = parseFloat(String(account.current_balance ?? '0'));
    const [newBalance, setNewBalance] = useState(String(currentBalance));
    const [adjusting, setAdjusting] = useState(false);

    // Delete confirmation
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const selectedType = accountTypes.find(
        (t) => String(t.id) === formData.account_type_id,
    );
    const effectiveIsCredit = selectedType?.is_credit ?? false;

    function updateField(field: string, value: string | boolean) {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => {
            const updated = { ...prev };
            delete updated[field];

            return updated;
        });
    }

    function handleAccountTypeChange(value: string) {
        updateField('account_type_id', value);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.put(
            `/ledgers/${ledgerId}/accounts/${account.id}`,
            {
                account_type_id: formData.account_type_id,
                name: formData.name,
                color: formData.color,
                initial_balance: formData.initial_balance,
                include_in_totals: formData.include_in_totals,
                statement_day: effectiveIsCredit
                    ? formData.statement_day || null
                    : null,
                payment_due_day: effectiveIsCredit
                    ? formData.payment_due_day || null
                    : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    toast.success('Account updated');
                    onOpenChange(false);
                    onSaved();
                },
                onError: (errors) => {
                    setProcessing(false);
                    setErrors(mapInertiaErrorsArray(errors));
                },
            },
        );
    }

    function handleAdjustBalance() {
        setAdjusting(true);
        const diff = parseFloat(newBalance) - currentBalance;

        router.post(
            `/ledgers/${ledgerId}/accounts/${account.id}/adjust-balance`,
            { amount: diff, description: 'Balance adjustment' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAdjusting(false);
                    toast.success('Balance adjusted');
                    onOpenChange(false);
                    onSaved();
                },
                onError: () => {
                    setAdjusting(false);
                    toast.error('Failed to adjust balance');
                },
            },
        );
    }

    function handleDelete() {
        setDeleting(true);
        router.delete(`/ledgers/${ledgerId}/accounts/${account.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Account deleted');
                setShowDeleteConfirm(false);
                onOpenChange(false);
                onSaved();
            },
            onError: () => {
                setDeleting(false);
                toast.error('Failed to delete account');
            },
        });
    }

    const balanceDiff = parseFloat(newBalance) - currentBalance;
    const hasBalanceDiff = !isNaN(balanceDiff) && Math.abs(balanceDiff) >= 0.01;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit account</DialogTitle>
                        <DialogDescription>
                            Update the account details.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit} className="space-y-4 py-2">
                        {/* Account type */}
                        <div className="grid gap-2">
                            <Label htmlFor="edit_account_type_id">
                                Account type
                            </Label>
                            <Select
                                value={formData.account_type_id}
                                onValueChange={handleAccountTypeChange}
                            >
                                <SelectTrigger
                                    id="edit_account_type_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {accountTypes.map((type) => (
                                        <SelectItem
                                            key={type.id}
                                            value={String(type.id)}
                                        >
                                            {type.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.account_type_id?.[0]} />
                        </div>

                        {/* Name */}
                        <div className="grid gap-2">
                            <Label htmlFor="edit_name">Account name</Label>
                            <Input
                                id="edit_name"
                                name="name"
                                value={formData.name}
                                onChange={(e) =>
                                    updateField('name', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.name?.[0]} />
                        </div>

                        {/* Color */}
                        <div className="grid gap-2">
                            <Label>Color</Label>
                            <ColorPicker
                                value={formData.color}
                                onChange={(color) =>
                                    updateField('color', color)
                                }
                            />
                            <InputError message={errors.color?.[0]} />
                        </div>

                        {/* Initial balance */}
                        <div className="grid gap-2">
                            <Label htmlFor="edit_initial_balance">
                                Initial balance
                            </Label>
                            <Input
                                id="edit_initial_balance"
                                name="initial_balance"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                value={formData.initial_balance}
                                onChange={(e) =>
                                    updateField(
                                        'initial_balance',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={errors.initial_balance?.[0]} />
                        </div>

                        {/* Statement day (credit only) */}
                        {effectiveIsCredit && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit_statement_day">
                                        Statement day{' '}
                                        <span className="text-muted-foreground">
                                            (1-31)
                                        </span>
                                    </Label>
                                    <Input
                                        id="edit_statement_day"
                                        name="statement_day"
                                        type="number"
                                        inputMode="decimal"
                                        min="1"
                                        max="31"
                                        value={formData.statement_day}
                                        onChange={(e) =>
                                            updateField(
                                                'statement_day',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. 15"
                                    />
                                    <InputError
                                        message={errors.statement_day?.[0]}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit_payment_due_day">
                                        Payment due day{' '}
                                        <span className="text-muted-foreground">
                                            (1-31)
                                        </span>
                                    </Label>
                                    <Input
                                        id="edit_payment_due_day"
                                        name="payment_due_day"
                                        type="number"
                                        inputMode="decimal"
                                        min="1"
                                        max="31"
                                        value={formData.payment_due_day}
                                        onChange={(e) =>
                                            updateField(
                                                'payment_due_day',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. 25"
                                    />
                                    <InputError
                                        message={errors.payment_due_day?.[0]}
                                    />
                                </div>
                            </>
                        )}

                        {/* Include in totals */}
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="edit_include_in_totals"
                                checked={formData.include_in_totals}
                                onCheckedChange={(checked) =>
                                    updateField(
                                        'include_in_totals',
                                        checked === true,
                                    )
                                }
                            />
                            <Label htmlFor="edit_include_in_totals">
                                Include in totals
                            </Label>
                        </div>

                        {/* Adjust balance section */}
                        <div className="space-y-3 border-t pt-4">
                            <div>
                                <h3 className="text-sm font-medium">
                                    Adjust balance
                                </h3>
                                <p className="text-xs text-muted-foreground">
                                    Set the current balance. An adjustment
                                    transaction will be created for the
                                    difference.
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="edit_new_balance">
                                    Current balance:{' '}
                                    <span className="font-mono">
                                        {formatAmount(currentBalance)}
                                    </span>
                                </Label>
                                <Input
                                    id="edit_new_balance"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    value={newBalance}
                                    onChange={(e) =>
                                        setNewBalance(e.target.value)
                                    }
                                />
                            </div>

                            {hasBalanceDiff && (
                                <p className="text-xs text-muted-foreground">
                                    This will create a{' '}
                                    <span
                                        className={
                                            balanceDiff > 0
                                                ? 'font-medium text-green-600'
                                                : 'font-medium text-red-600'
                                        }
                                    >
                                        {balanceDiff > 0 ? '+' : ''}
                                        {formatAmount(balanceDiff)}
                                    </span>{' '}
                                    {balanceDiff > 0 ? 'income' : 'expense'}{' '}
                                    adjustment transaction.
                                </p>
                            )}

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    adjusting ||
                                    isNaN(parseFloat(newBalance)) ||
                                    Math.abs(
                                        parseFloat(newBalance) - currentBalance,
                                    ) < 0.01
                                }
                                onClick={handleAdjustBalance}
                            >
                                {adjusting ? 'Adjusting...' : 'Adjust balance'}
                            </Button>
                        </div>

                        <DialogFooter className="gap-2 border-t pt-4">
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowDeleteConfirm(true)}
                            >
                                Delete account
                            </Button>
                            <div className="flex-1" />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Nested delete confirmation */}
            <Dialog
                open={showDeleteConfirm}
                onOpenChange={setShowDeleteConfirm}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete account</DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <strong>{account.name}</strong> and all of its
                            transactions. This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            Delete account
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

// ── Main Component ──────────────────────────────────────────────────────

export default function AccountsIndex() {
    const {
        auth,
        currentLedger: ledger,
        accounts: accountGroups,
        accountTypes,
    } = usePage<{
        accounts: AccountGroup[];
        accountTypes: AccountType[];
    }>().props;

    const isPremiumUser = auth.user?.membership.is_premium ?? false;

    function refetchData() {
        router.reload({ only: ['accounts', 'netWorth'] });
    }

    const allAccounts = accountGroups.flatMap((g) => g.accounts);
    const canCreateAccount = isPremiumUser || allAccounts.length < 7;

    const premiumBadge = !canCreateAccount && (
        <Badge variant="secondary" className="gap-1 text-[10px] leading-none">
            <Crown className="size-2.5" />
            Premium
        </Badge>
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger!.name, href: ledgerDashboard.url(ledger!.id) },
        { title: 'Accounts', href: accountsIndex.url(ledger!.id) },
    ];

    // Create modal state
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Edit modal state
    const [editingAccount, setEditingAccount] =
        useState<AccountWithStatement | null>(null);

    // Delete confirmation state
    const [deletingAccount, setDeletingAccount] =
        useState<AccountWithStatement | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    // Drag state
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);
    const [dragTypeId, setDragTypeId] = useState<number | null>(null);
    const isReorderingRef = useRef(false);

    function handleDeleteConfirm() {
        if (!deletingAccount) {
            return;
        }

        setDeleteProcessing(true);

        router.delete(`/ledgers/${ledger!.id}/accounts/${deletingAccount.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Account deleted');
                setDeletingAccount(null);
                setDeleteProcessing(false);
            },
            onError: () => {
                setDeleteProcessing(false);
                toast.error('Failed to delete account');
            },
        });
    }

    function getTransactionsUrl(accountId: number): string {
        const txBase = transactionsIndex.url(ledger!.id);
        const params = new URLSearchParams();
        params.append('account_ids[]', String(accountId));

        return `${txBase}?${params.toString()}`;
    }

    // ── Drag & drop handlers ────────────────────────────────────────────

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
            `/ledgers/${ledger!.id}/accounts/reorder`,
            { items },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isReorderingRef.current = false;
                },
                onError: () => {
                    isReorderingRef.current = false;
                    toast.error('Failed to reorder accounts.');
                    refetchData();
                },
            },
        );
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
        account: AccountWithStatement,
        typeId: number,
        isCredit: boolean,
        typeAccounts: AccountWithStatement[],
    ) {
        const balance = getAccountBalance(account);
        const isDragOver = dragOverId === account.id;
        const hasStmt = account.statement_start != null;

        return (
            <div
                key={account.id}
                draggable
                onDragStart={(e) => handleDragStart(e, account.id, typeId)}
                onDragOver={(e) => {
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
                <Card
                    className={`group py-4 transition-colors ${
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
                                <p className="text-base font-semibold">
                                    {account.name}
                                </p>
                            </div>
                            <div className="flex items-center gap-1">
                                {account.statement_day !== null && (
                                    <Badge variant="secondary">
                                        Day {account.statement_day}
                                    </Badge>
                                )}
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            onClick={() =>
                                                setEditingAccount(account)
                                            }
                                        >
                                            <Pencil className="size-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Edit</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            asChild
                                        >
                                            <Link
                                                href={getTransactionsUrl(
                                                    account.id,
                                                )}
                                            >
                                                <ExternalLink className="size-3.5" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Show transactions
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                                            onClick={() =>
                                                setDeletingAccount(account)
                                            }
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>Delete</TooltipContent>
                                </Tooltip>
                            </div>
                        </div>

                        <p
                            className={`mt-3 text-xl font-semibold tabular-nums ${amountColor(balance)}`}
                        >
                            {balance < 0 ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="inline-flex items-center gap-1.5">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            {formatAbsAmount(balance)}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>
                                            This account has a negative balance,
                                            which can happen if you've logged
                                            more expenses than the initial
                                            balance you set.
                                        </p>
                                    </TooltipContent>
                                </Tooltip>
                            ) : (
                                formatAbsAmount(balance)
                            )}
                        </p>

                        {/* Credit card statement group */}
                        {isCredit && hasStmt && (
                            <div className="mt-3 rounded-lg border border-border/60 bg-muted/20 px-3 py-2">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">
                                        Statement
                                    </span>
                                    <span
                                        className={`font-medium tabular-nums ${amountColor(account.statement_balance ?? 0)}`}
                                    >
                                        {formatAbsAmount(
                                            account.statement_balance ?? 0,
                                        )}
                                    </span>
                                </div>
                                <div className="mt-1 flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">
                                        {formatDate(account.statement_start!)}{' '}
                                        &ndash;{' '}
                                        {formatDate(account.statement_end!)}
                                    </span>
                                    {account.payment_due_date && (
                                        <span className="text-muted-foreground">
                                            Due{' '}
                                            {formatDate(
                                                account.payment_due_date,
                                            )}
                                        </span>
                                    )}
                                </div>
                                {account.current_spending != null && (
                                    <div className="mt-1 flex items-center justify-between border-t border-border/40 pt-1 text-xs">
                                        <span className="text-muted-foreground">
                                            Current spending
                                        </span>
                                        <span
                                            className={`font-medium tabular-nums ${amountColor(account.current_spending)}`}
                                        >
                                            {formatAbsAmount(
                                                account.current_spending,
                                            )}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger!.name} accounts`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Accounts"
                            description="Track balances across all ledger accounts."
                        />
                        <div className="hidden md:block">
                            {canCreateAccount ? (
                                <Button
                                    onClick={() => setShowCreateModal(true)}
                                >
                                    New Account
                                </Button>
                            ) : (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={premium.url()}
                                        className="gap-2"
                                    >
                                        New Account
                                        {premiumBadge}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                    <div className="md:hidden">
                        {canCreateAccount ? (
                            <Button
                                className="w-full"
                                onClick={() => setShowCreateModal(true)}
                            >
                                New Account
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <Link href={premium.url()} className="gap-2">
                                    New Account
                                    {premiumBadge}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Net worth cards - always single row */}
                <Deferred
                    data="netWorth"
                    fallback={
                        <div className="grid grid-cols-3 gap-3">
                            {[1, 2, 3].map((i) => (
                                <Card key={i} className="py-3">
                                    <CardContent className="px-3">
                                        <Skeleton className="mb-1 h-3 w-16" />
                                        <Skeleton className="h-6 w-20" />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    }
                >
                    <NetWorthCards />
                </Deferred>

                {allAccounts.length === 0 && (
                    <EmptyState
                        icon={<CreditCard className="size-6" />}
                        title="No accounts yet"
                        description="Add your bank accounts and wallets to start tracking."
                        action={{
                            label: 'New account',
                            onClick: () => setShowCreateModal(true),
                        }}
                    />
                )}

                {accountGroups.map((group) => {
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
                                        group.type.id,
                                        group.type.is_credit,
                                        typeAccounts,
                                    ),
                                )}
                            </div>

                            {typeAccounts.length > 1 &&
                                group.total_balance !== undefined && (
                                    <div className="mt-3 flex items-center justify-end gap-2 px-1">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            Total {group.type.name}:
                                        </span>
                                        <span
                                            className={`text-sm font-semibold tabular-nums ${amountColor(parseFloat(group.total_balance))}`}
                                        >
                                            {formatAbsAmount(
                                                parseFloat(group.total_balance),
                                            )}
                                        </span>
                                    </div>
                                )}
                        </section>
                    );
                })}
            </div>

            {/* Create modal */}
            <CreateAccountModal
                open={showCreateModal}
                onOpenChange={setShowCreateModal}
                ledgerId={ledger!.id}
                accountTypes={accountTypes}
                onCreated={refetchData}
            />

            {/* Edit modal */}
            {editingAccount && (
                <EditAccountModal
                    account={editingAccount}
                    open={true}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingAccount(null);
                        }
                    }}
                    ledgerId={ledger!.id}
                    accountTypes={accountTypes}
                    onSaved={refetchData}
                />
            )}

            {/* Delete confirmation dialog */}
            <Dialog
                open={deletingAccount !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingAccount(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete account</DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <strong>{deletingAccount?.name}</strong> and all of
                            its transactions. This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeletingAccount(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDeleteConfirm}
                            disabled={deleteProcessing}
                        >
                            Delete account
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
