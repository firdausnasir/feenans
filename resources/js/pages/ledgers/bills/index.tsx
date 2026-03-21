import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    ExternalLink,
    MoreHorizontal,
    Pencil,
    Receipt,
    Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    store as storeRoute,
    update as updateRoute,
} from '@/actions/App/Http/Controllers/Ledger/BillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PayBillDialog } from '@/components/pay-bill-dialog';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import {
    buildCategoryOptions,
    describeRecurrence,
    formatAbsAmount,
    formatDate,
    parseDate,
} from '@/lib/format';
import { cn, mapInertiaErrors } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as billsIndex } from '@/routes/ledgers/bills';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { Account, Bill, BreadcrumbItem, Category, Payee } from '@/types';

type BillsPageProps = {
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    bills?: Bill[];
};

const COLUMN_COUNT = 7;

const ACTION_LABELS: Record<string, { pay: string; paid: string }> = {
    expense: { pay: 'Record Payment', paid: 'paid' },
    income: { pay: 'Record Income', paid: 'received' },
    transfer: { pay: 'Record Transfer', paid: 'recorded' },
};

function recurrenceDescription(
    type: Bill['recurrence_type'],
    interval: number,
): string {
    if (type === 'custom') {
        return 'Custom';
    }

    const labels: Record<Bill['recurrence_type'], [string, string]> = {
        daily: ['Daily', 'Every {n} days'],
        weekly: ['Weekly', 'Every {n} weeks'],
        monthly: ['Monthly', 'Every {n} months'],
        yearly: ['Yearly', 'Every {n} years'],
        custom: ['Custom', 'Custom'],
    };

    const [singular, plural] = labels[type];

    if (interval === 1) {
        return singular;
    }

    return plural.replace('{n}', String(interval));
}

