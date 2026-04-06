import { Head, router, useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { index as accountsLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/AccountController';
import { index as categoriesLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/CategoryController';
import { index as payeesLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/PayeeController';
import { store as storeRoute } from '@/actions/App/Http/Controllers/Ledger/BillController';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';
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
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { buildAccountSelectOptions } from '@/lib/account-select-options';
import { buildCategoryOptions, describeRecurrence } from '@/lib/format';
import { mapInertiaErrors } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create as createRoute,
    index as billsIndex,
} from '@/routes/ledgers/bills';
import type { Account, BreadcrumbItem, Category, Ledger, Payee } from '@/types';

type ApiEnvelope<T> = { data: T };

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
    is_active: boolean;
    notify_email: boolean;
    end_type: EndType;
    end_date: string;
    end_after_occurrences: string;
};

type FormErrors = Partial<Record<keyof FormData | 'new_payee_name', string>>;

function BillFormSkeleton() {
    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <div className="space-y-6 rounded-xl border border-sidebar-border/70 p-6">
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-16" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-12" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Skeleton className="h-4 w-28" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                    <div className="grid gap-2">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <Skeleton className="h-9 w-24" />
            </div>
        </div>
    );
}

function BillFormErrorState({ onRetry }: { onRetry: () => void }) {
    return (
        <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
            <p className="text-sm text-muted-foreground">Failed to load form data.</p>
            <Button variant="outline" size="sm" onClick={onRetry}>
                Retry
            </Button>
        </div>
    );
}

function CreateBillForm({
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
    const accountOptions = buildAccountSelectOptions(accounts);
    const resolveDefaultToAccountId = (sourceAccountId: string): string =>
        buildAccountSelectOptions(accounts, sourceAccountId)[0]?.value ?? '';

    const [data, setFormData] = useState<FormData>(() => ({
        name: '',
        transaction_type: 'expense',
        amount: '',
        account_id: accountOptions[0]?.value ?? '',
        to_account_id: resolveDefaultToAccountId(accountOptions[0]?.value ?? ''),
        category_id: '',
        payee_id: '',
        recurrence_type: 'monthly',
        recurrence_interval: '1',
        recurrence_day: '',
        next_due_date: '',
        auto_create: false,
        is_active: true,
        notify_email: true,
        end_type: 'never',
        end_date: '',
        end_after_occurrences: '',
    }));
    const destinationAccountOptions = buildAccountSelectOptions(
        accounts,
        data.account_id,
    );
    const [newPayeeName, setNewPayeeName] = useState('');
    const [errors, setErrors] = useState<FormErrors>({});
    const [processing, setProcessing] = useState(false);

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
        { title: 'New', href: createRoute.url(ledger.id) },
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
            is_active: data.is_active,
            notify_email: data.notify_email,
            end_type: data.end_type,
            end_date: data.end_date || null,
            end_after_occurrences: data.end_after_occurrences || null,
        };

        router.post(storeRoute.url(ledger.id), formPayload, {
            onSuccess: () => {
                toast.success('Recurring transaction created');
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`New Recurring Transaction — ${ledger.name}`} />

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
                            onValueChange={(value) => {
                                setData('transaction_type', value);

                                if (value === 'transfer') {
                                    setData('category_id', '');
                                    setData('payee_id', '');
                                    setNewPayeeName('');

                                    if (
                                        !data.to_account_id &&
                                        accounts.length > 1
                                    ) {
                                        const fallbackAccountId =
                                            resolveDefaultToAccountId(
                                                data.account_id,
                                            );

                                        if (fallbackAccountId) {
                                            setData(
                                                'to_account_id',
                                                fallbackAccountId,
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
                            options={accountOptions}
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
                                options={destinationAccountOptions}
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

                    {/* Active */}
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="is_active">Active</Label>
                            <p className="text-sm text-muted-foreground">
                                Inactive recurring transactions are paused and won&apos;t be tracked or auto-created.
                            </p>
                        </div>
                    </div>

                    {/* Email notifications */}
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="notify_email"
                            checked={data.notify_email}
                            onCheckedChange={(checked) =>
                                setData('notify_email', checked === true)
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="notify_email">Email reminders</Label>
                            <p className="text-sm text-muted-foreground">
                                Receive email reminders 3 days before the due date, on the due date, and when overdue.
                            </p>
                        </div>
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

                    <Button disabled={processing}>Create</Button>
                </form>
            </div>
        </AppLayout>
    );
}

export default function CreateBill() {
    const { currentLedger } = usePage().props;
    const ledger = currentLedger! as Ledger;

    const accountsLoaderState = useHttp<Record<string, never>, ApiEnvelope<Account[]>>({});
    const categoriesLoaderState = useHttp<Record<string, never>, ApiEnvelope<Category[]>>({});
    const payeesLoaderState = useHttp<Record<string, never>, ApiEnvelope<Payee[]>>({});

    const [hasResolved, setHasResolved] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const mountRef = useRef(false);

    function loadAll() {
        let cancelled = false;
        setLoadError(null);

        accountsLoaderState.cancel();
        categoriesLoaderState.cancel();
        payeesLoaderState.cancel();

        void Promise.allSettled([
            accountsLoaderState.get(accountsLoader.url(ledger.id), {
                onCancel: () => {
 cancelled = true; 
},
            }),
            categoriesLoaderState.get(categoriesLoader.url(ledger.id), {
                onCancel: () => {
 cancelled = true; 
},
            }),
            payeesLoaderState.get(payeesLoader.url(ledger.id), {
                onCancel: () => {
 cancelled = true; 
},
            }),
        ]).then((results) => {
            if (cancelled) {
return;
}

            const anyFailed = results.some((r) => r.status === 'rejected');

            if (anyFailed) {
                setLoadError('Failed to load form data.');
            }

            setHasResolved(true);
        });

        return () => {
 cancelled = true; 
};
    }

    useEffect(() => {
        if (mountRef.current) {
return;
}

        mountRef.current = true;
        const cleanup = loadAll();

        return () => {
            cleanup();
            accountsLoaderState.cancel();
            categoriesLoaderState.cancel();
            payeesLoaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ledger.id]);

    const accounts = accountsLoaderState.response?.data ?? [];
    const categories = categoriesLoaderState.response?.data ?? [];
    const payees = payeesLoaderState.response?.data ?? [];

    if (!hasResolved) {
        return (
            <AppLayout breadcrumbs={[]}>
                <Head title={`New Recurring Transaction — ${ledger.name}`} />
                <BoneSkeleton name="bill-create-form" loading fallback={<BillFormSkeleton />}>
                    <BillFormSkeleton />
                </BoneSkeleton>
            </AppLayout>
        );
    }

    if (loadError) {
        return (
            <AppLayout breadcrumbs={[]}>
                <Head title={`New Recurring Transaction — ${ledger.name}`} />
                <BillFormErrorState onRetry={() => {
                    setHasResolved(false);
                    mountRef.current = false;
                    loadAll();
                }} />
            </AppLayout>
        );
    }

    return (
        <CreateBillForm
            ledger={ledger}
            accounts={accounts}
            categories={categories}
            payees={payees}
        />
    );
}
