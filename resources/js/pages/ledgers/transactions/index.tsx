import type { DuplicateData } from '@/components/add-transaction-modal';
import { AddTransactionModal } from '@/components/add-transaction-modal';
import Heading from '@/components/heading';
import { SearchableSelect } from '@/components/searchable-select';
import { TagPill } from '@/components/tag-pill';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount, formatAmount, formatDate } from '@/lib/format';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    bulkDestroy,
    bulkUpdate,
    destroy,
    exportMethod as exportTransactions,
    restore,
    index as transactionsIndex,
    update,
} from '@/routes/ledgers/transactions';
import attachmentRoutes from '@/routes/ledgers/transactions/attachments';
import type {
    Account,
    Attachment,
    BreadcrumbItem,
    Category,
    Ledger,
    Pagination,
    Payee,
    Tag,
    Transaction,
    TransactionSplit,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    MoreHorizontal,
    Paperclip,
    Receipt,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type Filters = {
    search: string | null;
    date_from: string;
    date_to: string;
    account_ids: string[];
    category_ids: string[];
    transaction_types: string[];
    payee_ids: string[];
    tag_ids: string[];
    bill_id: string | null;
    uncategorized: string | null;
};

function amountColor(transaction: Transaction): string {
    if (transaction.transaction_type === 'transfer') {
        return 'text-blue-500';
    }

    return parseFloat(transaction.amount) >= 0
        ? 'text-green-600'
        : 'text-red-500';
}

function amountPrefix(transaction: Transaction): string {
    if (transaction.transaction_type === 'transfer') {
        return '';
    }

    return parseFloat(transaction.amount) >= 0 ? '+' : '-';
}

const TYPE_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    expense: 'destructive',
    income: 'default',
    transfer: 'outline',
};

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
            color: category.color,
        }));
}

