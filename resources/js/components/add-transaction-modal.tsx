import { Form, Link, router } from '@inertiajs/react';
import { Check, ChevronsUpDown, CreditCard, PlusCircle } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import PayeeController from '@/actions/App/Http/Controllers/Ledger/PayeeController';
import TransactionController from '@/actions/App/Http/Controllers/Ledger/TransactionController';
import { create as accountsCreate } from '@/routes/ledgers/accounts';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { TagPill } from '@/components/tag-pill';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import type { Account, Category, Ledger, Payee, Tag } from '@/types';

type TransactionMode = 'expense' | 'income' | 'transfer';
type SplitDraft = {
    id: number;
    amount: string;
    category_id: string;
    description: string;
};

const NEW_PAYEE_SENTINEL = '__new__';

export function AddTransactionModal({
    ledger,
    accounts,
    categories,
    payees: initialPayees,
    tags,
}: {
    ledger: Ledger;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
}) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<TransactionMode>('expense');
    const [accountId, setAccountId] = useState<string>(
        accounts.length > 0 ? String(accounts[0].id) : '',
    );
    const [toAccountId, setToAccountId] = useState<string>(
        accounts.length > 1 ? String(accounts[1].id) : '',
    );
    const [categoryId, setCategoryId] = useState<string>('none');
    const [payees, setPayees] = useState<Payee[]>(initialPayees);
    const [selectedPayeeId, setSelectedPayeeId] = useState<string>('');
    const [payeePopoverOpen, setPayeePopoverOpen] = useState(false);
    const [showNewPayeeInput, setShowNewPayeeInput] = useState(false);
    const [newPayeeName, setNewPayeeName] = useState('');
    const [creatingPayee, setCreatingPayee] = useState(false);
    const [selectedTagIds, setSelectedTagIds] = useState<number[]>([]);
    const [isSplitTransaction, setIsSplitTransaction] = useState(false);
    const [amount, setAmount] = useState('');
    const [splitRows, setSplitRows] = useState<SplitDraft[]>([
        { id: 1, amount: '', category_id: '', description: '' },
        { id: 2, amount: '', category_id: '', description: '' },
    ]);
    const newPayeeInputRef = useRef<HTMLInputElement>(null);

    const splitRowId = useRef(3);

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

    const selectedPayeeLabel = useMemo(() => {
        if (!selectedPayeeId) {
            return 'Select payee...';
        }

        const found = payees.find((p) => String(p.id) === selectedPayeeId);

        return found ? found.name : 'Select payee...';
    }, [payees, selectedPayeeId]);

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

    function handlePayeeSelect(value: string) {
        if (value === NEW_PAYEE_SENTINEL) {
            setShowNewPayeeInput(true);
            setSelectedPayeeId('');
            setPayeePopoverOpen(false);
            setTimeout(() => newPayeeInputRef.current?.focus(), 0);
        } else {
            setShowNewPayeeInput(false);
            setSelectedPayeeId(value === selectedPayeeId ? '' : value);
            setPayeePopoverOpen(false);
        }
    }

    function confirmNewPayee() {
        const name = newPayeeName.trim();

        if (!name || creatingPayee) {
            return;
        }

        setCreatingPayee(true);

        router.post(
            PayeeController.store.url(ledger.id),
            { name },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    // The new payee list comes back via shared/page props — but since
                    // this is a quick AJAX-style operation we optimistically add it.
                    // If the page refreshes the prop, it will be up-to-date already.
                    const allPayees = (page.props as { payees?: Payee[] })
                        .payees;

                    if (allPayees) {
                        setPayees(allPayees);
                        const created = allPayees.find((p) => p.name === name);

                        if (created) {
                            setSelectedPayeeId(String(created.id));
                        }
                    } else {
                        // Optimistic: create a temporary entry with a negative id
                        const tempId = Date.now();
                        const newPayee: Payee = {
                            id: tempId,
                            ledger_id: ledger.id,
                            name,
                        };
                        setPayees((prev) => [...prev, newPayee]);
                        setSelectedPayeeId(String(tempId));
                    }

                    setShowNewPayeeInput(false);
                    setNewPayeeName('');
                    setCreatingPayee(false);
                },
                onError: () => {
                    setCreatingPayee(false);
                },
            },
        );
    }

    function addSplitRow() {
        setSplitRows((prev) => [
            ...prev,
            {
                id: splitRowId.current++,
                amount: '',
                category_id: '',
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
        setAccountId(accounts.length > 0 ? String(accounts[0].id) : '');
        setToAccountId(accounts.length > 1 ? String(accounts[1].id) : '');
        setCategoryId('none');
        setSelectedPayeeId('');
        setShowNewPayeeInput(false);
        setNewPayeeName('');
        setSelectedTagIds([]);
        setIsSplitTransaction(false);
        setAmount('');
        setSplitRows([
            { id: 1, amount: '', category_id: '', description: '' },
            { id: 2, amount: '', category_id: '', description: '' },
        ]);
        splitRowId.current = 3;
    }

    function handleSuccess() {
        toast.success('Transaction saved');
        setOpen(false);
        resetForm();
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="gap-2">
                    <PlusCircle className="size-4" />
                    Add transaction
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add transaction</DialogTitle>
                    <DialogDescription>
                        Quickly log income, expenses, or transfers without
                        leaving the ledger.
                    </DialogDescription>
                </DialogHeader>

                {accounts.length === 0 ? (
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
                            <Link href={accountsCreate.url(ledger.id)}>
                                Create your first account
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <Form
                        {...TransactionController.store.form(ledger.id)}
                        className="space-y-6"
                        onSuccess={handleSuccess}
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="flex gap-2">
                                    {(
                                        [
                                            'expense',
                                            'income',
                                            'transfer',
                                        ] as TransactionMode[]
                                    ).map((value) => (
                                        <Button
                                            key={value}
                                            type="button"
                                            variant={
                                                mode === value
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() => setMode(value)}
                                        >
                                            {value}
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
                                                  name={`splits[${index}][description]`}
                                                  value={split.description}
                                              />
                                          </div>
                                      ))
                                    : null}

                                <div className="grid gap-2">
                                    <Label htmlFor="amount">Amount</Label>
                                    <Input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        autoFocus
                                        required
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(event.target.value)
                                        }
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="account_id">
                                            Account
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="account_id"
                                            value={accountId}
                                        />
                                        <Select
                                            value={accountId}
                                            onValueChange={setAccountId}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select account" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {accounts.map((account) => (
                                                    <SelectItem
                                                        key={account.id}
                                                        value={String(
                                                            account.id,
                                                        )}
                                                    >
                                                        {account.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.account_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="transaction_date">
                                            Date
                                        </Label>
                                        <Input
                                            id="transaction_date"
                                            name="transaction_date"
                                            type="date"
                                            defaultValue={new Date()
                                                .toISOString()
                                                .slice(0, 10)}
                                            required
                                        />
                                        <InputError
                                            message={errors.transaction_date}
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
                                            value={toAccountId}
                                        />
                                        <Select
                                            value={toAccountId}
                                            onValueChange={setToAccountId}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select destination" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {accounts
                                                    .filter(
                                                        (a) =>
                                                            String(a.id) !==
                                                            accountId,
                                                    )
                                                    .map((account) => (
                                                        <SelectItem
                                                            key={account.id}
                                                            value={String(
                                                                account.id,
                                                            )}
                                                        >
                                                            {account.name}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.to_account_id}
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
                                                        value={
                                                            categoryId ===
                                                            'none'
                                                                ? ''
                                                                : categoryId
                                                        }
                                                    />
                                                    <Select
                                                        value={categoryId}
                                                        onValueChange={
                                                            setCategoryId
                                                        }
                                                    >
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="Select category" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">
                                                                No category
                                                            </SelectItem>
                                                            {groupedCategories.map(
                                                                ({
                                                                    parent,
                                                                    children,
                                                                }) =>
                                                                    children.length >
                                                                    0 ? (
                                                                        <SelectGroup
                                                                            key={
                                                                                parent.id
                                                                            }
                                                                        >
                                                                            <SelectLabel>
                                                                                {
                                                                                    parent.name
                                                                                }
                                                                            </SelectLabel>
                                                                            <SelectItem
                                                                                value={String(
                                                                                    parent.id,
                                                                                )}
                                                                            >
                                                                                {
                                                                                    parent.name
                                                                                }{' '}
                                                                                (general)
                                                                            </SelectItem>
                                                                            {children.map(
                                                                                (
                                                                                    child,
                                                                                ) => (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            child.id
                                                                                        }
                                                                                        value={String(
                                                                                            child.id,
                                                                                        )}
                                                                                    >
                                                                                        {
                                                                                            child.name
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                            )}
                                                                        </SelectGroup>
                                                                    ) : (
                                                                        <SelectItem
                                                                            key={
                                                                                parent.id
                                                                            }
                                                                            value={String(
                                                                                parent.id,
                                                                            )}
                                                                        >
                                                                            {
                                                                                parent.name
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            errors.category_id
                                                        }
                                                    />
                                                </div>

                                                {/* Payee — searchable combobox with inline creation */}
                                                <div className="grid gap-2">
                                                    <Label>Payee</Label>

                                                    {/* Always-present hidden input so payee_id is always in the form data */}
                                                    <input
                                                        type="hidden"
                                                        name="payee_id"
                                                        value={
                                                            showNewPayeeInput
                                                                ? ''
                                                                : selectedPayeeId
                                                        }
                                                    />

                                                    <Popover
                                                        open={payeePopoverOpen}
                                                        onOpenChange={
                                                            setPayeePopoverOpen
                                                        }
                                                    >
                                                        <PopoverTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                role="combobox"
                                                                aria-expanded={
                                                                    payeePopoverOpen
                                                                }
                                                                className={cn(
                                                                    'w-full justify-between font-normal',
                                                                    !selectedPayeeId &&
                                                                        !showNewPayeeInput &&
                                                                        'text-muted-foreground',
                                                                )}
                                                            >
                                                                {showNewPayeeInput
                                                                    ? 'Creating new payee...'
                                                                    : selectedPayeeLabel}
                                                                <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            className="w-[--radix-popover-trigger-width] p-0"
                                                            align="start"
                                                        >
                                                            <Command>
                                                                <CommandInput placeholder="Search payees..." />
                                                                <CommandList>
                                                                    <CommandEmpty>
                                                                        No payee
                                                                        found.
                                                                    </CommandEmpty>
                                                                    <CommandGroup>
                                                                        <CommandItem
                                                                            value=""
                                                                            onSelect={() =>
                                                                                handlePayeeSelect(
                                                                                    '',
                                                                                )
                                                                            }
                                                                        >
                                                                            <Check
                                                                                className={cn(
                                                                                    'mr-2 size-4',
                                                                                    selectedPayeeId ===
                                                                                        ''
                                                                                        ? 'opacity-100'
                                                                                        : 'opacity-0',
                                                                                )}
                                                                            />
                                                                            No
                                                                            payee
                                                                        </CommandItem>
                                                                        {payees.map(
                                                                            (
                                                                                payee,
                                                                            ) => (
                                                                                <CommandItem
                                                                                    key={
                                                                                        payee.id
                                                                                    }
                                                                                    value={
                                                                                        payee.name
                                                                                    }
                                                                                    onSelect={() =>
                                                                                        handlePayeeSelect(
                                                                                            String(
                                                                                                payee.id,
                                                                                            ),
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Check
                                                                                        className={cn(
                                                                                            'mr-2 size-4',
                                                                                            selectedPayeeId ===
                                                                                                String(
                                                                                                    payee.id,
                                                                                                )
                                                                                                ? 'opacity-100'
                                                                                                : 'opacity-0',
                                                                                        )}
                                                                                    />
                                                                                    {
                                                                                        payee.name
                                                                                    }
                                                                                </CommandItem>
                                                                            ),
                                                                        )}
                                                                    </CommandGroup>
                                                                    <CommandSeparator />
                                                                    <CommandGroup>
                                                                        <CommandItem
                                                                            value={
                                                                                NEW_PAYEE_SENTINEL
                                                                            }
                                                                            onSelect={() =>
                                                                                handlePayeeSelect(
                                                                                    NEW_PAYEE_SENTINEL,
                                                                                )
                                                                            }
                                                                        >
                                                                            <PlusCircle className="mr-2 size-4" />
                                                                            Create
                                                                            new
                                                                            payee
                                                                        </CommandItem>
                                                                    </CommandGroup>
                                                                </CommandList>
                                                            </Command>
                                                        </PopoverContent>
                                                    </Popover>

                                                    {showNewPayeeInput && (
                                                        <div className="flex gap-2">
                                                            <Input
                                                                ref={
                                                                    newPayeeInputRef
                                                                }
                                                                placeholder="New payee name"
                                                                value={
                                                                    newPayeeName
                                                                }
                                                                onChange={(e) =>
                                                                    setNewPayeeName(
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                onKeyDown={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        e.key ===
                                                                        'Enter'
                                                                    ) {
                                                                        e.preventDefault();
                                                                        confirmNewPayee();
                                                                    }

                                                                    if (
                                                                        e.key ===
                                                                        'Escape'
                                                                    ) {
                                                                        setShowNewPayeeInput(
                                                                            false,
                                                                        );
                                                                        setNewPayeeName(
                                                                            '',
                                                                        );
                                                                    }
                                                                }}
                                                                className="flex-1"
                                                            />
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                disabled={
                                                                    creatingPayee ||
                                                                    !newPayeeName.trim()
                                                                }
                                                                onClick={
                                                                    confirmNewPayee
                                                                }
                                                            >
                                                                <Check className="size-4" />
                                                            </Button>
                                                        </div>
                                                    )}

                                                    <InputError
                                                        message={
                                                            errors.payee_id
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        )}

                                        {isSplitTransaction && (
                                            <div className="space-y-3 rounded-xl border border-border bg-muted/30 p-4">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <Label>
                                                            Split lines
                                                        </Label>
                                                        <p className="text-sm text-muted-foreground">
                                                            Total allocated:{' '}
                                                            {splitTotal.toFixed(
                                                                2,
                                                            )}
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
                                                    {splitRows.map((split) => (
                                                        <div
                                                            key={split.id}
                                                            className="grid gap-3 rounded-lg border border-border bg-background p-3 md:grid-cols-[120px_1fr_1fr_auto]"
                                                        >
                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Amount
                                                                </Label>
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0.01"
                                                                    value={
                                                                        split.amount
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateSplitRow(
                                                                            split.id,
                                                                            'amount',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label>
                                                                    Category
                                                                </Label>
                                                                <Select
                                                                    value={
                                                                        split.category_id ||
                                                                        'none'
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateSplitRow(
                                                                            split.id,
                                                                            'category_id',
                                                                            value ===
                                                                                'none'
                                                                                ? ''
                                                                                : value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-full">
                                                                        <SelectValue placeholder="No category" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        <SelectItem value="none">
                                                                            No
                                                                            category
                                                                        </SelectItem>
                                                                        {groupedCategories.map(
                                                                            ({
                                                                                parent,
                                                                                children,
                                                                            }) =>
                                                                                children.length >
                                                                                0 ? (
                                                                                    <SelectGroup
                                                                                        key={
                                                                                            parent.id
                                                                                        }
                                                                                    >
                                                                                        <SelectLabel>
                                                                                            {
                                                                                                parent.name
                                                                                            }
                                                                                        </SelectLabel>
                                                                                        <SelectItem
                                                                                            value={String(
                                                                                                parent.id,
                                                                                            )}
                                                                                        >
                                                                                            {
                                                                                                parent.name
                                                                                            }
                                                                                        </SelectItem>
                                                                                        {children.map(
                                                                                            (
                                                                                                child,
                                                                                            ) => (
                                                                                                <SelectItem
                                                                                                    key={
                                                                                                        child.id
                                                                                                    }
                                                                                                    value={String(
                                                                                                        child.id,
                                                                                                    )}
                                                                                                >
                                                                                                    {
                                                                                                        child.name
                                                                                                    }
                                                                                                </SelectItem>
                                                                                            ),
                                                                                        )}
                                                                                    </SelectGroup>
                                                                                ) : (
                                                                                    <SelectItem
                                                                                        key={
                                                                                            parent.id
                                                                                        }
                                                                                        value={String(
                                                                                            parent.id,
                                                                                        )}
                                                                                    >
                                                                                        {
                                                                                            parent.name
                                                                                        }
                                                                                    </SelectItem>
                                                                                ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </div>

                                                            <div className="grid gap-2">
                                                                <Label>
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

                                                            <div className="flex items-end justify-end">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="ghost"
                                                                    disabled={
                                                                        splitRows.length <=
                                                                        2
                                                                    }
                                                                    onClick={() =>
                                                                        removeSplitRow(
                                                                            split.id,
                                                                        )
                                                                    }
                                                                >
                                                                    Remove
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ))}
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
                                                        {splitRemainder.toFixed(
                                                            2,
                                                        )}
                                                    </span>
                                                </div>
                                                <InputError
                                                    message={errors.splits}
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        name="description"
                                        placeholder="Coffee, salary, or transfer note"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Input
                                        id="notes"
                                        name="notes"
                                        placeholder="Optional details"
                                    />
                                    <InputError message={errors.notes} />
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
                                                                    checked ===
                                                                    true
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

                                <Button
                                    disabled={processing || showNewPayeeInput}
                                    title={
                                        showNewPayeeInput
                                            ? 'Confirm new payee first'
                                            : undefined
                                    }
                                >
                                    {showNewPayeeInput
                                        ? 'Confirm new payee first'
                                        : 'Save transaction'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