function getDueStatus(
    nextDueDate: string,
): 'overdue' | 'due-soon' | 'upcoming' {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = parseDate(nextDueDate);
    const diffDays = Math.floor(
        (due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (diffDays < 0) {
        return 'overdue';
    }

    if (diffDays <= 3) {
        return 'due-soon';
    }

    return 'upcoming';
}

type DueStatus = ReturnType<typeof getDueStatus>;

const cardBorderStyles: Record<DueStatus, string> = {
    overdue: 'border-red-500 dark:border-red-400',
    'due-soon': 'border-amber-500 dark:border-amber-400',
    upcoming: 'border-border',
};

const dueStatusStyles: Record<DueStatus, string> = {
    overdue: 'border-l-2 border-l-red-500',
    'due-soon': 'border-l-2 border-l-amber-500',
    upcoming: '',
};

const dueDateStyles: Record<DueStatus, string> = {
    overdue: 'text-red-500 font-medium',
    'due-soon': 'text-amber-500',
    upcoming: 'text-muted-foreground',
};

function amountColor(bill: Bill): string {
    return bill.transaction_type === 'expense'
        ? 'text-red-500 dark:text-red-400'
        : 'text-foreground';
}

type RecurrenceType = 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';
type EndType = 'never' | 'on_date' | 'after_occurrences';

type FormData = {
    name: string;
    transaction_type: string;
    amount: string;
    account_id: string;
    to_account_id: string;
    category_id: string;
    payee_id: string;
    recurrence_type: RecurrenceType;
    recurrence_interval: string;
    recurrence_day: string;
    next_due_date: string;
    auto_create: boolean;
    end_type: EndType;
    end_date: string;
    end_after_occurrences: string;
    is_active: boolean;
};

type FormErrors = Partial<Record<keyof FormData, string>>;

function BillFormModal({
    bill,
    open,
    onOpenChange,
    ledgerId,
    accounts,
    categories,
    payees,
}: {
    bill: Bill | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ledgerId: number;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
}) {
    const isEdit = bill !== null;

    const [data, setFormData] = useState<FormData>(() =>
        buildInitialData(bill, accounts),
    );
    const [errors, setErrors] = useState<FormErrors>({});
    const [processing, setProcessing] = useState(false);

    // Reset form when bill changes
    useEffect(() => {
        setFormData(buildInitialData(bill, accounts));
        setErrors({});
        setProcessing(false);
    }, [bill, accounts]);

    function setData<K extends keyof FormData>(key: K, value: FormData[K]) {
        setFormData((prev) => ({ ...prev, [key]: value }));
        clearErrors(key);
    }

    function clearErrors(key: keyof FormData) {
        setErrors((prev) => {
            if (!(key in prev)) {
                return prev;
            }

            const next = { ...prev };
            delete next[key];

            return next;
        });
    }

    const categoryOptions = buildCategoryOptions(categories);

    function submit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formPayload = {
            name: data.name,
            transaction_type: data.transaction_type,
            amount: data.amount,
            account_id: data.account_id,
            to_account_id: data.to_account_id || null,
            category_id:
                data.transaction_type === 'transfer'
                    ? null
                    : data.category_id || null,
            payee_id:
                data.transaction_type === 'transfer'
                    ? null
                    : data.payee_id || null,
            recurrence_type: data.recurrence_type,
            recurrence_interval: data.recurrence_interval,
            recurrence_day: data.recurrence_day || null,
            next_due_date: data.next_due_date,
            auto_create: data.auto_create,
            end_type: data.end_type,
            end_date: data.end_date || null,
            end_after_occurrences: data.end_after_occurrences || null,
            ...(isEdit ? { is_active: data.is_active } : {}),
        };

        const url = isEdit
            ? updateRoute.url({ ledger: ledgerId, bill: bill.id })
            : storeRoute.url(ledgerId);

        const method = isEdit ? 'put' : 'post';

        router[method](url, formPayload, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
            onError: (errs) => {
                setErrors(mapInertiaErrors<FormErrors>(errs));
                setProcessing(false);
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    }

    return (
        <Dialog open={open} modal onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit
                            ? 'Edit Recurring Transaction'
                            : 'New Recurring Transaction'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the recurring transaction details.'
                            : 'Create a new recurring transaction.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-5">
                    {/* Active toggle (edit only) */}
                    {isEdit && (
                        <div className="flex items-center justify-between">
                            <Label htmlFor="is_active">Active</Label>
                            <Switch
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked)
                                }
                            />
                        </div>
                    )}

                    {/* Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="bill_name">Name</Label>
                        <Input
                            id="bill_name"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    {/* Transaction type */}
                    <div className="grid gap-2">
                        <Label>Type</Label>
                        <Select
                            value={data.transaction_type}
                            onValueChange={(value) => {
                                setData('transaction_type', value);

                                if (value === 'transfer') {
                                    setData('category_id', '');
                                    setData('payee_id', '');

                                    if (
                                        !data.to_account_id &&
                                        accounts.length > 1
                                    ) {
                                        const fallbackAccount = accounts.find(
                                            (account) =>
                                                String(account.id) !==
                                                data.account_id,
                                        );

                                        if (fallbackAccount) {
                                            setData(
                                                'to_account_id',
                                                String(fallbackAccount.id),
                                            );
                                        }
                                    }
                                }
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="expense">Expense</SelectItem>
                                <SelectItem value="income">Income</SelectItem>
                                <SelectItem value="transfer">
                                    Transfer
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.transaction_type} />
                    </div>

                    {/* Amount */}
                    <div className="grid gap-2">
                        <Label htmlFor="bill_amount">Amount (RM)</Label>
                        <Input
                            id="bill_amount"
                            name="amount"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    {/* Account */}
                    <div className="grid gap-2">
                        <Label>Account</Label>
                        <SearchableSelect
                            options={accounts.map((account) => ({
                                value: String(account.id),
                                label: account.name,
                                color: account.color,
                            }))}
                            value={data.account_id || null}
                            onValueChange={(value) =>
                                setData('account_id', value ?? '')
                            }
                            placeholder="Select account"
                            searchPlaceholder="Search accounts..."
                        />
                        <InputError message={errors.account_id} />
                    </div>

                    {data.transaction_type === 'transfer' && (
                        <div className="grid gap-2">
                            <Label>Destination account</Label>
                            <SearchableSelect
                                options={accounts
                                    .filter(
                                        (account) =>
                                            String(account.id) !==
                                            data.account_id,
                                    )
                                    .map((account) => ({
                                        value: String(account.id),
                                        label: account.name,
                                        color: account.color,
                                    }))}
                                value={data.to_account_id || null}
                                onValueChange={(value) =>
                                    setData('to_account_id', value ?? '')
                                }
                                placeholder="Select destination account"
                                searchPlaceholder="Search accounts..."
                            />
                            <InputError message={errors.to_account_id} />
                        </div>
                    )}

                    {data.transaction_type !== 'transfer' && (
                        <>
                            {/* Category */}
                            <div className="grid gap-2">
                                <Label>
                                    Category{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <SearchableSelect
                                    options={categoryOptions}
                                    value={data.category_id || null}
                                    onValueChange={(value) =>
                                        setData('category_id', value ?? '')
                                    }
                                    placeholder="No category"
                                    searchPlaceholder="Search categories..."
                                    allOption="No category"
                                />
                                <InputError message={errors.category_id} />
                            </div>

                            {/* Payee */}
                            <div className="grid gap-2">
                                <Label>
                                    Payee{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <SearchableSelect
                                    options={payees.map((payee) => ({
                                        value: String(payee.id),
                                        label: payee.name,
                                    }))}
                                    value={data.payee_id || null}
                                    onValueChange={(value) =>
                                        setData('payee_id', value ?? '')
                                    }
                                    placeholder="No payee"
                                    searchPlaceholder="Search payees..."
                                    allOption="No payee"
                                />
                                <InputError message={errors.payee_id} />
                            </div>
                        </>
                    )}

                    {/* Recurrence */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Recurrence type</Label>
                            <Select
                                value={data.recurrence_type}
                                onValueChange={(val) =>
                                    setData(
                                        'recurrence_type',
                                        val as RecurrenceType,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select recurrence" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">
                                        Weekly
                                    </SelectItem>
                                    <SelectItem value="monthly">
                                        Monthly
                                    </SelectItem>
                                    <SelectItem value="yearly">
                                        Yearly
                                    </SelectItem>
                                    <SelectItem value="custom">
                                        Custom
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.recurrence_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bill_recurrence_interval">
                                Every (interval)
                            </Label>
                            <Input
                                id="bill_recurrence_interval"
                                name="recurrence_interval"
                                type="number"
                                inputMode="decimal"
                                min="1"
                                value={data.recurrence_interval}
                                onChange={(e) =>
                                    setData(
                                        'recurrence_interval',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError message={errors.recurrence_interval} />
                        </div>
                    </div>

                    {/* Recurrence day */}
                    {(data.recurrence_type === 'monthly' ||
                        data.recurrence_type === 'yearly' ||
                        data.recurrence_type === 'custom') && (
                        <div className="grid gap-2">
                            <Label>
                                Day of month{' '}
                                <span className="text-muted-foreground">
                                    (required)
                                </span>
                            </Label>
                            <div className="grid grid-cols-7 gap-1">
                                {Array.from(
                                    { length: 31 },
                                    (_, i) => i + 1,
                                ).map((day) => (
                                    <button
                                        key={day}
                                        type="button"
                                        className={`flex h-9 w-full items-center justify-center rounded-md text-sm transition-colors ${
                                            data.recurrence_day === String(day)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'hover:bg-accent hover:text-accent-foreground'
                                        }`}
                                        onClick={() =>
                                            setData(
                                                'recurrence_day',
                                                String(day),
                                            )
                                        }
                                    >
                                        {day}
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.recurrence_day} />
                        </div>
                    )}

                    {/* Recurrence preview */}
                    <p className="text-sm text-muted-foreground italic">
                        {describeRecurrence(
                            data.recurrence_type,
                            data.recurrence_interval,
                            data.recurrence_day,
                        )}
                    </p>

                    {/* Next due date */}
                    <div className="grid gap-2">
                        <Label htmlFor="bill_next_due_date">
                            Next due date
                        </Label>
                        <DatePicker
                            id="bill_next_due_date"
                            name="next_due_date"
                            value={data.next_due_date}
                            onChange={(date) => setData('next_due_date', date)}
                        />
                        <InputError message={errors.next_due_date} />
                    </div>

                    {/* Auto-create */}
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="bill_auto_create"
                            checked={data.auto_create}
                            onCheckedChange={(checked) =>
                                setData('auto_create', checked === true)
                            }
                        />
                        <Label htmlFor="bill_auto_create">
                            Auto-create transaction when due
                        </Label>
                    </div>

                    {/* End type */}
                    <div className="grid gap-2">
                        <Label>End</Label>
                        <RadioGroup
                            value={data.end_type}
                            onValueChange={(val) =>
                                setData('end_type', val as EndType)
                            }
                        >
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="never"
                                    id="bill_end_type_never"
                                />
                                <Label htmlFor="bill_end_type_never">
                                    Never
                                </Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="on_date"
                                    id="bill_end_type_on_date"
                                />
                                <Label htmlFor="bill_end_type_on_date">
                                    On date
                                </Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="after_occurrences"
                                    id="bill_end_type_after_occurrences"
                                />
                                <Label htmlFor="bill_end_type_after_occurrences">
                                    After occurrences
                                </Label>
                            </div>
                        </RadioGroup>
                        <InputError message={errors.end_type} />
                    </div>

                    {/* End date */}
                    {data.end_type === 'on_date' && (
                        <div className="grid gap-2">
                            <Label htmlFor="bill_end_date">End date</Label>
                            <DatePicker
                                id="bill_end_date"
                                name="end_date"
                                value={data.end_date}
                                onChange={(date) => setData('end_date', date)}
                            />
                            <InputError message={errors.end_date} />
                        </div>
                    )}

                    {/* End after occurrences */}
                    {data.end_type === 'after_occurrences' && (
                        <div className="grid gap-2">
                            <Label htmlFor="bill_end_after_occurrences">
                                End after occurrences
                            </Label>
                            <Input
                                id="bill_end_after_occurrences"
                                name="end_after_occurrences"
                                type="number"
                                inputMode="decimal"
                                min="1"
                                value={data.end_after_occurrences}
                                onChange={(e) =>
                                    setData(
                                        'end_after_occurrences',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={errors.end_after_occurrences}
                            />
                        </div>
                    )}

                    <Button disabled={processing} className="w-full">
                        {processing
                            ? isEdit
                                ? 'Saving...'
                                : 'Creating...'
                            : isEdit
                              ? 'Save'
                              : 'Create'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function buildInitialData(bill: Bill | null, accounts: Account[]): FormData {
    if (bill) {
        return {
            name: bill.name,
            transaction_type: bill.transaction_type,
            amount: String(Math.abs(bill.amount)),
            account_id: String(bill.account_id),
            to_account_id: bill.to_account_id ? String(bill.to_account_id) : '',
            category_id: bill.category_id ? String(bill.category_id) : '',
            payee_id: bill.payee_id ? String(bill.payee_id) : '',
            recurrence_type: bill.recurrence_type,
            recurrence_interval: String(bill.recurrence_interval),
            recurrence_day: bill.recurrence_day
                ? String(bill.recurrence_day)
                : '',
            next_due_date: bill.next_due_date,
            auto_create: bill.auto_create,
            end_type: bill.end_type ?? 'never',
            end_date: bill.end_date ?? '',
            end_after_occurrences: bill.end_after_occurrences
                ? String(bill.end_after_occurrences)
                : '',
            is_active: bill.is_active,
        };
    }

    return {
        name: '',
        transaction_type: 'expense',
        amount: '',
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        to_account_id: accounts.length > 1 ? String(accounts[1].id) : '',
        category_id: '',
        payee_id: '',
        recurrence_type: 'monthly',
        recurrence_interval: '1',
        recurrence_day: '',
        next_due_date: '',
        auto_create: false,
        end_type: 'never',
        end_date: '',
        end_after_occurrences: '',
        is_active: true,
    };
}

function BillsLoadingSkeleton() {
    return (
        <>
            {/* Mobile skeleton */}
            <div className="space-y-3 sm:hidden">
                {Array.from({ length: 5 }).map((_, i) => (
                    <div
                        key={i}
                        className="flex rounded-lg border border-border bg-card"
                    >
                        <div className="flex-1 space-y-2 px-3 py-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="space-y-1">
                                    <Skeleton className="h-4 w-32" />
                                    <Skeleton className="h-3 w-24" />
                                </div>
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="flex gap-1.5">
                                    <Skeleton className="h-5 w-12 rounded-full" />
                                    <Skeleton className="h-5 w-14 rounded-full" />
                                </div>
                                <Skeleton className="h-3 w-20" />
                            </div>
                        </div>
                        <div className="w-10 border-l border-border" />
                    </div>
                ))}
            </div>

            {/* Desktop skeleton */}
            <Card className="hidden sm:block">
                <CardContent className="p-0">
                    <div className="space-y-4 p-6">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <div key={i} className="flex items-center gap-4">
                                <Skeleton className="h-4 w-48" />
                                <Skeleton className="h-4 w-20" />
                                <Skeleton className="h-4 w-16" />
                                <Skeleton className="hidden h-4 w-24 md:block" />
                                <Skeleton className="hidden h-4 w-20 md:block" />
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="hidden h-4 w-16 lg:block" />
                                <Skeleton className="h-4 w-12" />
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

function BillCard({
    bill,
    ledgerId,
    onPay,
    onDelete,
    onEdit,
}: {
    bill: Bill;
    ledgerId: number;
    onPay: (bill: Bill) => void;
    onDelete: (bill: Bill) => void;
    onEdit: (bill: Bill) => void;
}) {
    const [expanded, setExpanded] = useState(false);
    const transactions = bill.transactions ?? [];
    const hasTransactions = transactions.length > 0;
    const dueStatus = bill.is_active
        ? getDueStatus(bill.next_due_date)
        : 'upcoming';

    return (
        <div
            className={cn(
                'group relative flex rounded-lg border transition-colors',
                bill.is_active ? cardBorderStyles[dueStatus] : 'border-border',
                'bg-card',
            )}
        >
            {/* Content */}
            <div
                className="min-w-0 flex-1 px-3 py-3"
                onClick={() => {
                    if (hasTransactions) {
                        setExpanded((prev) => !prev);
                    }
                }}
            >
                {/* Row 1: Name + Amount */}
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate text-sm font-semibold">
                            {bill.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {bill.transaction_type === 'transfer'
                                ? `${bill.account?.name ?? '-'} -> ${bill.to_account?.name ?? '-'}`
                                : (bill.account?.name ?? '-')}{' '}
                            &middot;{' '}
                            {recurrenceDescription(
                                bill.recurrence_type,
                                bill.recurrence_interval,
                            )}
                        </p>
                    </div>
                    <span
                        className={`shrink-0 text-sm font-bold tabular-nums ${amountColor(bill)}`}
                    >
                        {formatAbsAmount(bill.amount)}
                    </span>
                </div>

                {/* Row 2: Badges + Due date */}
                <div className="mt-1.5 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-1.5">
                        <Badge
                            variant={bill.is_active ? 'default' : 'secondary'}
                            className="text-[10px]"
                        >
                            {bill.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        {bill.auto_create && (
                            <Badge variant="outline" className="text-[10px]">
                                Auto
                            </Badge>
                        )}
                    </div>
                    <span className={cn('text-xs', dueDateStyles[dueStatus])}>
                        {formatDate(bill.next_due_date)}
                    </span>
                </div>

                {/* Expandable payment history */}
                {expanded && hasTransactions && (
                    <div className="mt-3 border-t pt-2">
                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                            Recent payments
                        </p>
                        {transactions.map((txn) => (
                            <div
                                key={txn.id}
                                className="flex justify-between py-1 text-xs"
                            >
                                <span className="text-muted-foreground">
                                    {formatDate(txn.transaction_date)}
                                </span>
                                <span className="tabular-nums">
                                    {formatAbsAmount(txn.amount)}
                                </span>
                            </div>
                        ))}
                        <Link
                            href={transactionsIndex.url(ledgerId, {
                                query: {
                                    bill_id: String(bill.id),
                                },
                            })}
                            className="mt-1 block text-center text-xs font-medium text-primary hover:underline"
                        >
                            View all transactions
                        </Link>
                    </div>
                )}

                {/* Expand indicator */}
                {hasTransactions && (
                    <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                        {expanded ? (
                            <ChevronDown className="size-3" />
                        ) : (
                            <ChevronRight className="size-3" />
                        )}
                        {transactions.length} payment
                        {transactions.length !== 1 ? 's' : ''}
                    </div>
                )}

                {/* Mark as paid button */}
                {bill.is_active && (
                    <button
                        type="button"
                        className="mt-2 w-full rounded-md border border-border bg-muted/50 py-1.5 text-center text-xs font-medium text-foreground transition-colors hover:bg-muted"
                        onClick={(e) => {
                            e.stopPropagation();
                            onPay(bill);
                        }}
                    >
                        Mark as paid
                    </button>
                )}
            </div>

            {/* Right side action strip */}
            <div className="flex shrink-0 flex-col items-center justify-center gap-0.5 border-l border-border px-1.5">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            asChild
                        >
                            <Link
                                href={transactionsIndex.url(ledgerId, {
                                    query: {
                                        bill_id: String(bill.id),
                                    },
                                })}
                            >
                                <ExternalLink className="size-3.5" />
                            </Link>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Transactions</TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            onClick={(e) => {
                                e.stopPropagation();
                                onEdit(bill);
                            }}
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
                            className="size-8 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                            onClick={() => onDelete(bill)}
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Delete</TooltipContent>
                </Tooltip>
            </div>
        </div>
    );
}

function BillRow({
    bill,
    ledgerId,
    onPay,
    onDelete,
    onEdit,
}: {
    bill: Bill;
    ledgerId: number;
    onPay: (bill: Bill) => void;
    onDelete: (bill: Bill) => void;
    onEdit: (bill: Bill) => void;
}) {
    const [expanded, setExpanded] = useState(false);
    const transactions = bill.transactions ?? [];
    const hasTransactions = transactions.length > 0;
    const dueStatus = bill.is_active
        ? getDueStatus(bill.next_due_date)
        : 'upcoming';

    return (
        <>
            <TableRow
                className={cn(
                    'group',
                    hasTransactions ? 'cursor-pointer' : undefined,
                    bill.is_active ? dueStatusStyles[dueStatus] : '',
                )}
                onClick={() => {
                    if (hasTransactions) {
                        setExpanded((prev) => !prev);
                    }
                }}
            >
                <TableCell className="font-medium">
                    <div className="flex items-center gap-1.5">
                        {hasTransactions ? (
                            expanded ? (
                                <ChevronDown className="size-4 shrink-0 text-muted-foreground" />
                            ) : (
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            )
                        ) : (
                            <span className="inline-block w-4 shrink-0" />
                        )}
                        {bill.name}
                    </div>
                </TableCell>
                <TableCell>
                    <span className={amountColor(bill)}>
                        {formatAbsAmount(bill.amount)}
                    </span>
                </TableCell>
                <TableCell className="hidden text-muted-foreground md:table-cell">
                    {bill.transaction_type === 'transfer'
                        ? `${bill.account?.name ?? '-'} -> ${bill.to_account?.name ?? '-'}`
                        : (bill.account?.name ?? '-')}
                </TableCell>
                <TableCell className="hidden text-muted-foreground md:table-cell">
                    {recurrenceDescription(
                        bill.recurrence_type,
                        bill.recurrence_interval,
                    )}
                </TableCell>
                <TableCell className={dueDateStyles[dueStatus]}>
                    {formatDate(bill.next_due_date)}
                </TableCell>
                <TableCell className="hidden lg:table-cell">
                    <Badge variant={bill.is_active ? 'default' : 'secondary'}>
                        {bill.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </TableCell>
                <TableCell>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge
                                variant={
                                    bill.auto_create ? 'outline' : 'secondary'
                                }
                            >
                                {bill.auto_create ? 'Auto' : 'Manual'}
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent>
                            {bill.auto_create
                                ? 'Transactions are created automatically when due'
                                : 'You must manually pay this bill each cycle'}
                        </TooltipContent>
                    </Tooltip>
                </TableCell>
                <TableCell>
                    <div
                        className="flex items-center gap-2"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {bill.is_active && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-auto px-2 py-0.5 text-xs"
                                onClick={() => onPay(bill)}
                            >
                                {ACTION_LABELS[bill.transaction_type]?.pay ??
                                    'Record Payment'}
                            </Button>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-auto px-1.5 py-0.5 opacity-0 transition-opacity group-hover:opacity-100 data-[state=open]:opacity-100"
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={transactionsIndex.url(ledgerId, {
                                            query: {
                                                bill_id: String(bill.id),
                                            },
                                        })}
                                    >
                                        View transactions
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => onEdit(bill)}>
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => onDelete(bill)}
                                >
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </TableCell>
            </TableRow>
            {expanded && hasTransactions && (
                <TableRow className="bg-muted/50 hover:bg-muted/50">
                    <TableCell colSpan={COLUMN_COUNT} className="p-0">
                        <div className="px-10 py-3">
                            <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Payment History (last {transactions.length})
                            </p>
                            <Table>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="h-8 text-xs">
                                            Date
                                        </TableHead>
                                        <TableHead className="h-8 text-xs">
                                            Amount
                                        </TableHead>
                                        <TableHead className="h-8 text-xs">
                                            Account
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.map((txn) => (
                                        <TableRow
                                            key={txn.id}
                                            className="hover:bg-transparent"
                                        >
                                            <TableCell className="py-1.5 text-sm">
                                                {formatDate(
                                                    txn.transaction_date,
                                                )}
                                            </TableCell>
                                            <TableCell className="py-1.5 text-sm">
                                                {formatAbsAmount(txn.amount)}
                                            </TableCell>
                                            <TableCell className="py-1.5 text-sm text-muted-foreground">
                                                {txn.account?.name ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <div className="pt-2 text-center">
                                <Link
                                    href={transactionsIndex.url(ledgerId, {
                                        query: {
                                            bill_id: String(bill.id),
                                        },
                                    })}
                                    className="text-xs font-medium text-primary hover:underline"
                                >
                                    View all transactions
                                </Link>
                            </div>
                        </div>
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}

function BillsContent({
    onPay,
    onDelete,
    onEdit,
    onCreateNew,
}: {
    onPay: (bill: Bill) => void;
    onDelete: (bill: Bill) => void;
    onEdit: (bill: Bill) => void;
    onCreateNew: () => void;
}) {
    const { currentLedger, bills } = usePage<
        BillsPageProps & { currentLedger: { id: number } }
    >().props;
    const ledgerId = currentLedger!.id;
    const billsList = bills ?? [];

    if (billsList.length === 0) {
        return (
            <EmptyState
                icon={<Receipt className="size-6" />}
                title="No recurring transactions yet"
                description="Set up recurring transactions to track regular expenses and income."
                action={{
                    label: 'New recurring transaction',
                    onClick: onCreateNew,
                }}
            />
        );
    }

    return (
        <>
            {/* Mobile cards */}
            <div className="space-y-3 sm:hidden">
                {billsList.map((bill) => (
                    <BillCard
                        key={bill.id}
                        bill={bill}
                        ledgerId={ledgerId}
                        onPay={onPay}
                        onDelete={onDelete}
                        onEdit={onEdit}
                    />
                ))}
            </div>

            {/* Desktop table */}
            <Card className="hidden sm:block">
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Account
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Recurrence
                                </TableHead>
                                <TableHead>Next Due</TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Status
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Auto
                                </TableHead>
                                <TableHead className="sr-only">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {billsList.map((bill) => (
                                <BillRow
                                    key={bill.id}
                                    bill={bill}
                                    ledgerId={ledgerId}
                                    onPay={onPay}
                                    onDelete={onDelete}
                                    onEdit={onEdit}
                                />
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </>
    );
}

export default function BillsIndex() {
    const { currentLedger, accounts, categories, payees } = usePage<
        BillsPageProps & { currentLedger: { id: number; name: string } }
    >().props;
    const ledger = currentLedger!;

    const [billToDelete, setBillToDelete] = useState<Bill | null>(null);
    const [billToPay, setBillToPay] = useState<Bill | null>(null);
    const [formBill, setFormBill] = useState<Bill | 'create' | null>(null);
    const [deleting, setDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
    ];

    function handleEdit(bill: Bill) {
        setFormBill(bill);
    }

    function handleDelete() {
        if (!billToDelete) {
            return;
        }

        setDeleting(true);
        router.delete(`/ledgers/${ledger.id}/bills/${billToDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Recurring transaction deleted');
                setBillToDelete(null);
                setDeleting(false);
            },
            onError: () => {
                toast.error('Failed to delete recurring transaction');
                setDeleting(false);
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Recurring Transactions — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Recurring Transactions"
                        description="Manage recurring expenses and income for this ledger."
                    />

                    <Button
                        className="w-full sm:w-auto"
                        onClick={() => setFormBill('create')}
                    >
                        New Recurring Transaction
                    </Button>
                </div>

                <Deferred data="bills" fallback={<BillsLoadingSkeleton />}>
                    <BillsContent
                        onPay={setBillToPay}
                        onDelete={setBillToDelete}
                        onEdit={handleEdit}
                        onCreateNew={() => setFormBill('create')}
                    />
                </Deferred>
            </div>

            <PayBillDialog
                bill={billToPay}
                ledgerId={ledger.id}
                accounts={accounts}
                onClose={() => setBillToPay(null)}
            />

            <BillFormModal
                key={formBill && formBill !== 'create' ? formBill.id : 'create'}
                bill={formBill === 'create' ? null : formBill}
                open={formBill !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setFormBill(null);
                    }
                }}
                ledgerId={ledger.id}
                accounts={accounts}
                categories={categories}
                payees={payees}
            />

            {/* Delete confirmation dialog */}
            <Dialog
                open={billToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBillToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete recurring transaction</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{billToDelete?.name}</strong>? This action
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBillToDelete(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            {deleting ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
