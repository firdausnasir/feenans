import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    destroy as destroyBudget,
    index as budgetsIndex,
    store as storeBudget,
    update as updateBudget,
} from '@/routes/ledgers/budgets';
import type { BreadcrumbItem, BudgetStat, Category, Ledger } from '@/types';

type Props = {
    ledger: Ledger;
    budgets: BudgetStat[];
    categories: Category[];
};

type FormState = {
    category_id: string;
    amount: string;
    period: string;
    start_date: string;
    end_date: string;
    rollover: boolean;
};

const emptyForm = (): FormState => ({
    category_id: '',
    amount: '',
    period: 'monthly',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    rollover: false,
});

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

export default function BudgetsIndex({ ledger, budgets, categories }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Budgets', href: budgetsIndex.url(ledger.id) },
    ];

    const [showCreate, setShowCreate] = useState(false);
    const [editBudget, setEditBudget] = useState<BudgetStat | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm());

    const handleCreate = () => {
        setForm(emptyForm());
        setShowCreate(true);
    };

    const handleEdit = (budget: BudgetStat) => {
        setEditBudget(budget);
        setForm({
            category_id:
                budget.category_id !== null ? String(budget.category_id) : '',
            amount: String(budget.amount),
            period: budget.period,
            start_date: new Date().toISOString().slice(0, 10),
            end_date: '',
            rollover: budget.rollover,
        });
    };

    const handleSubmit = () => {
        const payload = {
            category_id: form.category_id ? Number(form.category_id) : null,
            amount: Number(form.amount),
            period: form.period,
            start_date: form.start_date,
            end_date: form.end_date || null,
            rollover: form.rollover,
        };

        if (editBudget) {
            router.put(
                updateBudget.url({ ledger: ledger.id, budget: editBudget.id }),
                payload,
                {
                    onSuccess: () => {
                        setEditBudget(null);
                        toast.success('Budget updated');
                    },
                },
            );
        } else {
            router.post(storeBudget.url(ledger.id), payload, {
                onSuccess: () => {
                    setShowCreate(false);
                    toast.success('Budget created');
                },
            });
        }
    };

    const handleDelete = (budget: BudgetStat) => {
        if (!confirm(`Delete budget for "${budget.category_name}"?`)) {
            return;
        }
        router.delete(
            destroyBudget.url({ ledger: ledger.id, budget: budget.id }),
            {
                onSuccess: () => {
                    toast.success('Budget deleted');
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

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Budgets"
                        description="Set spending limits and track your progress."
                    />
                    <Button onClick={handleCreate}>+ New Budget</Button>
                </div>

                {budgets.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center text-muted-foreground">
                        <p className="text-lg font-medium">No budgets yet</p>
                        <p className="text-sm">
                            Create a budget to track your spending limits.
                        </p>
                        <Button onClick={handleCreate}>
                            Create your first budget
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {budgets.map((budget) => (
                            <Card key={budget.id}>
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">
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
                                        {budget.period}
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
                                    <p className="text-sm text-muted-foreground">
                                        {budget.percentage >= 100
                                            ? `Over by ${formatAbsAmount(budget.spent - budget.amount)}`
                                            : `${formatAbsAmount(budget.remaining)} remaining`}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleEdit(budget)}
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDelete(budget)}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Create / Edit Dialog */}
                <Dialog
                    open={showCreate || editBudget !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setShowCreate(false);
                            setEditBudget(null);
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
                                <Select
                                    value={form.category_id}
                                    onValueChange={(val) =>
                                        setForm((f) => ({
                                            ...f,
                                            category_id: val,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Overall budget" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            Overall budget
                                        </SelectItem>
                                        {flatCategories.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.parent_id
                                                    ? `  ↳ ${c.name}`
                                                    : c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="amount">Budget Amount</Label>
                                <Input
                                    id="amount"
                                    type="number"
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
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={form.start_date}
                                    onChange={(e) =>
                                        setForm((f) => ({
                                            ...f,
                                            start_date: e.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setShowCreate(false);
                                    setEditBudget(null);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleSubmit}
                                disabled={!form.amount || !form.start_date}
                            >
                                {editBudget ? 'Save changes' : 'Create budget'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
