import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, Pencil, PiggyBank, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as destroyBudget,
    store as storeBudget,
    update as updateBudget,
} from '@/actions/App/Http/Controllers/Ledger/BudgetController';
import Heading from '@/components/heading';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
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
import { formatAbsAmount, formatDate } from '@/lib/format';
import { mapInertiaErrorsArray } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as budgetsIndex } from '@/routes/ledgers/budgets';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { BreadcrumbItem, BudgetStat, Category } from '@/types';

type FormState = {
    category_id: string;
    amount: string;
    period: string;
    start_date: string;
    end_date: string;
    rollover: boolean;
};

function getCycleBounds(
    referenceDate: Date,
    cycleStartDay: number,
): { start: Date; end: Date } {
    const day = referenceDate.getDate();
    let start: Date;

    if (day >= cycleStartDay) {
        start = new Date(
            referenceDate.getFullYear(),
            referenceDate.getMonth(),
            cycleStartDay,
        );
    } else {
        const prevYear =
            referenceDate.getMonth() === 0
                ? referenceDate.getFullYear() - 1
                : referenceDate.getFullYear();
        const prevMonth =
            referenceDate.getMonth() === 0 ? 11 : referenceDate.getMonth() - 1;
        const maxDay = new Date(prevYear, prevMonth + 1, 0).getDate();
        start = new Date(prevYear, prevMonth, Math.min(cycleStartDay, maxDay));
    }

    const endRaw = new Date(
        start.getFullYear(),
        start.getMonth() + 1,
        start.getDate() - 1,
    );

    return { start, end: endRaw };
}

function toDateString(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

function getPeriodBoundsForType(
    period: string,
    cycleStartDay: number,
): { start: string; end: string } {
    const today = new Date();

    if (period === 'weekly') {
        const dayOfWeek = today.getDay();
        const mondayOffset = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        const start = new Date(today);
        start.setDate(today.getDate() + mondayOffset);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);

        return { start: toDateString(start), end: toDateString(end) };
    }

    if (period === 'yearly') {
        const start = new Date(today.getFullYear(), 0, 1);
        const end = new Date(today.getFullYear(), 11, 31);

        return { start: toDateString(start), end: toDateString(end) };
    }

    const { start, end } = getCycleBounds(today, cycleStartDay);

    return { start: toDateString(start), end: toDateString(end) };
}

function getCycleStart(cycleStartDay: number): string {
    const { start } = getCycleBounds(new Date(), cycleStartDay);

    return toDateString(start);
}

function makeEmptyForm(cycleStartDay: number): FormState {
    return {
        category_id: '__none__',
        amount: '',
        period: 'monthly',
        start_date: getCycleStart(cycleStartDay),
        end_date: '',
        rollover: false,
    };
}

const statusColor: Record<string, string> = {
    good: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-orange-500',
    over: 'bg-red-500',
};

const statusLabel: Record<string, string> = {
    good: 'On track',
    warning: 'Getting close',
    danger: 'Almost over',
    over: 'Over budget',
};

