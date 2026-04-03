import { Head, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update as updateRoute } from '@/actions/App/Http/Controllers/Ledger/BillController';
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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { buildCategoryOptions, describeRecurrence } from '@/lib/format';
import { mapInertiaErrors } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { edit as editRoute, index as billsIndex } from '@/routes/ledgers/bills';
import type { Account, Bill, BreadcrumbItem, Category, Payee } from '@/types';

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
};

type FormErrors = Partial<Record<keyof FormData | 'new_payee_name', string>>;

export default function EditBill() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger!;

    const { bill, accounts, categories, payees } = usePage<{
        bill: Bill;
        accounts: Account[];
        categories: Category[];
        payees: Payee[];
    }>().props;

    const [data, setFormData] = useState<FormData>({
        name: bill.name,
        transaction_type: bill.transaction_type ?? 'expense',
        amount: String(bill.amount),
        account_id: String(bill.account_id),
        to_account_id: bill.to_account_id ? String(bill.to_account_id) : '',
        category_id: bill.category_id ? String(bill.category_id) : '',
        payee_id: bill.payee_id ? String(bill.payee_id) : '',
        recurrence_type: bill.recurrence_type as RecurrenceType,
        recurrence_interval: String(bill.recurrence_interval),
        recurrence_day:
            bill.recurrence_day != null ? String(bill.recurrence_day) : '',
        next_due_date: bill.next_due_date ?? '',
        auto_create: bill.auto_create,
        end_type: (bill.end_type ?? 'never') as EndType,
        end_date: bill.end_date ?? '',
        end_after_occurrences:
            bill.end_after_occurrences != null
                ? String(bill.end_after_occurrences)
                : '',
    });
    const [errors, setErrors] = useState<FormErrors>({});
    const [processing, setProcessing] = useState(false);
    const [newPayeeName, setNewPayeeName] = useState('');

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

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        {
            title: 'Recurring Transactions',
            href: billsIndex.url(ledger.id),
        },
        {
            title: bill.name,
            href: editRoute.url({
                ledger: ledger.id,
                bill: bill.id,
            }),
        },
    ];

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
            new_payee_name:
                data.transaction_type === 'transfer'
                    ? null
                    : newPayeeName || null,
            recurrence_type: data.recurrence_type,
            recurrence_interval: data.recurrence_interval,
            recurrence_day: data.recurrence_day || null,
            next_due_date: data.next_due_date,
            auto_create: data.auto_create,
            end_type: data.end_type,
            end_date: data.end_date || null,
            end_after_occurrences: data.end_after_occurrences || null,
        };

        router.put(
            updateRoute.url({ ledger: ledger.id, bill: bill.id }),
            formPayload,
            {
                onSuccess: () => {
                    toast.success('Recurring transaction updated');
                },
                onError: (errs) => {
                    setErrors(mapInertiaErrors<FormErrors>(errs));
                    setProcessing(false);
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${bill.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
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
                            onValueChange={(value: string) => {
                                setData('transaction_type', value);

                                if (value === 'transfer') {
                                    setData('category_id', '');
                                    setData('payee_id', '');
                                    setNewPayeeName('');

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
                        <Label htmlFor="amount">Amount (RM)</Label>
                        <Input
                            id="amount"
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
                            {/* Category — grouped by parent */}
                            <div className="grid gap-2">
                                <Label>
                                    Category{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <SearchableSelect
                                    options={buildCategoryOptions(categories)}
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
                                    value={
                                        data.payee_id ||
                                        (newPayeeName
                                            ? `new:${newPayeeName}`
                                            : null)
                                    }
                                    onValueChange={(value) => {
                                        setData('payee_id', value ?? '');
                                        setNewPayeeName('');
                                    }}
                                    placeholder="No payee"
                                    searchPlaceholder="Search payees..."
                                    allOption="No payee"
                                    creatable
                                    onCreate={(name) => {
                                        setData('payee_id', '');
                                        setNewPayeeName(name);
                                    }}
                                    createLabel={
                                        newPayeeName
                                            ? `${newPayeeName} (new)`
                                            : undefined
                                    }
                                />
                                <InputError
                                    message={
                                        errors.payee_id ?? errors.new_payee_name
                                    }
                                />
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
                        <Label htmlFor="next_due_date">Next due date</Label>
                        <DatePicker
                            id="next_due_date"
                            name="next_due_date"
                            value={data.next_due_date}
                            onChange={(date) => setData('next_due_date', date)}
                        />
                        <InputError message={errors.next_due_date} />
                    </div>

                    {/* Auto-create */}
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="auto_create"
                            checked={data.auto_create}
                            onCheckedChange={(checked) =>
                                setData('auto_create', checked === true)
                            }
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
                            onValueChange={(val) =>
                                setData('end_type', val as EndType)
                            }
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
                                onChange={(date) => setData('end_date', date)}
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

                    <Button disabled={processing}>Save changes</Button>
                </form>
            </div>
        </AppLayout>
    );
}
