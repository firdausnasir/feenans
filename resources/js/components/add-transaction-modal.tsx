import type { Page } from '@inertiajs/core';
import { Link, router, usePage } from '@inertiajs/react';
import confetti from 'canvas-confetti';
import { CreditCard, Loader2, Paperclip, PlusCircle, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import TransactionController from '@/actions/App/Http/Controllers/Ledger/TransactionController';
import { buildAddTransactionSubmitOptions } from '@/components/add-transaction-modal-options';
import InputError from '@/components/input-error';
import { SearchableSelect } from '@/components/searchable-select';
import { TagPill } from '@/components/tag-pill';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

import { cn } from '@/lib/utils';
import { index as accountsIndex } from '@/routes/ledgers/accounts';
import type { Account, Category, Ledger, Payee, Tag } from '@/types';

type TransactionMode = 'expense' | 'income' | 'transfer';
type SplitDraft = {
    id: number;
    amount: string;
    category_id: string;
    payee_id: string;
    description: string;
};

export type DuplicateData = {
    transaction_type: TransactionMode;
    account_id: number;
    to_account_id?: number | null;
    category_id: number | null;
    payee_id: number | null;
    amount: string;
    description: string | null;
    notes: string | null;
    tag_ids: number[];
};

type ModalData = {
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
};

function useModalData(open: boolean) {
    const { transactionModalData } = usePage().props;
    const fetchedRef = useRef(false);

    useEffect(() => {
        if (!open || transactionModalData || fetchedRef.current) {
            return;
        }

        fetchedRef.current = true;
        router.reload({ only: ['transactionModalData'] });
    }, [open, transactionModalData]);

    const invalidateCache = useCallback(() => {
        fetchedRef.current = false;
        router.reload({ only: ['transactionModalData'] });
    }, []);

    const data: ModalData | null = transactionModalData
        ? {
              accounts: transactionModalData.accounts ?? [],
              categories: transactionModalData.categories ?? [],
              payees: transactionModalData.payees ?? [],
              tags: transactionModalData.tags ?? [],
          }
        : null;

    const loading = open && !transactionModalData;

    return { data, loading, invalidateCache };
}

export function AddTransactionModal({
    ledger,
    defaultAccountId,
    externalOpen,
    onExternalOpenChange,
    initialData,
    onTransactionAdded,
    onModalClosed,
    modal = false,
}: {
    ledger: Ledger;
    defaultAccountId?: number;
    externalOpen?: boolean;
    onExternalOpenChange?: (open: boolean) => void;
    initialData?: DuplicateData | null;
    onTransactionAdded?: () => void;
    onModalClosed?: () => void;
    modal?: boolean;
}) {
    const isControlled = externalOpen !== undefined;
    const [internalOpen, setInternalOpen] = useState(false);
    const open = isControlled ? externalOpen : internalOpen;
    const hadSavesRef = useRef(false);

    function setOpen(value: boolean) {
        if (!value && hadSavesRef.current) {
            hadSavesRef.current = false;
            onModalClosed?.();
        }

        if (isControlled) {
            onExternalOpenChange?.(value);
        } else {
            setInternalOpen(value);
        }
    }

    const { data: modalData, loading, invalidateCache } = useModalData(open);
    const accounts = useMemo(() => modalData?.accounts ?? [], [modalData]);
    const categories = useMemo(() => modalData?.categories ?? [], [modalData]);
    const payees = modalData?.payees ?? [];
    const tags = modalData?.tags ?? [];

    const resolveDefaultAccountId = useCallback((): string => {
        if (defaultAccountId) {
            return String(defaultAccountId);
        }

        return accounts.length > 0 ? String(accounts[0].id) : '';
    }, [defaultAccountId, accounts]);

    const resolveDefaultToAccountId = useCallback(
        (sourceAccountId: string): string => {
            const fallback = accounts.find(
                (account) => String(account.id) !== sourceAccountId,
            );

            return fallback ? String(fallback.id) : '';
        },
        [accounts],
    );

    const [mode, setMode] = useState<TransactionMode>('expense');
    const [accountId, setAccountId] = useState<string>('');
    const [toAccountId, setToAccountId] = useState<string>('');
    const [categoryId, setCategoryId] = useState<string>('');
    const [selectedPayeeId, setSelectedPayeeId] = useState<string>('');
    const [newPayeeNameForSubmit, setNewPayeeNameForSubmit] = useState('');
    const [selectedTagIds, setSelectedTagIds] = useState<number[]>([]);
    const [isSplitTransaction, setIsSplitTransaction] = useState(false);
    const [amount, setAmount] = useState('');
    const [description, setDescription] = useState('');
    const [notes, setNotes] = useState('');
    const [splitRows, setSplitRows] = useState<SplitDraft[]>([
        { id: 1, amount: '', category_id: '', payee_id: '', description: '' },
        { id: 2, amount: '', category_id: '', payee_id: '', description: '' },
    ]);
    const [pendingFiles, setPendingFiles] = useState<File[]>([]);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const amountInputRef = useRef<HTMLInputElement>(null);
    const [rapidEntry, setRapidEntry] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('rapid-entry') === 'true';
        }

        return false;
    });

    const splitRowId = useRef(3);
    const [transactionDate, setTransactionDate] = useState(
        new Date().toISOString().slice(0, 10),
    );

    const initializedDuplicateRef = useRef<DuplicateData | null>(null);

    useEffect(() => {
        if (open) {
            return;
        }

        initializedDuplicateRef.current = null;
    }, [open]);

    useEffect(() => {
        if (
            !initialData ||
            !open ||
            initialData === initializedDuplicateRef.current
        ) {
            return;
        }

        let cancelled = false;

        queueMicrotask(() => {
            if (cancelled) {
                return;
            }

            initializedDuplicateRef.current = initialData;
            setMode(initialData.transaction_type);
            setAccountId(String(initialData.account_id));
            setToAccountId(
                initialData.to_account_id
                    ? String(initialData.to_account_id)
                    : resolveDefaultToAccountId(String(initialData.account_id)),
            );
            setCategoryId(
                initialData.category_id ? String(initialData.category_id) : '',
            );
            setSelectedPayeeId(
                initialData.payee_id ? String(initialData.payee_id) : '',
            );
            setNewPayeeNameForSubmit('');
            setAmount(String(Math.abs(parseFloat(initialData.amount || '0'))));
            setDescription(initialData.description ?? '');
            setNotes(initialData.notes ?? '');
            setSelectedTagIds(initialData.tag_ids ?? []);
            setTransactionDate(new Date().toISOString().slice(0, 10));
        });

        return () => {
            cancelled = true;
        };
    }, [initialData, open, resolveDefaultToAccountId]);

    const effectiveAccountId = accountId || resolveDefaultAccountId();
    const effectiveToAccountId =
        toAccountId || resolveDefaultToAccountId(effectiveAccountId);

    function handleSourceAccountChange(newAccountId: string | null) {
        const id = newAccountId ?? '';
        setAccountId(id);

        if (id === effectiveToAccountId) {
            const fallback = accounts.find((a) => String(a.id) !== id);
            setToAccountId(fallback ? String(fallback.id) : '');
        }
    }

    // Build grouped category structure: parents with their children
    const groupedCategories = useMemo(() => {
        const filtered = categories.filter((c) => c.transaction_type === mode);

        // Separate parents and children
        const parents = filtered.filter((c) => c.parent_id === null);
        const childrenMap = new Map<number, Category[]>();

        filtered
            .filter((c) => c.parent_id !== null)
            .forEach((c) => {
                const pid = c.parent_id!;

                if (!childrenMap.has(pid)) {
                    childrenMap.set(pid, []);
                }

                childrenMap.get(pid)!.push(c);
            });

        return parents.map((parent) => ({
            parent,
            children: childrenMap.get(parent.id) ?? [],
        }));
    }, [categories, mode]);

    // Build SearchableSelect options for categories
    const categoryOptions = useMemo(() => {
        return groupedCategories.flatMap(({ parent, children }) => {
            const items = [
                {
                    value: String(parent.id),
                    label:
                        children.length > 0
                            ? `${parent.name} (general)`
                            : parent.name,
                    group: children.length > 0 ? parent.name : undefined,
                    color: parent.color,
                },
            ];

            children.forEach((child) => {
                items.push({
                    value: String(child.id),
                    label: child.name,
                    group: parent.name,
                    color: child.color,
                });
            });

            return items;
        });
    }, [groupedCategories]);

    const splitTotal = useMemo(
        () =>
            splitRows.reduce(
                (total, split) => total + (Number(split.amount || 0) || 0),
                0,
            ),
        [splitRows],
    );

    const splitRemainder = useMemo(() => {
        return Number(((Number(amount || 0) || 0) - splitTotal).toFixed(2));
    }, [amount, splitTotal]);

    function addSplitRow() {
        setSplitRows((prev) => [
            ...prev,
            {
                id: splitRowId.current++,
                amount: '',
                category_id: '',
                payee_id: '',
                description: '',
            },
        ]);
    }

    function updateSplitRow(
        id: number,
        key: keyof Omit<SplitDraft, 'id'>,
        value: string,
    ) {
        setSplitRows((prev) =>
            prev.map((row) => (row.id === id ? { ...row, [key]: value } : row)),
        );
    }

    function removeSplitRow(id: number) {
        setSplitRows((prev) => prev.filter((row) => row.id !== id));
    }

    function resetForm() {
        setMode('expense');
        setAccountId('');
        setToAccountId('');
        setCategoryId('');
        setSelectedPayeeId('');
        setNewPayeeNameForSubmit('');
        setSelectedTagIds([]);
        setIsSplitTransaction(false);
        setAmount('');
        setDescription('');
        setNotes('');
        setSplitRows([
            {
                id: 1,
                amount: '',
                category_id: '',
                payee_id: '',
                description: '',
            },
            {
                id: 2,
                amount: '',
                category_id: '',
                payee_id: '',
                description: '',
            },
        ]);
        splitRowId.current = 3;
        setPendingFiles([]);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        setTransactionDate(new Date().toISOString().slice(0, 10));
    }

    function resetForRapidEntry() {
        setAmount('');
        setCategoryId('');
        setSelectedPayeeId('');
        setNewPayeeNameForSubmit('');
        setSelectedTagIds([]);
        setIsSplitTransaction(false);
        setDescription('');
        setNotes('');
        setSplitRows([
            {
                id: 1,
                amount: '',
                category_id: '',
                payee_id: '',
                description: '',
            },
            {
                id: 2,
                amount: '',
                category_id: '',
                payee_id: '',
                description: '',
            },
        ]);
        splitRowId.current = 3;
        setPendingFiles([]);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        setTimeout(() => {
            amountInputRef.current?.focus();
        }, 0);
    }

    const [processing, setProcessing] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setFormErrors({});

        const formData = new FormData(e.target as HTMLFormElement);

        // Append files manually since the hidden file input has no name
        for (const file of pendingFiles) {
            formData.append('attachments[]', file);
        }

        router.post(TransactionController.store.url(ledger.id), formData, {
            forceFormData: true,
            ...buildAddTransactionSubmitOptions(),
            onSuccess: handleSuccess,
            onError: (errors) => {
                setFormErrors(errors);
                const firstError = Object.values(errors)[0];

                if (firstError) {
                    toast.error(String(firstError));
                }
            },
            onFinish: () => setProcessing(false),
        });
    }

    function handleSuccess(page: Page) {
        const flash = page.props.flash as {
            first_transaction?: boolean;
        };

        if (flash?.first_transaction) {
            confetti({
                particleCount: 100,
                spread: 70,
                origin: { y: 0.6 },
            });
            toast.success(
                "Your first transaction! You're on your way to financial clarity.",
                { duration: 5000 },
            );
        } else {
            toast.success('Transaction saved');
        }

        // Invalidate cache so new payees are picked up on next open
        invalidateCache();

        // Track that a save occurred (for onModalClosed callback)
        hadSavesRef.current = true;

        // Notify parent to refetch data
        onTransactionAdded?.();

        if (rapidEntry) {
            resetForRapidEntry();
        } else {
            setOpen(false);
            resetForm();
        }
    }

    return (
        <Dialog open={open} modal={modal} onOpenChange={setOpen}>
            {!isControlled && (
                <DialogTrigger asChild>
                    <Button className="w-full gap-2 sm:w-auto">
                        <PlusCircle className="size-4" />
                        Add transaction
                    </Button>
                </DialogTrigger>
            )}

            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {initialData
                            ? 'Duplicate transaction'
                            : 'Add transaction'}
                    </DialogTitle>
                    <DialogDescription>
                        Quickly log income, expenses, or transfers without
                        leaving the ledger.
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <div className="flex flex-col items-center gap-3 py-12">
                        <Loader2 className="size-6 animate-spin text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Loading...
                        </p>
                    </div>
                ) : accounts.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 py-8 text-center">
                        <CreditCard className="size-12 text-muted-foreground" />
                        <div>
                            <p className="font-medium">No accounts yet</p>
                            <p className="text-sm text-muted-foreground">
                                You need at least one account to record
                                transactions.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={accountsIndex.url(ledger.id)}>
                                Create your first account
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <>
                            <div className="grid grid-cols-3 gap-1">
                                {(
                                    ['expense', 'income', 'transfer'] as const
                                ).map((type) => (
                                    <Button
                                        key={type}
                                        type="button"
                                        variant={
                                            mode === type
                                                ? 'default'
                                                : 'outline'
                                        }
                                        className="capitalize"
                                        onClick={() => setMode(type)}
                                    >
                                        {type}
                                    </Button>
                                ))}
                            </div>

                            <input
                                type="hidden"
                                name="transaction_type"
                                value={mode}
                            />

                            {isSplitTransaction && mode !== 'transfer'
                                ? splitRows.map((split, index) => (
                                      <div key={split.id}>
                                          <input
                                              type="hidden"
                                              name={`splits[${index}][amount]`}
                                              value={split.amount}
                                          />
                                          <input
                                              type="hidden"
                                              name={`splits[${index}][category_id]`}
                                              value={split.category_id}
                                          />
                                          <input
                                              type="hidden"
                                              name={`splits[${index}][payee_id]`}
                                              value={split.payee_id}
                                          />
                                          <input
                                              type="hidden"
                                              name={`splits[${index}][description]`}
                                              value={split.description}
                                          />
                                      </div>
                                  ))
                                : null}

                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount</Label>
                                <div className="relative">
                                    <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                        {ledger.currency_code}
                                    </span>
                                    <Input
                                        id="amount"
                                        name="amount"
                                        ref={amountInputRef}
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min="0.01"
                                        autoFocus
                                        required
                                        className="pl-14"
                                        value={amount}
                                        onChange={(event) => {
                                            const value = event.target.value;

                                            if (
                                                value !== '' &&
                                                Number(value) < 0
                                            ) {
                                                return;
                                            }

                                            setAmount(value);
                                        }}
                                    />
                                </div>
                                <InputError message={formErrors.amount} />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="account_id">Account</Label>
                                    <input
                                        type="hidden"
                                        name="account_id"
                                        value={effectiveAccountId}
                                    />
                                    <SearchableSelect
                                        options={accounts.map((account) => ({
                                            value: String(account.id),
                                            label: account.name,
                                            color: account.color,
                                        }))}
                                        value={effectiveAccountId || null}
                                        onValueChange={
                                            handleSourceAccountChange
                                        }
                                        placeholder="Select account"
                                        searchPlaceholder="Search accounts..."
                                    />
                                    <InputError
                                        message={formErrors.account_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="transaction_date">
                                        Date
                                    </Label>
                                    <DatePicker
                                        id="transaction_date"
                                        name="transaction_date"
                                        value={transactionDate}
                                        onChange={(date) =>
                                            setTransactionDate(date)
                                        }
                                    />
                                    <InputError
                                        message={formErrors.transaction_date}
                                    />
                                </div>
                            </div>

                            {mode === 'transfer' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="to_account_id">
                                        Destination account
                                    </Label>
                                    <input
                                        type="hidden"
                                        name="to_account_id"
                                        value={effectiveToAccountId}
                                    />
                                    <SearchableSelect
                                        options={accounts
                                            .filter(
                                                (a) =>
                                                    String(a.id) !==
                                                    effectiveAccountId,
                                            )
                                            .map((account) => ({
                                                value: String(account.id),
                                                label: account.name,
                                                color: account.color,
                                            }))}
                                        value={effectiveToAccountId || null}
                                        onValueChange={(v) =>
                                            setToAccountId(v ?? '')
                                        }
                                        placeholder="Select destination"
                                        searchPlaceholder="Search accounts..."
                                    />
                                    <InputError
                                        message={formErrors.to_account_id}
                                    />
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between rounded-lg border border-dashed border-border px-4 py-3">
                                        <div>
                                            <Label htmlFor="split-toggle">
                                                Split transaction
                                            </Label>
                                            <p className="text-sm text-muted-foreground">
                                                Break this transaction into
                                                multiple category lines.
                                            </p>
                                        </div>
                                        <Switch
                                            id="split-toggle"
                                            checked={isSplitTransaction}
                                            onCheckedChange={
                                                setIsSplitTransaction
                                            }
                                        />
                                    </div>

                                    {!isSplitTransaction && (
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {/* Category — grouped by parent */}
                                            <div className="grid gap-2">
                                                <Label htmlFor="category_id">
                                                    Category
                                                </Label>
                                                <input
                                                    type="hidden"
                                                    name="category_id"
                                                    value={categoryId}
                                                />
                                                <SearchableSelect
                                                    options={categoryOptions}
                                                    value={categoryId || null}
                                                    onValueChange={(v) =>
                                                        setCategoryId(v ?? '')
                                                    }
                                                    placeholder="No category"
                                                    searchPlaceholder="Search categories..."
                                                    allOption="No category"
                                                />
                                                <InputError
                                                    message={
                                                        formErrors.category_id
                                                    }
                                                />
                                            </div>

                                            {/* Payee — searchable combobox with inline creation */}
                                            <div className="grid gap-2">
                                                <Label>Payee</Label>

                                                <input
                                                    type="hidden"
                                                    name="payee_id"
                                                    value={selectedPayeeId}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="new_payee_name"
                                                    value={
                                                        newPayeeNameForSubmit
                                                    }
                                                />

                                                <SearchableSelect
                                                    options={payees.map(
                                                        (payee) => ({
                                                            value: String(
                                                                payee.id,
                                                            ),
                                                            label: payee.name,
                                                        }),
                                                    )}
                                                    value={
                                                        selectedPayeeId ||
                                                        (newPayeeNameForSubmit
                                                            ? `new:${newPayeeNameForSubmit}`
                                                            : null)
                                                    }
                                                    onValueChange={(v) => {
                                                        setSelectedPayeeId(
                                                            v ?? '',
                                                        );
                                                        setNewPayeeNameForSubmit(
                                                            '',
                                                        );
                                                    }}
                                                    placeholder="No payee"
                                                    searchPlaceholder="Search payees..."
                                                    allOption="No payee"
                                                    creatable
                                                    onCreate={(name) => {
                                                        setSelectedPayeeId('');
                                                        setNewPayeeNameForSubmit(
                                                            name,
                                                        );
                                                    }}
                                                    createLabel={
                                                        newPayeeNameForSubmit
                                                            ? `${newPayeeNameForSubmit} (new)`
                                                            : undefined
                                                    }
                                                />

                                                <InputError
                                                    message={
                                                        formErrors.payee_id ??
                                                        formErrors.new_payee_name
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {isSplitTransaction && (
                                        <div className="space-y-3 rounded-xl border border-border bg-muted/30 p-4">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <Label>Split lines</Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        Total allocated:{' '}
                                                        {splitTotal.toFixed(2)}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={addSplitRow}
                                                >
                                                    Add split
                                                </Button>
                                            </div>

                                            <div className="space-y-3">
                                                {splitRows.map(
                                                    (split, index) => (
                                                        <div
                                                            key={split.id}
                                                            className="space-y-3 rounded-lg border p-3"
                                                        >
                                                            <div className="flex items-center justify-between">
                                                                <span className="text-sm font-medium">
                                                                    Split{' '}
                                                                    {index + 1}
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    disabled={
                                                                        splitRows.length <=
                                                                        2
                                                                    }
                                                                    className="text-muted-foreground hover:text-foreground disabled:opacity-50"
                                                                    onClick={() =>
                                                                        removeSplitRow(
                                                                            split.id,
                                                                        )
                                                                    }
                                                                >
                                                                    <X className="size-4" />
                                                                </button>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-2">
                                                                <div className="grid gap-1">
                                                                    <Label className="text-xs">
                                                                        Amount
                                                                    </Label>
                                                                    <Input
                                                                        type="number"
                                                                        inputMode="decimal"
                                                                        step="0.01"
                                                                        min="0.01"
                                                                        value={
                                                                            split.amount
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const value =
                                                                                e
                                                                                    .target
                                                                                    .value;

                                                                            if (
                                                                                value !==
                                                                                    '' &&
                                                                                Number(
                                                                                    value,
                                                                                ) <
                                                                                    0
                                                                            ) {
                                                                                return;
                                                                            }

                                                                            updateSplitRow(
                                                                                split.id,
                                                                                'amount',
                                                                                value,
                                                                            );
                                                                        }}
                                                                    />
                                                                </div>
                                                                <div className="grid gap-1">
                                                                    <Label className="text-xs">
                                                                        Category
                                                                    </Label>
                                                                    <SearchableSelect
                                                                        options={
                                                                            categoryOptions
                                                                        }
                                                                        value={
                                                                            split.category_id ||
                                                                            null
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateSplitRow(
                                                                                split.id,
                                                                                'category_id',
                                                                                value ??
                                                                                    '',
                                                                            )
                                                                        }
                                                                        placeholder="No category"
                                                                        searchPlaceholder="Search categories..."
                                                                        allOption="No category"
                                                                    />
                                                                </div>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-2">
                                                                <div className="grid gap-1">
                                                                    <Label className="text-xs">
                                                                        Payee
                                                                    </Label>
                                                                    <SearchableSelect
                                                                        options={payees.map(
                                                                            (
                                                                                payee,
                                                                            ) => ({
                                                                                value: String(
                                                                                    payee.id,
                                                                                ),
                                                                                label: payee.name,
                                                                            }),
                                                                        )}
                                                                        value={
                                                                            split.payee_id ||
                                                                            null
                                                                        }
                                                                        onValueChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateSplitRow(
                                                                                split.id,
                                                                                'payee_id',
                                                                                value ??
                                                                                    '',
                                                                            )
                                                                        }
                                                                        placeholder="No payee"
                                                                        searchPlaceholder="Search payees..."
                                                                        allOption="No payee"
                                                                    />
                                                                </div>
                                                                <div className="grid gap-1">
                                                                    <Label className="text-xs">
                                                                        Description
                                                                    </Label>
                                                                    <Input
                                                                        value={
                                                                            split.description
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateSplitRow(
                                                                                split.id,
                                                                                'description',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="Optional split detail"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>

                                            <div className="flex items-center justify-between rounded-lg bg-background px-3 py-2 text-sm">
                                                <span className="text-muted-foreground">
                                                    Remaining to allocate
                                                </span>
                                                <span
                                                    className={cn(
                                                        'font-medium tabular-nums',
                                                        splitRemainder === 0
                                                            ? 'text-green-600'
                                                            : 'text-amber-600',
                                                    )}
                                                >
                                                    {splitRemainder.toFixed(2)}
                                                </span>
                                            </div>
                                            <InputError
                                                message={formErrors.splits}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    value={description}
                                    onChange={(event) =>
                                        setDescription(event.target.value)
                                    }
                                    placeholder="Coffee, salary, or transfer note"
                                />
                                <InputError message={formErrors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Input
                                    id="notes"
                                    name="notes"
                                    value={notes}
                                    onChange={(event) =>
                                        setNotes(event.target.value)
                                    }
                                    placeholder="Optional details"
                                />
                                <InputError message={formErrors.notes} />
                            </div>

                            {/* Attachments */}
                            <div className="grid gap-2">
                                <Label>Attachments</Label>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"
                                    className="hidden"
                                    onChange={(e) => {
                                        const newFiles = Array.from(
                                            e.target.files ?? [],
                                        );
                                        setPendingFiles((prev) => [
                                            ...prev,
                                            ...newFiles,
                                        ]);
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit gap-1.5"
                                    onClick={() => {
                                        if (fileInputRef.current) {
                                            fileInputRef.current.value = '';
                                        }

                                        fileInputRef.current?.click();
                                    }}
                                >
                                    <Paperclip className="size-3.5" />
                                    Attach files
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    Max 5 MB per file. PDF, JPG, PNG, GIF, WebP
                                    accepted.
                                </p>
                                {pendingFiles.length > 0 && (
                                    <div className="space-y-1.5">
                                        {pendingFiles.map((file, index) => (
                                            <div
                                                key={`${file.name}-${index}`}
                                                className="flex items-center justify-between rounded-lg border border-border px-3 py-1.5 text-sm"
                                            >
                                                <span className="min-w-0 truncate">
                                                    {file.name}{' '}
                                                    <span className="text-muted-foreground">
                                                        (
                                                        {(
                                                            file.size / 1024
                                                        ).toFixed(0)}{' '}
                                                        KB)
                                                    </span>
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="ml-2 size-6 shrink-0 p-0"
                                                    onClick={() => {
                                                        setPendingFiles(
                                                            (prev) =>
                                                                prev.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        index,
                                                                ),
                                                        );
                                                    }}
                                                >
                                                    <X className="size-3.5" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <InputError
                                    message={formErrors['attachments']}
                                />
                                <InputError
                                    message={formErrors['attachments.0']}
                                />
                            </div>

                            {tags.length > 0 && (
                                <div className="grid gap-2">
                                    <Label>Tags</Label>
                                    {selectedTagIds.map((id) => (
                                        <input
                                            key={id}
                                            type="hidden"
                                            name="tag_ids[]"
                                            value={id}
                                        />
                                    ))}
                                    <div className="flex flex-wrap gap-2">
                                        {tags.map((tag) => (
                                            <label
                                                key={tag.id}
                                                className="flex cursor-pointer items-center gap-1.5"
                                            >
                                                <Checkbox
                                                    checked={selectedTagIds.includes(
                                                        tag.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedTagIds(
                                                            (prev) =>
                                                                checked === true
                                                                    ? [
                                                                          ...prev,
                                                                          tag.id,
                                                                      ]
                                                                    : prev.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              tag.id,
                                                                      ),
                                                        )
                                                    }
                                                />
                                                <TagPill tag={tag} />
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <Switch
                                    id="rapid-entry"
                                    checked={rapidEntry}
                                    onCheckedChange={(checked) => {
                                        setRapidEntry(checked);
                                        localStorage.setItem(
                                            'rapid-entry',
                                            String(checked),
                                        );
                                    }}
                                />
                                <Label
                                    htmlFor="rapid-entry"
                                    className="cursor-pointer text-sm text-muted-foreground"
                                >
                                    Keep open for rapid entry
                                </Label>
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save transaction
                            </Button>
                        </>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