function BudgetsLoadingSkeleton() {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
                <Card key={i}>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <Skeleton className="h-5 w-32" />
                            <Skeleton className="h-5 w-16 rounded-full" />
                        </div>
                        <Skeleton className="mt-1 h-3 w-40" />
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        <Skeleton className="h-2 w-full" />
                        <div className="flex justify-between">
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-20" />
                        </div>
                        <Skeleton className="h-4 w-28" />
                        <div className="flex gap-2">
                            <Skeleton className="h-8 w-14" />
                            <Skeleton className="h-8 w-16" />
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function BudgetsIndex() {
    const {
        currentLedger,
        budgets: budgetStats,
        categories,
    } = usePage<{
        categories: Category[];
        budgets?: BudgetStat[];
    }>().props;
    const ledger = currentLedger!;
    const budgets = budgetStats ?? [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Budgets', href: budgetsIndex.url(ledger.id) },
    ];

    const [showCreate, setShowCreate] = useState(false);
    const [editBudget, setEditBudget] = useState<BudgetStat | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [form, setForm] = useState<FormState>(
        makeEmptyForm(ledger.cycle_start_day),
    );
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    const handleCreate = () => {
        setForm(makeEmptyForm(ledger.cycle_start_day));
        setErrors({});
        setShowCreate(true);
    };

    const handleEdit = (budget: BudgetStat) => {
        setEditBudget(budget);
        setErrors({});
        setForm({
            category_id:
                budget.category_id !== null
                    ? String(budget.category_id)
                    : '__none__',
            amount: String(budget.amount),
            period: budget.period,
            start_date:
                budget.start_date ?? getCycleStart(ledger.cycle_start_day),
            end_date: '',
            rollover: budget.rollover,
        });
    };

    const closeDialog = () => {
        setShowCreate(false);
        setEditBudget(null);
        setErrors({});
    };

    const handleSubmit = () => {
        const payload = {
            category_id:
                form.category_id && form.category_id !== '__none__'
                    ? Number(form.category_id)
                    : null,
            amount: Number(form.amount),
            period: form.period,
            start_date: form.start_date,
            end_date: form.end_date || null,
            rollover: form.rollover,
        };

        setSubmitting(true);
        setErrors({});

        if (editBudget) {
            router.put(
                updateBudget.url({
                    ledger: ledger.id,
                    budget: editBudget.id,
                }),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Budget updated');
                        closeDialog();
                    },
                    onError: (inertiaErrors) => {
                        const validationErrors =
                            mapInertiaErrorsArray(inertiaErrors);
                        setErrors(validationErrors);

                        const firstError = Object.values(validationErrors)[0]?.[0];

                        if (firstError) {
                            toast.error(firstError);
                        }
                    },
                    onFinish: () => setSubmitting(false),
                },
            );

            return;
        }

        router.post(storeBudget.url(ledger.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Budget created');
                closeDialog();
            },
            onError: (inertiaErrors) => {
                const validationErrors = mapInertiaErrorsArray(inertiaErrors);
                setErrors(validationErrors);

                const firstError = Object.values(validationErrors)[0]?.[0];

                if (firstError) {
                    toast.error(firstError);
                }
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const handleDelete = (budget: BudgetStat) => {
        if (!confirm(`Delete budget for "${budget.category_name}"?`)) {
            return;
        }

        router.delete(
            destroyBudget.url({ ledger: ledger.id, budget: budget.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Budget deleted');
                },
                onError: () => {
                    toast.error('Failed to delete budget');
                },
            },
        );
    };

    const flatCategories = categories.flatMap((c) =>
        c.children && c.children.length > 0 ? [c, ...c.children] : [c],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Budgets — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Budgets"
                        description="Set spending limits and track your progress."
                    />
                    <Button className="w-full sm:w-auto" onClick={handleCreate}>
                        + New Budget
                    </Button>
                </div>

                <Deferred data="budgets" fallback={<BudgetsLoadingSkeleton />}>
                    {budgets.length === 0 ? (
                        <EmptyState
                            icon={<PiggyBank className="size-6" />}
                            title="No budgets yet"
                            description="Set monthly spending limits for your categories and track how you're doing."
                            action={{
                                label: 'Create your first budget',
                                onClick: handleCreate,
                            }}
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {budgets.map((budget) => (
                                <Card key={budget.id}>
                                    <CardHeader className="pb-2">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="inline-flex items-center gap-1.5 text-base">
                                                {budget.category_color && (
                                                    <span
                                                        className="inline-block h-2 w-2 shrink-0 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                budget.category_color,
                                                        }}
                                                    />
                                                )}
                                                {budget.category_name}
                                            </CardTitle>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-medium text-white ${
                                                    statusColor[budget.status] ??
                                                    'bg-muted'
                                                }`}
                                            >
                                                {statusLabel[budget.status]}
                                            </span>
                                        </div>
                                        <p className="text-xs text-muted-foreground capitalize">
                                            {budget.period} &middot;{' '}
                                            {formatDate(budget.period_start)} –{' '}
                                            {formatDate(budget.period_end)}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-3">
                                        <Progress
                                            value={budget.percentage}
                                            className="h-2"
                                        />
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Spent:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatAbsAmount(budget.spent)}
                                                </span>
                                            </span>
                                            <span className="text-muted-foreground">
                                                Budget:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatAbsAmount(budget.amount)}
                                                </span>
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm text-muted-foreground">
                                                {budget.percentage >= 100
                                                    ? `Over by ${formatAbsAmount(budget.spent - budget.amount)}`
                                                    : `${formatAbsAmount(budget.remaining)} remaining`}
                                            </p>
                                            <div className="flex items-center gap-0.5">
                                                {budget.category_id !== null && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={transactionsIndex.url(
                                                                        ledger.id,
                                                                        {
                                                                            query: {
                                                                                'category_ids[]':
                                                                                    budget.category_id,
                                                                                date_from:
                                                                                    budget.period_start,
                                                                                date_to:
                                                                                    budget.period_end,
                                                                                'transaction_types[]':
                                                                                    'expense',
                                                                            },
                                                                        },
                                                                    )}
                                                                >
                                                                    <ExternalLink className="size-3.5" />
                                                                </Link>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Transactions
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7"
                                                            onClick={() =>
                                                                handleEdit(budget)
                                                            }
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Edit
                                                    </TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    budget,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Delete
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </Deferred>

                {/* Create / Edit Dialog */}
                <Dialog
                    open={showCreate || editBudget !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeDialog();
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editBudget ? 'Edit Budget' : 'New Budget'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="flex flex-col gap-4 py-2">
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">
                                    Category (leave empty for overall)
                                </Label>
                                <SearchableSelect
                                    options={flatCategories.map((c) => ({
                                        value: String(c.id),
                                        label: c.parent_id
                                            ? `  \u21B3 ${c.name}`
                                            : c.name,
                                        color: c.color,
                                    }))}
                                    value={
                                        form.category_id === '__none__'
                                            ? null
                                            : form.category_id
                                    }
                                    onValueChange={(val) =>
                                        setForm((f) => ({
                                            ...f,
                                            category_id: val ?? '__none__',
                                        }))
                                    }
                                    placeholder="Overall budget"
                                    searchPlaceholder="Search categories..."
                                    allOption="Overall budget"
                                />
                                {errors.category_id && (
                                    <p className="text-xs text-destructive">
                                        {errors.category_id[0]}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="amount">Budget Amount</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    value={form.amount}
                                    onChange={(e) =>
                                        setForm((f) => ({
                                            ...f,
                                            amount: e.target.value,
                                        }))
                                    }
                                    placeholder="e.g. 500"
                                />
                                {errors.amount && (
                                    <p className="text-xs text-destructive">
                                        {errors.amount[0]}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="period">Period</Label>
                                <Select
                                    value={form.period}
                                    onValueChange={(val) =>
                                        setForm((f) => ({ ...f, period: val }))
                                    }
                                >
                                    <SelectTrigger id="period">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="weekly">
                                            Weekly
                                        </SelectItem>
                                        <SelectItem value="monthly">
                                            Monthly
                                        </SelectItem>
                                        <SelectItem value="yearly">
                                            Yearly
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Current cycle:{' '}
                                    {formatDate(
                                        getPeriodBoundsForType(
                                            form.period,
                                            ledger.cycle_start_day,
                                        ).start,
                                    )}{' '}
                                    –{' '}
                                    {formatDate(
                                        getPeriodBoundsForType(
                                            form.period,
                                            ledger.cycle_start_day,
                                        ).end,
                                    )}
                                </p>
                                {errors.period && (
                                    <p className="text-xs text-destructive">
                                        {errors.period[0]}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Start Date</Label>
                                <DatePicker
                                    id="start_date"
                                    value={form.start_date}
                                    onChange={(date) =>
                                        setForm((f) => ({
                                            ...f,
                                            start_date: date,
                                        }))
                                    }
                                    placeholder="Pick a start date"
                                />
                                {errors.start_date && (
                                    <p className="text-xs text-destructive">
                                        {errors.start_date[0]}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="rollover"
                                    checked={form.rollover}
                                    onCheckedChange={(checked) =>
                                        setForm((f) => ({
                                            ...f,
                                            rollover: checked === true,
                                        }))
                                    }
                                />
                                <Label htmlFor="rollover">
                                    Roll over unspent amount to next period
                                </Label>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleSubmit}
                                disabled={
                                    !form.amount ||
                                    !form.start_date ||
                                    submitting
                                }
                            >
                                {submitting
                                    ? 'Saving...'
                                    : editBudget
                                      ? 'Save changes'
                                      : 'Create budget'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