function EditTransactionModal({
    ledger,
    transaction,
    accounts,
    categories,
    payees,
    tags,
    onClose,
}: {
    ledger: Ledger;
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
    onClose: () => void;
}) {
    const isTransfer = transaction.transfer_pair_id !== null;
    const isIncomingSide =
        isTransfer && parseFloat(transaction.amount || '0') > 0;

    const [form, setForm] = useState<EditFormData>({
        transaction_type: transaction.transaction_type as
            | 'expense'
            | 'income'
            | 'transfer',
        transaction_date: transaction.transaction_date.slice(0, 10),
        account_id:
            isIncomingSide && transaction.transfer_pair
                ? String(transaction.transfer_pair.account_id)
                : String(transaction.account_id),
        to_account_id: isIncomingSide
            ? String(transaction.account_id)
            : transaction.transfer_pair
              ? String(transaction.transfer_pair.account_id)
              : '',
        category_id: transaction.category_id
            ? String(transaction.category_id)
            : '',
        payee_id: transaction.payee_id ? String(transaction.payee_id) : '',
        amount: isTransfer
            ? String(Math.abs(parseFloat(transaction.amount || '0')))
            : transaction.amount,
        description: transaction.description ?? '',
        notes: transaction.notes ?? '',
        tag_ids: (transaction.tags ?? []).map((t) => t.id),
    });
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [attachments, setAttachments] = useState<Attachment[]>(
        transaction.attachments ?? [],
    );
    const [uploadingAttachments, setUploadingAttachments] = useState(false);
    const [attachmentError, setAttachmentError] = useState<string | null>(null);
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

    function handleInputChange(
        e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
    ) {
        const { name, value } = e.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
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
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction updated');
                    onClose();
                },
                onError: (errors) => {
                    setProcessing(false);
                    const firstError = Object.values(errors)[0];
                    if (firstError) {
                        toast.error(String(firstError));
                    }
                },
            },
        );
    }

    function handleDelete() {
        setDeleting(true);
        router.delete(
            destroy.url({ ledger: ledger.id, transaction: transaction.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction deleted');
                    onClose();
                },
                onError: (errors) => {
                    setDeleting(false);
                    const firstError = Object.values(errors)[0];
                    if (firstError) {
                        toast.error(String(firstError));
                    }
                },
            },
        );
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Transaction</DialogTitle>
                    <DialogDescription>
                        Update the details of this transaction.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid gap-2">
                        <div className="grid grid-cols-3 gap-1">
                            {(['expense', 'income', 'transfer'] as const).map(
                                (t) => (
                                    <Button
                                        key={t}
                                        type="button"
                                        variant={
                                            form.transaction_type === t
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            setForm((prev) => ({
                                                ...prev,
                                                transaction_type: t,
                                                category_id:
                                                    t === 'transfer'
                                                        ? ''
                                                        : prev.category_id,
                                                payee_id:
                                                    t === 'transfer'
                                                        ? ''
                                                        : prev.payee_id,
                                                to_account_id:
                                                    t === 'transfer'
                                                        ? prev.to_account_id ||
                                                          (accounts.length > 1
                                                              ? String(
                                                                    accounts.find(
                                                                        (a) =>
                                                                            String(
                                                                                a.id,
                                                                            ) !==
                                                                            prev.account_id,
                                                                    )?.id ?? '',
                                                                )
                                                              : '')
                                                        : prev.to_account_id,
                                            }))
                                        }
                                    >
                                        {t.charAt(0).toUpperCase() + t.slice(1)}
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-date">Date</Label>
                            <DatePicker
                                id="edit-date"
                                name="transaction_date"
                                value={form.transaction_date}
                                onChange={(date) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        transaction_date: date,
                                    }))
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="edit-amount">Amount</Label>
                            <Input
                                id="edit-amount"
                                name="amount"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0.01"
                                value={form.amount}
                                onChange={handleInputChange}
                                required
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Account</Label>
                        <SearchableSelect
                            options={accounts.map((a) => ({
                                value: String(a.id),
                                label: a.name,
                                color: a.color,
                            }))}
                            value={form.account_id}
                            onValueChange={(value) =>
                                setForm((prev) => ({
                                    ...prev,
                                    account_id: value ?? '',
                                }))
                            }
                            placeholder="Select account"
                            searchPlaceholder="Search accounts..."
                        />
                    </div>

                    {form.transaction_type !== 'transfer' && (
                        <>
                            <div className="flex items-center justify-between rounded-lg border border-dashed border-border px-4 py-3">
                                <div>
                                    <Label htmlFor={`split-${transaction.id}`}>
                                        Split transaction
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Categorize this transaction across
                                        multiple lines.
                                    </p>
                                </div>
                                <Switch
                                    id={`split-${transaction.id}`}
                                    checked={isSplitTransaction}
                                    onCheckedChange={setIsSplitTransaction}
                                />
                            </div>

                            {!isSplitTransaction && (
                                <div className="grid gap-2">
                                    <Label>Category</Label>
                                    <SearchableSelect
                                        options={splitOptions}
                                        value={form.category_id || null}
                                        onValueChange={(value) =>
                                            setForm((prev) => ({
                                                ...prev,
                                                category_id: value ?? '',
                                            }))
                                        }
                                        placeholder="No category"
                                        searchPlaceholder="Search categories..."
                                        allOption="No category"
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label>Payee</Label>
                                <SearchableSelect
                                    options={payees.map((p) => ({
                                        value: String(p.id),
                                        label: p.name,
                                    }))}
                                    value={form.payee_id || null}
                                    onValueChange={(value) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            payee_id: value ?? '',
                                        }))
                                    }
                                    placeholder="No payee"
                                    searchPlaceholder="Search payees..."
                                    allOption="No payee"
                                />
                            </div>

                            {isSplitTransaction && (
                                <div className="space-y-3 rounded-xl border border-border bg-muted/30 p-4">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <Label>Split lines</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Allocated total:{' '}
                                                {splitTotal.toFixed(2)}
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
                                            className="grid gap-3 rounded-lg border border-border bg-background p-3 sm:grid-cols-[120px_1fr_1fr_auto]"
                                        >
                                            <div className="grid gap-2">
                                                <Label>Amount</Label>
                                                <Input
                                                    type="number"
                                                    inputMode="decimal"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={split.amount}
                                                    onChange={(event) =>
                                                        updateSplit(
                                                            split.id,
                                                            'amount',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label>Category</Label>
                                                <SearchableSelect
                                                    options={splitOptions}
                                                    value={
                                                        split.category_id ||
                                                        null
                                                    }
                                                    onValueChange={(value) =>
                                                        updateSplit(
                                                            split.id,
                                                            'category_id',
                                                            value ?? '',
                                                        )
                                                    }
                                                    placeholder="No category"
                                                    searchPlaceholder="Search categories..."
                                                    allOption="No category"
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label>Description</Label>
                                                <Input
                                                    value={split.description}
                                                    onChange={(event) =>
                                                        updateSplit(
                                                            split.id,
                                                            'description',
                                                            event.target.value,
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
                                                        splits.length <= 2
                                                    }
                                                    onClick={() =>
                                                        removeSplit(split.id)
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

                    {form.transaction_type === 'transfer' && (
                        <div className="grid gap-2">
                            <Label>To Account</Label>
                            <SearchableSelect
                                options={accounts
                                    .filter(
                                        (a) => String(a.id) !== form.account_id,
                                    )
                                    .map((a) => ({
                                        value: String(a.id),
                                        label: a.name,
                                        color: a.color,
                                    }))}
                                value={form.to_account_id || null}
                                onValueChange={(value) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        to_account_id: value ?? '',
                                    }))
                                }
                                placeholder="Select account"
                                searchPlaceholder="Search accounts..."
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="edit-description">Description</Label>
                        <Input
                            id="edit-description"
                            name="description"
                            value={form.description}
                            onChange={handleInputChange}
                            placeholder="Optional description"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="edit-notes">Notes</Label>
                        <Input
                            id="edit-notes"
                            name="notes"
                            value={form.notes}
                            onChange={handleInputChange}
                            placeholder="Optional notes"
                        />
                    </div>

                    {tags.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Tags</Label>
                            <div className="flex flex-wrap gap-2">
                                {tags.map((tag) => (
                                    <label
                                        key={tag.id}
                                        className="flex cursor-pointer items-center gap-1.5"
                                    >
                                        <Checkbox
                                            checked={form.tag_ids.includes(
                                                tag.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    tag_ids:
                                                        checked === true
                                                            ? [
                                                                  ...prev.tag_ids,
                                                                  tag.id,
                                                              ]
                                                            : prev.tag_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      tag.id,
                                                              ),
                                                }))
                                            }
                                        />
                                        <TagPill tag={tag} />
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="grid gap-3 rounded-xl border border-border bg-muted/30 p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <Label
                                    htmlFor={`attachments-${transaction.id}`}
                                >
                                    Attachments
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    Upload receipts and supporting files.
                                </p>
                            </div>
                            <Input
                                id={`attachments-${transaction.id}`}
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv"
                                className="max-w-xs"
                                onChange={(event) =>
                                    uploadFiles(event.target.files)
                                }
                            />
                        </div>

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
                                        className="flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2"
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
                                                deleteAttachment(attachment.id)
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
                    </div>

                    <DialogFooter className="flex-col gap-2 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setShowDeleteConfirm(true)}
                            disabled={processing || deleting}
                        >
                            Delete
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Save changes
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>

            {/* Delete confirmation nested dialog */}
            <Dialog
                open={showDeleteConfirm}
                onOpenChange={(open) => !open && setShowDeleteConfirm(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete transaction</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this transaction?
                            This action cannot be undone.
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
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Dialog>
    );
}

export default function TransactionsIndex({
    ledger,
    transactions,
    accounts,
    categories,
    payees,
    tags,
    filters,
}: {
    ledger: Ledger;
    transactions: Pagination<Transaction>;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    tags: Tag[];
    filters: Filters;
}) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [editTransaction, setEditTransaction] = useState<Transaction | null>(
        null,
    );
    const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false);
    const [bulkAction, setBulkAction] = useState<
        'change_category' | 'change_account' | 'change_payee' | null
    >(null);
    const [bulkActionValue, setBulkActionValue] = useState<string | null>(null);
    const [deleteConfirmTransaction, setDeleteConfirmTransaction] =
        useState<Transaction | null>(null);
    const [duplicateTransaction, setDuplicateTransaction] =
        useState<DuplicateData | null>(null);
    const [showDuplicateModal, setShowDuplicateModal] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    // Track the last search value that triggered a navigation so that syncing
    // `localFilters` from the server props (e.g. after a page change) doesn't
    // accidentally fire a new search and reset the page back to 1.
    const lastNavigatedSearch = useRef<string | null>(filters.search);

    // Only re-run when `localFilters.search` changes — other filters are
    // applied via explicit "Apply" actions, not this debounced effect.
    useEffect(() => {
        // Skip if the search value hasn't actually changed from what was last
        // navigated (covers the case where localFilters is reset from props).
        if (localFilters.search === lastNavigatedSearch.current) {
            return;
        }

        if (searchDebounceRef.current) {
            clearTimeout(searchDebounceRef.current);
        }

        searchDebounceRef.current = setTimeout(() => {
            lastNavigatedSearch.current = localFilters.search;

            const params = Object.fromEntries(
                Object.entries({
                    ...localFilters,
                    search: localFilters.search,
                }).filter(([, v]) => v !== null && v !== ''),
            ) as Record<string, string>;

            router.get(transactionsIndex.url(ledger.id), params, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);

        return () => {
            if (searchDebounceRef.current) {
                clearTimeout(searchDebounceRef.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localFilters.search]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
    ];

    const allVisibleIds = transactions.data.map((t) => t.id);
    const allSelected =
        allVisibleIds.length > 0 &&
        allVisibleIds.every((id) => selectedIds.includes(id));
    const someSelected =
        !allSelected && allVisibleIds.some((id) => selectedIds.includes(id));

    function handleSelectAll(checked: boolean | 'indeterminate') {
        if (checked === true) {
            setSelectedIds((prev) => [...new Set([...prev, ...allVisibleIds])]);
        } else {
            setSelectedIds((prev) =>
                prev.filter((id) => !allVisibleIds.includes(id)),
            );
        }
    }

    function handleSelectOne(id: number, checked: boolean | 'indeterminate') {
        if (checked === true) {
            setSelectedIds((prev) => [...prev, id]);
        } else {
            setSelectedIds((prev) => prev.filter((i) => i !== id));
        }
    }

    function handleApplyFilters() {
        const params: Record<string, string | string[]> = {};

        for (const [key, val] of Object.entries(localFilters)) {
            if (Array.isArray(val)) {
                if (val.length > 0) {
                    params[key] = val;
                }
            } else if (val !== null && val !== '') {
                params[key] = val;
            }
        }

        router.get(transactionsIndex.url(ledger.id), params, {
            preserveState: true,
        });
    }

    function handleResetFilters() {
        router.get(
            transactionsIndex.url(ledger.id),
            {},
            { preserveState: false },
        );
    }

    function handleBulkDelete() {
        router.post(
            bulkDestroy.url(ledger.id),
            { ids: selectedIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    setShowBulkDeleteConfirm(false);
                    toast.success('Transactions deleted');
                },
            },
        );
    }

    function handleBulkUpdate() {
        if (!bulkAction || !bulkActionValue) {
            return;
        }

        router.post(
            bulkUpdate.url(ledger.id),
            {
                ids: selectedIds,
                action: bulkAction,
                value: Number(bulkActionValue),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const actionLabel = {
                        change_category: 'Category',
                        change_account: 'Account',
                        change_payee: 'Payee',
                    }[bulkAction];

                    setSelectedIds([]);
                    setBulkAction(null);
                    setBulkActionValue(null);
                    toast.success(
                        `${actionLabel} updated for selected transactions`,
                    );
                },
            },
        );
    }

    function openBulkActionModal(
        action: 'change_category' | 'change_account' | 'change_payee',
    ) {
        setBulkAction(action);
        setBulkActionValue(null);
    }

    function handleContextDelete() {
        const transaction = deleteConfirmTransaction;

        if (!transaction) {
            return;
        }

        const transactionId = transaction.id;

        setDeleteConfirmTransaction(null);
        router.delete(
            destroy.url({ ledger: ledger.id, transaction: transactionId }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Transaction deleted', {
                        action: {
                            label: 'Undo',
                            onClick: () => {
                                router.patch(
                                    restore.url({
                                        ledger: ledger.id,
                                        transaction: transactionId,
                                    }),
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            toast.success(
                                                'Transaction restored',
                                            ),
                                    },
                                );
                            },
                        },
                        duration: 5000,
                    });
                },
            },
        );
    }

    function handleDuplicate(transaction: Transaction) {
        setDuplicateTransaction({
            transaction_type: transaction.transaction_type,
            account_id: transaction.account_id,
            to_account_id: transaction.transfer_pair
                ? transaction.transfer_pair.account_id
                : null,
            category_id: transaction.category_id,
            payee_id: transaction.payee_id,
            amount: transaction.amount,
            description: transaction.description,
            notes: transaction.notes,
            tag_ids: (transaction.tags ?? []).map((t) => t.id),
        });
        setShowDuplicateModal(true);
    }

    // Flat categories for filter dropdown (all, not grouped)
    const flatCategories = categories.flatMap((parent) => [
        parent,
        ...(parent.children ?? []),
    ]);

    const isAccountFiltered = filters.account_ids.length === 1;
    const filteredAccount = isAccountFiltered
        ? accounts.find((a) => String(a.id) === filters.account_ids[0])
        : null;

    // Compute running balances when a single account is filtered
    const runningBalances = (() => {
        if (!filteredAccount) {
            return null;
        }

        const txns = transactions.data;

        if (txns.length === 0) {
            return null;
        }

        // current_balance reflects the account's balance after all transactions.
        // Transactions are ordered desc (newest first).
        // We walk from the last (oldest on page) to first (newest on page),
        // computing balance after each transaction by starting from
        // (currentBalance - sum of amounts of transactions newer than this page,
        //  i.e. the balance before the first transaction on the page).
        //
        // A simpler approach: balance after last tx on page =
        //   currentBalance - sum(amounts of all txns on pages before this one)
        // But we don't have that info. Instead, use currentBalance and
        // subtract amounts of txns from top to get each row's balance.
        //
        // The balance shown at the newest row = currentBalance (if page 1)
        // or we can compute relative balances. Since we only have the page data,
        // let's compute: balanceAfterRow[0] = currentBalance (only accurate on page 1)
        // For subsequent pages this won't be fully accurate, but it's a reasonable
        // client-side approximation. A proper implementation would need server data.

        const balances = new Map<number, number>();
        const currentBalance = parseFloat(filteredAccount.current_balance);

        // For page 1, the newest transaction's "after" balance = currentBalance
        // For other pages, we approximate by working backwards from current balance
        // minus the sum of all transactions on previous pages (not available).
        // We'll show balances only on page 1 for accuracy, or always show
        // with a note. Let's just compute for all pages using the approach:
        // balance = currentBalance - sum(amounts before this row, across all pages)
        // We only know the current page though, so let's just compute relative.

        // Actually, the simplest correct approach for page 1:
        // Walk top to bottom, balance starts at currentBalance and we subtract each txn
        // But that's wrong because the top txn is the newest.
        // balanceAfterRow[i] = currentBalance - sum(amounts of rows 0..i-1)
        // No - balance after row 0 (newest) = currentBalance
        // balance after row 1 = currentBalance - amount[0] ... no that's also wrong.
        // Let me think:
        // currentBalance = initialBalance + sum(all transactions)
        // balance after txn[i] (0=newest) = currentBalance - sum(txns[0..i-1].amount)
        //   because txns 0..i-1 happen after txn[i]
        // This is only accurate on page 1 since we'd miss txns from other pages.

        if (transactions.current_page === 1) {
            let cumulativeBefore = 0;

            for (const txn of txns) {
                balances.set(txn.id, currentBalance - cumulativeBefore);
                cumulativeBefore += parseFloat(txn.amount);
            }
        } else {
            // For non-first pages, skip showing balance (not accurate without server data)
            return null;
        }

        return balances;
    })();

    const activeFilterLabels = (() => {
        const labels: string[] = [];

        if (localFilters.date_from || localFilters.date_to) {
            const from = localFilters.date_from
                ? formatDate(localFilters.date_from)
                : 'Start';
            const to = localFilters.date_to
                ? formatDate(localFilters.date_to)
                : 'Now';
            labels.push(`${from} – ${to}`);
        }

        if (localFilters.search) {
            labels.push(`"${localFilters.search}"`);
        }

        for (const id of localFilters.account_ids) {
            const account = accounts.find((a) => String(a.id) === id);
            if (account) {
                labels.push(account.name);
            }
        }

        const allCats = categories.flatMap((p) => [p, ...(p.children ?? [])]);
        for (const id of localFilters.category_ids) {
            const cat = allCats.find((c) => String(c.id) === id);
            if (cat) {
                labels.push(cat.name);
            }
        }

        for (const type of localFilters.transaction_types) {
            labels.push(type.charAt(0).toUpperCase() + type.slice(1));
        }

        for (const id of localFilters.payee_ids) {
            const payee = payees.find((p) => String(p.id) === id);
            if (payee) {
                labels.push(payee.name);
            }
        }

        for (const id of localFilters.tag_ids) {
            const tag = tags.find((t) => String(t.id) === id);
            if (tag) {
                labels.push(tag.name);
            }
        }

        if (localFilters.uncategorized === '1') {
            labels.push('Uncategorized');
        }

        if (localFilters.bill_id) {
            labels.push('Recurring');
        }

        return labels;
    })();

    const activeFilterCount = activeFilterLabels.length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} transactions`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Transactions"
                        description={`Review all activity in ${ledger.name}.`}
                    />
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <Button
                            variant="outline"
                            size="sm"
                            className="flex-1 sm:flex-initial"
                            asChild
                        >
                            <a
                                href={
                                    exportTransactions.url(ledger.id) +
                                    '?' +
                                    (() => {
                                        const params = new URLSearchParams();

                                        for (const [key, val] of Object.entries(
                                            localFilters,
                                        )) {
                                            if (Array.isArray(val)) {
                                                for (const v of val) {
                                                    params.append(
                                                        `${key}[]`,
                                                        v,
                                                    );
                                                }
                                            } else if (
                                                val != null &&
                                                val !== ''
                                            ) {
                                                params.append(key, val);
                                            }
                                        }

                                        return params.toString();
                                    })()
                                }
                                download
                            >
                                Export CSV
                            </a>
                        </Button>
                        <AddTransactionModal
                            ledger={ledger}
                            accounts={accounts}
                            categories={flatCategories}
                            payees={payees}
                            tags={tags}
                        />
                    </div>
                </div>

                {/* Filters bar */}
                <Card
                    className={filtersOpen ? '' : 'cursor-pointer'}
                    onClick={() => {
                        if (!filtersOpen) {
                            setFiltersOpen(true);
                        }
                    }}
                >
                    <CardContent className="px-4 py-2">
                        <div className="flex flex-col gap-3">
                            {/* Mobile filter toggle */}
                            <button
                                type="button"
                                className="flex items-center justify-between"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setFiltersOpen(!filtersOpen);
                                }}
                            >
                                <div className="flex min-w-0 items-center gap-1.5">
                                    <SlidersHorizontal className="size-4 shrink-0 text-muted-foreground" />
                                    {activeFilterCount === 0 ? (
                                        <span className="text-sm text-muted-foreground">
                                            No filters applied
                                        </span>
                                    ) : (
                                        <div className="flex flex-wrap items-center gap-1">
                                            {activeFilterLabels.map((label) => (
                                                <Badge
                                                    key={label}
                                                    variant="secondary"
                                                    className="text-xs font-normal"
                                                >
                                                    {label}
                                                </Badge>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <ChevronDown
                                    className={`size-4 text-muted-foreground transition-transform ${filtersOpen ? 'rotate-180' : ''}`}
                                />
                            </button>

                            <div
                                className={`flex flex-col gap-3 ${filtersOpen ? '' : 'hidden'}`}
                            >
                                <div className="mb-3">
                                    <Input
                                        placeholder="Search description or notes..."
                                        value={localFilters.search ?? ''}
                                        onChange={(e) =>
                                            setLocalFilters((prev) => ({
                                                ...prev,
                                                search: e.target.value || null,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                                    <div className="grid gap-1">
                                        <Label className="text-xs">From</Label>
                                        <DatePicker
                                            value={localFilters.date_from}
                                            onChange={(date) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    date_from: date,
                                                }))
                                            }
                                            placeholder="From date"
                                        />
                                    </div>

                                    <div className="grid gap-1">
                                        <Label className="text-xs">To</Label>
                                        <DatePicker
                                            value={localFilters.date_to}
                                            onChange={(date) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    date_to: date,
                                                }))
                                            }
                                            placeholder="To date"
                                        />
                                    </div>

                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Account
                                        </Label>
                                        <SearchableSelect
                                            multiple
                                            options={accounts.map((a) => ({
                                                value: String(a.id),
                                                label: a.name,
                                                color: a.color,
                                            }))}
                                            value={localFilters.account_ids}
                                            onValueChange={(value) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    account_ids: value,
                                                }))
                                            }
                                            placeholder="All accounts"
                                            searchPlaceholder="Search accounts..."
                                            emptyMessage="No accounts found."
                                        />
                                    </div>

                                    <div className="grid gap-1">
                                        <Label className="text-xs">
                                            Category
                                        </Label>
                                        <SearchableSelect
                                            multiple
                                            options={flatCategories.map(
                                                (c) => ({
                                                    value: String(c.id),
                                                    label: c.name,
                                                    color: c.color,
                                                    group: c.parent_id
                                                        ? categories.find(
                                                              (p) =>
                                                                  p.id ===
                                                                  c.parent_id,
                                                          )?.name
                                                        : undefined,
                                                }),
                                            )}
                                            value={localFilters.category_ids}
                                            onValueChange={(value) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    category_ids: value,
                                                }))
                                            }
                                            placeholder="All categories"
                                            searchPlaceholder="Search categories..."
                                            emptyMessage="No categories found."
                                        />
                                    </div>

                                    <div className="grid gap-1">
                                        <Label className="text-xs">Type</Label>
                                        <SearchableSelect
                                            multiple
                                            options={[
                                                {
                                                    value: 'expense',
                                                    label: 'Expense',
                                                },
                                                {
                                                    value: 'income',
                                                    label: 'Income',
                                                },
                                                {
                                                    value: 'transfer',
                                                    label: 'Transfer',
                                                },
                                            ]}
                                            value={
                                                localFilters.transaction_types
                                            }
                                            onValueChange={(value) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    transaction_types: value,
                                                }))
                                            }
                                            placeholder="All types"
                                            searchPlaceholder="Search types..."
                                            emptyMessage="No types found."
                                        />
                                    </div>

                                    <div className="grid gap-1">
                                        <Label className="text-xs">Payee</Label>
                                        <SearchableSelect
                                            multiple
                                            options={payees.map((p) => ({
                                                value: String(p.id),
                                                label: p.name,
                                            }))}
                                            value={localFilters.payee_ids}
                                            onValueChange={(value) =>
                                                setLocalFilters((prev) => ({
                                                    ...prev,
                                                    payee_ids: value,
                                                }))
                                            }
                                            placeholder="All payees"
                                            searchPlaceholder="Search payees..."
                                            emptyMessage="No payees found."
                                        />
                                    </div>

                                    {tags.length > 0 && (
                                        <div className="grid gap-1">
                                            <Label className="text-xs">
                                                Tag
                                            </Label>
                                            <SearchableSelect
                                                multiple
                                                options={tags.map((t) => ({
                                                    value: String(t.id),
                                                    label: t.name,
                                                }))}
                                                value={localFilters.tag_ids}
                                                onValueChange={(value) =>
                                                    setLocalFilters((prev) => ({
                                                        ...prev,
                                                        tag_ids: value,
                                                    }))
                                                }
                                                placeholder="All tags"
                                                searchPlaceholder="Search tags..."
                                                emptyMessage="No tags found."
                                            />
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        onClick={handleApplyFilters}
                                    >
                                        Apply filters
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handleResetFilters}
                                    >
                                        Reset
                                    </Button>
                                    {activeFilterCount > 0 && (
                                        <span className="text-xs text-muted-foreground">
                                            {activeFilterCount} filter
                                            {activeFilterCount !== 1
                                                ? 's'
                                                : ''}{' '}
                                            active
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk actions bar */}
                {selectedIds.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 px-4 py-2">
                        <span className="text-sm font-medium text-muted-foreground">
                            {selectedIds.length} selected
                        </span>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_category')
                                }
                            >
                                Change category
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_account')
                                }
                            >
                                Change account
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openBulkActionModal('change_payee')
                                }
                            >
                                Change payee
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => setShowBulkDeleteConfirm(true)}
                            >
                                Delete selected
                            </Button>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setSelectedIds([])}
                        >
                            Clear selection
                        </Button>
                    </div>
                )}

                {/* Table */}
                <Card>
                    {/* Showing X-Y of Z */}
                    {transactions.total > 0 && (
                        <div className="px-6 pt-4 text-xs text-muted-foreground">
                            Showing {transactions.from}–{transactions.to} of{' '}
                            {transactions.total}
                        </div>
                    )}

                    <CardContent className="p-0">
                        {/* Mobile card list */}
                        <div className="divide-y sm:hidden">
                            {transactions.data.length === 0 ? (
                                <EmptyState
                                    icon={<Receipt className="size-6" />}
                                    title="No transactions yet"
                                    description="Start tracking your spending by adding your first transaction."
                                />
                            ) : (
                                <>
                                    <div className="flex items-center gap-2 border-b px-4 py-2">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={handleSelectAll}
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            Select all
                                        </span>
                                    </div>
                                    {transactions.data.map((transaction) => (
                                        <div
                                            key={transaction.id}
                                            className="space-y-1.5 px-4 py-3"
                                            onClick={() =>
                                                setEditTransaction(transaction)
                                            }
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex min-w-0 flex-1 items-start gap-3">
                                                    <div
                                                        className="pt-0.5"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <Checkbox
                                                            checked={selectedIds.includes(
                                                                transaction.id,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                handleSelectOne(
                                                                    transaction.id,
                                                                    checked,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-medium">
                                                            {transaction.description ??
                                                                transaction
                                                                    .payee
                                                                    ?.name ??
                                                                '—'}
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            {formatDate(
                                                                transaction.transaction_date,
                                                            )}
                                                            {transaction.account
                                                                ?.name
                                                                ? ` · ${transaction.account.name}`
                                                                : ''}
                                                            {transaction
                                                                .category?.name
                                                                ? ` · ${transaction.category.name}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <span
                                                        className={`text-sm font-semibold tabular-nums ${amountColor(transaction)}`}
                                                    >
                                                        {amountPrefix(
                                                            transaction,
                                                        )}
                                                        {formatAbsAmount(
                                                            transaction.amount,
                                                        )}
                                                    </span>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-7 w-7"
                                                            >
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    setEditTransaction(
                                                                        transaction,
                                                                    );
                                                                }}
                                                            >
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    handleDuplicate(
                                                                        transaction,
                                                                    );
                                                                }}
                                                            >
                                                                Duplicate
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                className="text-destructive"
                                                                onClick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    setDeleteConfirmTransaction(
                                                                        transaction,
                                                                    );
                                                                }}
                                                            >
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </div>
                                            {/* Tags */}
                                            {(transaction.tags ?? []).length >
                                                0 && (
                                                <div className="flex flex-wrap gap-1 pl-8">
                                                    {(
                                                        transaction.tags ?? []
                                                    ).map((tag) => (
                                                        <TagPill
                                                            key={tag.id}
                                                            tag={tag}
                                                        />
                                                    ))}
                                                </div>
                                            )}
                                            {/* Badges row */}
                                            {((transaction.splits ?? [])
                                                .length > 0 ||
                                                (transaction.attachments ?? [])
                                                    .length > 0) && (
                                                <div className="flex flex-wrap items-center gap-1 pl-8">
                                                    {(transaction.splits ?? [])
                                                        .length > 0 && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px]"
                                                        >
                                                            {
                                                                transaction
                                                                    .splits
                                                                    ?.length
                                                            }{' '}
                                                            splits
                                                        </Badge>
                                                    )}
                                                    {(
                                                        transaction.attachments ??
                                                        []
                                                    ).length > 0 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[10px]"
                                                        >
                                                            <Paperclip className="mr-0.5 h-2.5 w-2.5" />
                                                            {
                                                                transaction
                                                                    .attachments
                                                                    ?.length
                                                            }
                                                        </Badge>
                                                    )}
                                                </div>
                                            )}
                                            {/* Split details */}
                                            {(transaction.splits ?? []).length >
                                                0 && (
                                                <div className="space-y-1 pl-8">
                                                    {(
                                                        transaction.splits ?? []
                                                    ).map((split) => (
                                                        <div
                                                            key={split.id}
                                                            className="flex items-center justify-between text-xs text-muted-foreground"
                                                        >
                                                            <span className="truncate">
                                                                {split.description ??
                                                                    split
                                                                        .category
                                                                        ?.name ??
                                                                    'Uncategorized'}
                                                            </span>
                                                            <span className="ml-2 shrink-0 tabular-nums">
                                                                {formatAbsAmount(
                                                                    split.amount,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </>
                            )}
                        </div>

                        <Table className="hidden sm:table">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10 pl-4">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={handleSelectAll}
                                            aria-label="Select all"
                                        />
                                    </TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Account
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Category
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Payee
                                    </TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Tags
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    {runningBalances && (
                                        <TableHead className="hidden text-right lg:table-cell">
                                            Balance
                                        </TableHead>
                                    )}
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={runningBalances ? 11 : 10}
                                        >
                                            <EmptyState
                                                icon={
                                                    <Receipt className="size-6" />
                                                }
                                                title="No transactions yet"
                                                description="Start tracking your spending by adding your first transaction."
                                            />
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    transactions.data.map((transaction) => (
                                        <TableRow
                                            key={transaction.id}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                setEditTransaction(transaction)
                                            }
                                        >
                                            <TableCell
                                                className="pl-4"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        transaction.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        handleSelectOne(
                                                            transaction.id,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Select transaction ${transaction.id}`}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(
                                                    transaction.transaction_date,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {transaction.description ??
                                                        transaction.payee
                                                            ?.name ??
                                                        '—'}
                                                </span>
                                                {((transaction.splits ?? [])
                                                    .length > 0 ||
                                                    (
                                                        transaction.attachments ??
                                                        []
                                                    ).length > 0) && (
                                                    <div className="mt-1 flex flex-wrap items-center gap-1">
                                                        {(
                                                            transaction.splits ??
                                                            []
                                                        ).length > 0 && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="text-[10px]"
                                                            >
                                                                {
                                                                    transaction
                                                                        .splits
                                                                        ?.length
                                                                }{' '}
                                                                splits
                                                            </Badge>
                                                        )}
                                                        {(
                                                            transaction.attachments ??
                                                            []
                                                        ).length > 0 && (
                                                            <Badge
                                                                variant="outline"
                                                                className="gap-1 text-[10px]"
                                                            >
                                                                <Paperclip className="size-2.5" />
                                                                {
                                                                    transaction
                                                                        .attachments
                                                                        ?.length
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>
                                                )}
                                                {(transaction.splits ?? [])
                                                    .length > 0 && (
                                                    <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                                        {(
                                                            transaction.splits ??
                                                            []
                                                        ).map((split) => (
                                                            <div
                                                                key={split.id}
                                                                className="flex items-center justify-between gap-3"
                                                            >
                                                                <span className="truncate">
                                                                    {split.description ??
                                                                        split
                                                                            .category
                                                                            ?.name ??
                                                                        'Uncategorized'}
                                                                </span>
                                                                <span className="tabular-nums">
                                                                    {formatAbsAmount(
                                                                        split.amount,
                                                                    )}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground md:table-cell">
                                                {transaction.account?.name ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground lg:table-cell">
                                                {transaction.category?.name ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground lg:table-cell">
                                                {transaction.payee?.name ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden xl:table-cell">
                                                {(transaction.tags ?? [])
                                                    .length > 0 && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {(
                                                            transaction.tags ??
                                                            []
                                                        ).map((tag) => (
                                                            <TagPill
                                                                key={tag.id}
                                                                tag={tag}
                                                            />
                                                        ))}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right font-semibold tabular-nums ${amountColor(transaction)}`}
                                            >
                                                {amountPrefix(transaction)}
                                                {formatAbsAmount(
                                                    transaction.amount,
                                                )}
                                            </TableCell>
                                            {runningBalances && (
                                                <TableCell className="hidden text-right text-muted-foreground tabular-nums lg:table-cell">
                                                    {formatAmount(
                                                        runningBalances.get(
                                                            transaction.id,
                                                        ) ?? 0,
                                                    )}
                                                </TableCell>
                                            )}
                                            <TableCell
                                                className="pr-4"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Actions
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                setEditTransaction(
                                                                    transaction,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleDuplicate(
                                                                    transaction,
                                                                )
                                                            }
                                                        >
                                                            Duplicate
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            className="text-destructive"
                                                            onClick={() =>
                                                                setDeleteConfirmTransaction(
                                                                    transaction,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>

                    {/* Pagination */}
                    {transactions.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-border px-6 py-3">
                            <span className="text-xs text-muted-foreground">
                                Page {transactions.current_page} of{' '}
                                {transactions.last_page}
                            </span>
                            <div className="flex gap-1">
                                {transactions.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <Button
                                                key={i}
                                                variant="outline"
                                                size="sm"
                                                disabled
                                                className="h-7 px-2.5 text-xs"
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    }

                                    return (
                                        <Button
                                            key={i}
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            className="h-7 px-2.5 text-xs"
                                            asChild
                                        >
                                            <Link
                                                href={link.url}
                                                preserveState
                                                preserveScroll
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Button>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            {/* Edit modal */}
            {editTransaction && (
                <EditTransactionModal
                    ledger={ledger}
                    transaction={editTransaction}
                    accounts={accounts}
                    categories={categories}
                    payees={payees}
                    tags={tags}
                    onClose={() => setEditTransaction(null)}
                />
            )}

            {/* Single delete confirmation */}
            <Dialog
                open={deleteConfirmTransaction !== null}
                onOpenChange={(open) =>
                    !open && setDeleteConfirmTransaction(null)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this transaction?</DialogTitle>
                        <DialogDescription>
                            This transaction will be moved to trash. You can
                            undo this action for a short time after deletion.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteConfirmTransaction(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleContextDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Duplicate transaction modal */}
            <AddTransactionModal
                ledger={ledger}
                accounts={accounts}
                categories={flatCategories}
                payees={payees}
                tags={tags}
                externalOpen={showDuplicateModal}
                onExternalOpenChange={(open) => {
                    setShowDuplicateModal(open);

                    if (!open) {
                        setDuplicateTransaction(null);
                    }
                }}
                initialData={duplicateTransaction}
            />

            {/* Bulk delete confirmation */}
            <Dialog
                open={showBulkDeleteConfirm}
                onOpenChange={(open) =>
                    !open && setShowBulkDeleteConfirm(false)
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete transactions</DialogTitle>
                        <DialogDescription>
                            Delete <strong>{selectedIds.length}</strong>{' '}
                            transaction
                            {selectedIds.length !== 1 ? 's' : ''}? This cannot
                            be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowBulkDeleteConfirm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleBulkDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Bulk action modal (change category/account/payee) */}
            <Dialog
                open={bulkAction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setBulkAction(null);
                        setBulkActionValue(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {bulkAction === 'change_category' &&
                                'Change category'}
                            {bulkAction === 'change_account' &&
                                'Change account'}
                            {bulkAction === 'change_payee' && 'Change payee'}
                        </DialogTitle>
                        <DialogDescription>
                            Update <strong>{selectedIds.length}</strong>{' '}
                            transaction
                            {selectedIds.length !== 1 ? 's' : ''}. Transfer
                            transactions will be skipped.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        {bulkAction === 'change_category' && (
                            <SearchableSelect
                                options={flatCategories.map((c) => ({
                                    value: String(c.id),
                                    label: c.parent_id
                                        ? `${categories.find((p) => p.id === c.parent_id)?.name} > ${c.name}`
                                        : c.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select category..."
                                searchPlaceholder="Search categories..."
                            />
                        )}
                        {bulkAction === 'change_account' && (
                            <SearchableSelect
                                options={accounts.map((a) => ({
                                    value: String(a.id),
                                    label: a.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select account..."
                                searchPlaceholder="Search accounts..."
                            />
                        )}
                        {bulkAction === 'change_payee' && (
                            <SearchableSelect
                                options={payees.map((p) => ({
                                    value: String(p.id),
                                    label: p.name,
                                }))}
                                value={bulkActionValue}
                                onValueChange={setBulkActionValue}
                                placeholder="Select payee..."
                                searchPlaceholder="Search payees..."
                            />
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setBulkAction(null);
                                setBulkActionValue(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            disabled={!bulkActionValue}
                            onClick={handleBulkUpdate}
                        >
                            Update
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
