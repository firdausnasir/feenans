import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import BillController from '@/actions/App/Http/Controllers/Ledger/BillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create as createRoute,
    index as billsIndex,
} from '@/routes/ledgers/bills';
import type { Account, BreadcrumbItem, Category, Ledger, Payee } from '@/types';

type RecurrenceType = 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';
type EndType = 'never' | 'on_date' | 'after_occurrences';

function describeRecurrence(
    type: string,
    interval: string,
    day: string,
): string {
    const n = parseInt(interval, 10) || 1;
    const dayNum = parseInt(day, 10);
    const dayStr = dayNum ? ` on day ${dayNum}` : '';

    const labels: Record<string, [string, string]> = {
        daily: ['day', 'days'],
        weekly: ['week', 'weeks'],
        monthly: ['month', 'months'],
        yearly: ['year', 'years'],
        custom: ['period', 'periods'],
    };

    const [singular, plural] = labels[type] ?? ['period', 'periods'];

    if (n === 1) {
        return `Every ${singular}${dayStr}`;
    }

    return `Every ${n} ${plural}${dayStr}`;
}

export default function CreateBill({
    ledger,
    accounts,
    categories,
    payees,
}: {
    ledger: Ledger;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
}) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        name: '',
        transaction_type: 'expense',
        amount: '',
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        category_id: '',
        payee_id: '',
        recurrence_type: 'monthly' as RecurrenceType,
        recurrence_interval: '1',
        recurrence_day: '',
        auto_create: false,
        end_type: 'never' as EndType,
        end_date: '',
        end_after_occurrences: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
        { title: 'New', href: createRoute.url(ledger.id) },
    ];

    // Local UI state for select components that use __none__ sentinel
    const categorySelectValue =
        data.category_id === '' ? '__none__' : data.category_id;
    const payeeSelectValue = data.payee_id === '' ? '__none__' : data.payee_id;

    function submit(e: FormEvent) {
        e.preventDefault();
        post(BillController.store.url(ledger.id), {
            onSuccess: () => toast.success('Recurring transaction created'),
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    toast.error(String(firstError));
                }
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`New Recurring Transaction — ${ledger.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New Recurring Transaction"
                    description="Set up a recurring expense or income for this ledger."
                />

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {/* Name */}
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            value={data.name}
                            onChange={(e) => {
                                setData('name', e.target.value);
                                clearErrors('name');
                            }}
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
                                clearErrors('transaction_type');
                            }}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="expense">Expense</SelectItem>
                                <SelectItem value="income">Income</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.transaction_type} />
                    </div>

                    {/* Amount */}
                    <div className="grid gap-2">
                        <Label htmlFor="amount">Amount (RM)</Label>
                        <Input
                            id="amount"
                            name="amount"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            min="0.01"
                            value={data.amount}
                            onChange={(e) => {
                                setData('amount', e.target.value);
                                clearErrors('amount');
                            }}
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
                                            onValueChange={(value) => {
                                                setData('account_id', value ?? '');
                                                clearErrors('account_id');
                                            }}
                                            placeholder="Select account"
                                            searchPlaceholder="Search accounts..."
                                        />
                        <InputError message={errors.account_id} />
                    </div>

                    {/* Category — grouped by parent */}
                    <div className="grid gap-2">
                        <Label>
                            Category{' '}
                            <span className="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <SearchableSelect
                                            options={categories.flatMap((parent) => {
                                                const items = [{
                                                    value: String(parent.id),
                                                    label: parent.children && parent.children.length > 0 ? `${parent.name} (general)` : parent.name,
                                                    group: parent.children && parent.children.length > 0 ? parent.name : undefined,
                                                    color: parent.color,
                                                }];
                                                if (parent.children) {
                                                    parent.children.forEach((child) => {
                                                        items.push({
                                                            value: String(child.id),
                                                            label: child.name,
                                                            group: parent.name,
                                                            color: child.color,
                                                        });
                                                    });
                                                }
                                                return items;
                                            })}
                                            value={data.category_id || null}
                                            onValueChange={(value) => {
                                                setData('category_id', value ?? '');
                                                clearErrors('category_id');
                                            }}
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
                                            onValueChange={(value) => {
                                                setData('payee_id', value ?? '');
                                                clearErrors('payee_id');
                                            }}
                                            placeholder="No payee"
                                            searchPlaceholder="Search payees..."
                                            allOption="No payee"
                                        />
                        <InputError message={errors.payee_id} />
                    </div>

                    {/* Recurrence */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Recurrence type</Label>
                            <Select
                                value={data.recurrence_type}
                                onValueChange={(val) => {
                                    setData(
                                        'recurrence_type',
                                        val as RecurrenceType,
                                    );
                                    clearErrors('recurrence_type');
                                }}
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
                            <Label htmlFor="recurrence_interval">
                                Every (interval)
                            </Label>
                            <Input
                                id="recurrence_interval"
                                name="recurrence_interval"
                                type="number"
                                inputMode="decimal"
                                min="1"
                                value={data.recurrence_interval}
                                onChange={(e) => {
                                    setData(
                                        'recurrence_interval',
                                        e.target.value,
                                    );
                                    clearErrors('recurrence_interval');
                                }}
                                required
                            />
                            <InputError message={errors.recurrence_interval} />
                        </div>
                    </div>

                    {/* Recurrence day — relevant for monthly/yearly/custom */}
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
                                        onClick={() => {
                                            setData(
                                                'recurrence_day',
                                                String(day),
                                            );
                                            clearErrors('recurrence_day');
                                        }}
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

                    {/* Auto-create */}
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="auto_create"
                            checked={data.auto_create}
                            onCheckedChange={(checked) => {
                                setData('auto_create', checked === true);
                                clearErrors('auto_create');
                            }}
                        />
                        <Label htmlFor="auto_create">
                            Auto-create transaction when due
                        </Label>
                    </div>

                    {/* End type */}
                    <div className="grid gap-2">
                        <Label>End</Label>
                        <RadioGroup
                            value={data.end_type}
                            onValueChange={(val) => {
                                setData('end_type', val as EndType);
                                clearErrors('end_type');
                            }}
                        >
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="never"
                                    id="end_type_never"
                                />
                                <Label htmlFor="end_type_never">Never</Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="on_date"
                                    id="end_type_on_date"
                                />
                                <Label htmlFor="end_type_on_date">
                                    On date
                                </Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="after_occurrences"
                                    id="end_type_after_occurrences"
                                />
                                <Label htmlFor="end_type_after_occurrences">
                                    After occurrences
                                </Label>
                            </div>
                        </RadioGroup>
                        <InputError message={errors.end_type} />
                    </div>

                    {/* End date */}
                    {data.end_type === 'on_date' && (
                        <div className="grid gap-2">
                            <Label htmlFor="end_date">End date</Label>
                            <DatePicker
                                id="end_date"
                                name="end_date"
                                value={data.end_date}
                                onChange={(date) => {
                                    setData('end_date', date);
                                    clearErrors('end_date');
                                }}
                            />
                            <InputError message={errors.end_date} />
                        </div>
                    )}

                    {/* End after occurrences */}
                    {data.end_type === 'after_occurrences' && (
                        <div className="grid gap-2">
                            <Label htmlFor="end_after_occurrences">
                                End after occurrences
                            </Label>
                            <Input
                                id="end_after_occurrences"
                                name="end_after_occurrences"
                                type="number"
                                inputMode="decimal"
                                min="1"
                                value={data.end_after_occurrences}
                                onChange={(e) => {
                                    setData(
                                        'end_after_occurrences',
                                        e.target.value,
                                    );
                                    clearErrors('end_after_occurrences');
                                }}
                                required
                            />
                            <InputError
                                message={errors.end_after_occurrences}
                            />
                        </div>
                    )}

                    <Button disabled={processing}>Create</Button>
                </form>
            </div>
        </AppLayout>
    );
}
