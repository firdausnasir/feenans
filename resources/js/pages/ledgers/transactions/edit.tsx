import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import attachmentRoutes from '@/routes/ledgers/transactions/attachments';
import {
    destroy,
    edit as transactionEdit,
    index as transactionsIndex,
    update,
} from '@/routes/ledgers/transactions';
import type {
    Account,
    Attachment,
    BreadcrumbItem,
    Category,
    Ledger,
    Payee,
    Tag,
    Transaction,
    TransactionSplit,
} from '@/types';

type EditFormData = {
    transaction_type: 'expense' | 'income' | 'transfer';
    transaction_date: string;
    account_id: string;
    to_account_id: string;
    category_id: string;
    payee_id: string;
    amount: string;
    description: string;
    notes: string;
    tag_ids: number[];
};

type EditableSplit = {
    id: string;
    amount: string;
    category_id: string;
    description: string;
};

function createEditableSplit(
    split?: TransactionSplit,
    index = 0,
): EditableSplit {
    return {
        id: split ? String(split.id) : `new-${index}`,
        amount: split?.amount ?? '',
        category_id: split?.category_id ? String(split.category_id) : '',
        description: split?.description ?? '',
    };
}

function transactionCategoryOptions(
    categories: Category[],
    transactionType: EditFormData['transaction_type'],
) {
    return categories
        .filter((category) => category.transaction_type === transactionType)
        .flatMap((parent) => [parent, ...(parent.children ?? [])])
        .map((category) => ({
            value: String(category.id),
            label: category.parent_id
                ? `${categories.find((item) => item.id === category.parent_id)?.name} > ${category.name}`
                : category.name,
        }));
}

export default function TransactionEdit({
    ledger,
    transaction,
    accounts,
    categories,
    payees,
    tags,
}: {
    ledger: Ledger;
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
        {
            title: 'Edit Transaction',
            href: transactionEdit([ledger.id, transaction.id]),
        },
    ];

    const [form, setForm] = useState<EditFormData>({
        transaction_type: transaction.transaction_type,
        transaction_date: transaction.transaction_date.slice(0, 10),
        account_id: String(transaction.account_id),
        to_account_id: transaction.transfer_pair
            ? String(transaction.transfer_pair.account_id)
            : '',
        category_id: transaction.category_id
            ? String(transaction.category_id)
            : '',
        payee_id: transaction.payee_id ? String(transaction.payee_id) : '',
        amount: transaction.amount,
        description: transaction.description ?? '',
        notes: transaction.notes ?? '',
        tag_ids: (transaction.tags ?? []).map((tag) => tag.id),
    });
    const [attachments, setAttachments] = useState<Attachment[]>(
        transaction.attachments ?? [],
    );
    const [attachmentError, setAttachmentError] = useState<string | null>(null);
    const [uploadingAttachments, setUploadingAttachments] = useState(false);
    const [isSplitTransaction, setIsSplitTransaction] = useState(
        (transaction.splits ?? []).length > 0,
    );
    const [splits, setSplits] = useState<EditableSplit[]>(
        (transaction.splits ?? []).length > 0
            ? (transaction.splits ?? []).map((split, index) =>
                  createEditableSplit(split, index),
              )
            : [
                  createEditableSplit(undefined, 0),
                  createEditableSplit(undefined, 1),
              ],
    );
    const [processing, setProcessing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const splitOptions = transactionCategoryOptions(
        categories,
        form.transaction_type,
    );
    const splitTotal = splits.reduce(
        (total, split) => total + (Number(split.amount || 0) || 0),
        0,
    );

    async function uploadFiles(files: FileList | null) {
        if (!files || files.length === 0) {
            return;
        }

        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        setUploadingAttachments(true);
        setAttachmentError(null);

        try {
            for (const file of Array.from(files)) {
                const formData = new FormData();
                formData.append('file', file);

                const response = await fetch(
                    attachmentRoutes.store.url({
                        ledger: ledger.id,
                        transaction: transaction.id,
                    }),
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            Accept: 'application/json',
                        },
                        body: formData,
                    },
                );

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    setAttachmentError(
                        payload?.errors?.file?.[0] ??
                            payload?.message ??
                            'Attachment upload failed.',
                    );
                    break;
                }

                if (payload?.attachment) {
                    setAttachments((current) => [
                        ...current,
                        payload.attachment as Attachment,
                    ]);
                }
            }
        } finally {
            setUploadingAttachments(false);
        }
    }

    async function deleteAttachment(attachmentId: number) {
        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        const response = await fetch(
            attachmentRoutes.destroy.url({
                ledger: ledger.id,
                transaction: transaction.id,
                attachment: attachmentId,
            }),
            {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            },
        );

        if (response.ok) {
            setAttachments((current) =>
                current.filter((attachment) => attachment.id !== attachmentId),
            );
        }
    }

    function addSplit() {
        setSplits((current) => [
            ...current,
            createEditableSplit(undefined, current.length),
        ]);
    }

    function updateSplit(
        id: string,
        key: keyof Omit<EditableSplit, 'id'>,
        value: string,
    ) {
        setSplits((current) =>
            current.map((split) =>
                split.id === id ? { ...split, [key]: value } : split,
            ),
        );
    }

    function removeSplit(id: string) {
        setSplits((current) => current.filter((split) => split.id !== id));
    }

    function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setProcessing(true);

        router.put(
            update.url({ ledger: ledger.id, transaction: transaction.id }),
            {
                transaction_type: form.transaction_type,
                transaction_date: form.transaction_date,
                account_id: form.account_id,
                ...(form.transaction_type === 'transfer'
                    ? { to_account_id: form.to_account_id }
                    : {
                          category_id: form.category_id || null,
                          payee_id: form.payee_id || null,
                          splits: isSplitTransaction
                              ? splits.map((split) => ({
                                    amount: split.amount || null,
                                    category_id: split.category_id || null,
                                    description: split.description || null,
                                }))
                              : null,
                      }),
                amount: form.amount,
                description: form.description || null,
                notes: form.notes || null,
                tag_ids: form.tag_ids,
            },
            {
                onSuccess: () => {
                    toast.success('Transaction updated');
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    function handleDelete() {
        setDeleting(true);

        router.delete(
            destroy.url({ ledger: ledger.id, transaction: transaction.id }),
            {
                onSuccess: () => {
                    toast.success('Transaction deleted');
                },
                onFinish: () => setDeleting(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Transaction" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Edit Transaction
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Update details, split allocations, and attachments
                            in one place.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            {transaction.transaction_type}
                        </Badge>
                        {(attachments.length > 0 ||
                            (transaction.splits ?? []).length > 0) && (
                            <Badge variant="secondary">
                                {attachments.length} files /{' '}
                                {(transaction.splits ?? []).length} splits
                            </Badge>
                        )}
                    </div>
                </div>

                <form
                    className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_360px]"
                    onSubmit={handleSubmit}
                >
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Transaction Details</CardTitle>
                                <CardDescription>
                                    Maintain the main transaction record and
                                    allocation rules.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label>Type</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {(
                                            [
                                                'expense',
                                                'income',
                                                'transfer',
                                            ] as const
                                        ).map((type) => (
                                            <Button
                                                key={type}
                                                type="button"
                                                variant={
                                                    form.transaction_type ===
                                                    type
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    setForm((current) => ({
                                                        ...current,
                                                        transaction_type: type,
                                                        category_id:
                                                            type === 'transfer'
                                                                ? ''
                                                                : current.category_id,
                                                        payee_id:
                                                            type === 'transfer'
                                                                ? ''
                                                                : current.payee_id,
                                                    }))
                                                }
                                            >
                                                {type}
                                            </Button>
                                        ))}
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Date</Label>
                                        <Input
                                            type="date"
                                            value={form.transaction_date}
                                            onChange={(event) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    transaction_date:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label>Amount</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={form.amount}
                                            onChange={(event) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    amount: event.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Account</Label>
                                    <SearchableSelect
                                        options={accounts.map((account) => ({
                                            value: String(account.id),
                                            label: account.name,
                                        }))}
                                        value={form.account_id}
                                        onValueChange={(value) =>
                                            setForm((current) => ({
                                                ...current,
                                                account_id: value ?? '',
                                            }))
                                        }
                                        placeholder="Select account"
                                        searchPlaceholder="Search accounts..."
                                    />
                                </div>

                                {form.transaction_type === 'transfer' ? (
                                    <div className="grid gap-2">
                                        <Label>To Account</Label>
                                        <SearchableSelect
                                            options={accounts
                                                .filter(
                                                    (account) =>
                                                        String(account.id) !==
                                                        form.account_id,
                                                )
                                                .map((account) => ({
                                                    value: String(account.id),
                                                    label: account.name,
                                                }))}
                                            value={form.to_account_id || null}
                                            onValueChange={(value) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    to_account_id: value ?? '',
                                                }))
                                            }
                                            placeholder="Select account"
                                            searchPlaceholder="Search accounts..."
                                        />
                                    </div>
                                ) : (
                                    <>
                                        <div className="flex items-center justify-between rounded-lg border border-dashed border-border px-4 py-3">
                                            <div>
                                                <Label htmlFor="page-split-toggle">
                                                    Split transaction
                                                </Label>
                                                <p className="text-sm text-muted-foreground">
                                                    Break the total into
                                                    multiple category lines.
                                                </p>
                                            </div>
                                            <Switch
                                                id="page-split-toggle"
                                                checked={isSplitTransaction}
                                                onCheckedChange={
                                                    setIsSplitTransaction
                                                }
                                            />
                                        </div>

                                        {!isSplitTransaction && (
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label>Category</Label>
                                                    <SearchableSelect
                                                        options={splitOptions}
                                                        value={
                                                            form.category_id ||
                                                            null
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    category_id:
                                                                        value ??
                                                                        '',
                                                                }),
                                                            )
                                                        }
                                                        placeholder="No category"
                                                        searchPlaceholder="Search categories..."
                                                        allOption="No category"
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label>Payee</Label>
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
                                                            form.payee_id ||
                                                            null
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    payee_id:
                                                                        value ??
                                                                        '',
                                                                }),
                                                            )
                                                        }
                                                        placeholder="No payee"
                                                        searchPlaceholder="Search payees..."
                                                        allOption="No payee"
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
                                                            Allocated total:{' '}
                                                            {splitTotal.toFixed(
                                                                2,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={addSplit}
                                                    >
                                                        Add split
                                                    </Button>
                                                </div>

                                                {splits.map((split) => (
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
                                                                    event,
                                                                ) =>
                                                                    updateSplit(
                                                                        split.id,
                                                                        'amount',
                                                                        event
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
                                                            <SearchableSelect
                                                                options={
                                                                    splitOptions
                                                                }
                                                                value={
                                                                    split.category_id ||
                                                                    null
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    updateSplit(
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

                                                        <div className="grid gap-2">
                                                            <Label>
                                                                Description
                                                            </Label>
                                                            <Input
                                                                value={
                                                                    split.description
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateSplit(
                                                                        split.id,
                                                                        'description',
                                                                        event
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
                                                                    splits.length <=
                                                                    2
                                                                }
                                                                onClick={() =>
                                                                    removeSplit(
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
                                        )}
                                    </>
                                )}

                                <div className="grid gap-2">
                                    <Label>Description</Label>
                                    <Input
                                        value={form.description}
                                        onChange={(event) =>
                                            setForm((current) => ({
                                                ...current,
                                                description: event.target.value,
                                            }))
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Notes</Label>
                                    <Input
                                        value={form.notes}
                                        onChange={(event) =>
                                            setForm((current) => ({
                                                ...current,
                                                notes: event.target.value,
                                            }))
                                        }
                                    />
                                </div>

                                {tags.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label>Tags</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {tags.map((tag) => (
                                                <label
                                                    key={tag.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded-full border border-border px-3 py-1 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={form.tag_ids.includes(
                                                            tag.id,
                                                        )}
                                                        onChange={(event) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    tag_ids:
                                                                        event
                                                                            .target
                                                                            .checked
                                                                            ? [
                                                                                  ...current.tag_ids,
                                                                                  tag.id,
                                                                              ]
                                                                            : current.tag_ids.filter(
                                                                                  (
                                                                                      id,
                                                                                  ) =>
                                                                                      id !==
                                                                                      tag.id,
                                                                              ),
                                                                }),
                                                            )
                                                        }
                                                    />
                                                    <span>{tag.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Attachments</CardTitle>
                                <CardDescription>
                                    Store supporting files directly with the
                                    transaction.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Input
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv"
                                    onChange={(event) =>
                                        uploadFiles(event.target.files)
                                    }
                                />

                                {attachmentError && (
                                    <p className="text-sm text-destructive">
                                        {attachmentError}
                                    </p>
                                )}

                                <div className="space-y-2">
                                    {attachments.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No attachments yet.
                                        </p>
                                    ) : (
                                        attachments.map((attachment) => (
                                            <div
                                                key={attachment.id}
                                                className="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                                            >
                                                <a
                                                    href={attachment.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="truncate text-sm font-medium hover:underline"
                                                >
                                                    {attachment.filename}
                                                </a>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        deleteAttachment(
                                                            attachment.id,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        ))
                                    )}
                                </div>

                                {uploadingAttachments && (
                                    <p className="text-sm text-muted-foreground">
                                        Uploading attachment...
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {(transaction.splits ?? []).length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Current Split Snapshot
                                    </CardTitle>
                                    <CardDescription>
                                        Existing saved split lines before your
                                        next update.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {(transaction.splits ?? []).map((split) => (
                                        <div
                                            key={split.id}
                                            className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm"
                                        >
                                            <span>
                                                {split.description ??
                                                    split.category?.name ??
                                                    'Uncategorized'}
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatAbsAmount(split.amount)}
                                            </span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Actions</CardTitle>
                                <CardDescription>
                                    Save your changes or remove the transaction
                                    entirely.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <a href={transactionsIndex.url(ledger.id)}>
                                        Back to transactions
                                    </a>
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={deleting}
                                    onClick={handleDelete}
                                >
                                    Delete transaction
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
