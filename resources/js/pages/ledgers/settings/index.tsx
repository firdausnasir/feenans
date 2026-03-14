import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { ColorPicker } from '@/components/color-picker';
import { CurrencySelect } from '@/components/currency-select';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import {
    dashboard as ledgerDashboard,
    destroy as destroyLedger,
    exportMethod as ledgerExport,
} from '@/routes/ledgers';
import {
    destroy as accountTypeDestroy,
    reorder,
    store as accountTypeStore,
    update as accountTypeUpdate,
} from '@/routes/ledgers/account-types';
import {
    index as settingsIndex,
    update as settingsUpdate,
} from '@/routes/ledgers/settings';
import { destroy as destroySampleData } from '@/routes/ledgers/sample-data';
import type { AccountType, BreadcrumbItem, Ledger } from '@/types';

// ─── Types ────────────────────────────────────────────────────────────────────

type LedgerWithAccountTypes = Ledger & { account_types: AccountType[] };

type AccountTypeEditState = {
    accountTypeId: number;
    name: string;
    color: string;
    is_credit: boolean;
};

type AddAccountTypeState = {
    name: string;
    color: string;
    is_credit: boolean;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function colorDot(color: string | null) {
    if (!color) {
        return (
            <span className="inline-block h-3 w-3 rounded-full border border-border bg-muted" />
        );
    }

    return (
        <span
            className="inline-block h-3 w-3 rounded-full border border-border"
            style={{ backgroundColor: color }}
        />
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function SettingsIndex({
    ledger,
    hasSampleData,
}: {
    ledger: LedgerWithAccountTypes;
    hasSampleData: boolean;
}) {
    // General settings state
    const [ledgerName, setLedgerName] = useState(ledger.name);
    const [cycleStartDay, setCycleStartDay] = useState(ledger.cycle_start_day);
    const [currencyCode, setCurrencyCode] = useState(ledger.currency_code);
    const [isSavingGeneral, setIsSavingGeneral] = useState(false);

    // Account types state
    const [editState, setEditState] = useState<AccountTypeEditState | null>(
        null,
    );
    const [showAddForm, setShowAddForm] = useState(false);
    const [addState, setAddState] = useState<AddAccountTypeState>({
        name: '',
        color: '#6b7280',
        is_credit: false,
    });
    const [deleteTarget, setDeleteTarget] = useState<AccountType | null>(null);
    const [isDeletingAccountType, setIsDeletingAccountType] = useState(false);

    // Drag state
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);
    const isReorderingRef = useRef(false);

    // Danger zone state
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [deleteConfirmName, setDeleteConfirmName] = useState('');
    const [isDeletingLedger, setIsDeletingLedger] = useState(false);

    // Sample data state
    const [isRemovingSampleData, setIsRemovingSampleData] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Settings', href: settingsIndex.url(ledger.id) },
    ];

    // ── General settings handlers ─────────────────────────────────────────────

    function handleSaveGeneral() {
        if (cycleStartDay < 1 || cycleStartDay > 31) {
            toast.error('Cycle start day must be between 1 and 31.');

            return;
        }

        setIsSavingGeneral(true);

        router.put(
            settingsUpdate.url(ledger.id),
            {
                name: ledgerName.trim(),
                cycle_start_day: cycleStartDay,
                currency_code: currencyCode,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSavingGeneral(false);
                    toast.success('Settings saved.');
                },
                onError: (errors) => {
                    setIsSavingGeneral(false);
                    const msg =
                        errors.name ??
                        errors.cycle_start_day ??
                        errors.currency_code ??
                        'Failed to save settings.';
                    toast.error(msg);
                },
            },
        );
    }

    // ── Account type drag & drop ──────────────────────────────────────────────

    function handleDragStart(e: React.DragEvent, id: number) {
        e.dataTransfer.setData('text/plain', String(id));
        e.dataTransfer.effectAllowed = 'move';
    }

    function handleDragOver(e: React.DragEvent, id: number) {
        e.preventDefault();

        if (dragOverIdRef.current !== id) {
            dragOverIdRef.current = id;
            setDragOverId(id);
        }
    }

    function handleDragLeave() {
        dragOverIdRef.current = null;
        setDragOverId(null);
    }

    function handleDrop(e: React.DragEvent, targetId: number) {
        e.preventDefault();
        dragOverIdRef.current = null;
        setDragOverId(null);

        if (isReorderingRef.current) {
            return;
        }

        const draggedId = Number(e.dataTransfer.getData('text/plain'));

        if (draggedId === targetId) {
            return;
        }

        const reordered = [...ledger.account_types];
        const fromIdx = reordered.findIndex((t) => t.id === draggedId);
        const toIdx = reordered.findIndex((t) => t.id === targetId);

        if (fromIdx === -1 || toIdx === -1) {
            return;
        }

        const [moved] = reordered.splice(fromIdx, 1);
        reordered.splice(toIdx, 0, moved);

        const items = reordered.map((t, i) => ({ id: t.id, position: i + 1 }));

        isReorderingRef.current = true;

        router.post(
            reorder.url(ledger.id),
            { items },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isReorderingRef.current = false;
                },
                onError: () => {
                    isReorderingRef.current = false;
                    toast.error('Failed to reorder account types.');
                },
            },
        );
    }

    // ── Account type inline edit ──────────────────────────────────────────────

    function startEdit(accountType: AccountType) {
        setEditState({
            accountTypeId: accountType.id,
            name: accountType.name,
            color: accountType.color ?? '#6b7280',
            is_credit: accountType.is_credit,
        });
    }

    function saveEdit() {
        if (!editState) {
            return;
        }

        if (!editState.name.trim()) {
            toast.error('Account type name is required.');

            return;
        }

        router.put(
            accountTypeUpdate.url({
                ledger: ledger.id,
                accountType: editState.accountTypeId,
            }),
            {
                name: editState.name,
                color: editState.color,
                is_credit: editState.is_credit,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditState(null);
                    toast.success('Account type updated.');
                },
                onError: (errors) => {
                    const msg =
                        errors.name ??
                        errors.color ??
                        errors.is_credit ??
                        'Failed to update account type.';
                    toast.error(msg);
                },
            },
        );
    }

    // ── Account type add ──────────────────────────────────────────────────────

    function handleAddAccountType() {
        if (!addState.name.trim()) {
            return;
        }

        router.post(
            accountTypeStore.url(ledger.id),
            {
                name: addState.name.trim(),
                color: addState.color,
                is_credit: addState.is_credit,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddState({
                        name: '',
                        color: '#6b7280',
                        is_credit: false,
                    });
                    setShowAddForm(false);
                    toast.success('Account type added.');
                },
                onError: (errors) => {
                    const msg =
                        errors.name ??
                        errors.color ??
                        errors.is_credit ??
                        'Failed to add account type.';
                    toast.error(msg);
                },
            },
        );
    }

    // ── Account type delete ───────────────────────────────────────────────────

    function handleDeleteAccountType() {
        if (!deleteTarget) {
            return;
        }

        setIsDeletingAccountType(true);

        router.delete(
            accountTypeDestroy.url({
                ledger: ledger.id,
                accountType: deleteTarget.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsDeletingAccountType(false);
                    setDeleteTarget(null);
                    toast.success('Account type deleted.');
                },
                onError: (errors) => {
                    setIsDeletingAccountType(false);
                    setDeleteTarget(null);
                    const msg =
                        errors.account_type ??
                        errors.message ??
                        Object.values(errors)[0] ??
                        'Cannot delete this account type.';
                    toast.error(String(msg));
                },
            },
        );
    }

    // ── Sample data handler ─────────────────────────────────────────────

    function handleRemoveSampleData() {
        setIsRemovingSampleData(true);

        router.delete(destroySampleData.url(ledger.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsRemovingSampleData(false);
                toast.success('Sample data removed.');
            },
            onError: (errors) => {
                setIsRemovingSampleData(false);
                const msg =
                    errors.message ??
                    Object.values(errors)[0] ??
                    'Failed to remove sample data.';
                toast.error(String(msg));
            },
        });
    }

    // ── Ledger delete ─────────────────────────────────────────────────────────

    function handleDeleteLedger() {
        setIsDeletingLedger(true);

        router.delete(destroyLedger.url(ledger.id), {
            preserveScroll: false,
            onSuccess: () => {
                setIsDeletingLedger(false);
                setShowDeleteDialog(false);
            },
            onError: (errors) => {
                setIsDeletingLedger(false);
                const msg =
                    errors.ledger ??
                    errors.message ??
                    Object.values(errors)[0] ??
                    'Failed to delete workspace.';
                toast.error(String(msg));
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} settings`} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6 lg:p-8">
                <Heading
                    title="Settings"
                    description="Configure your workspace settings."
                />

                {/* ── General ────────────────────────────────────────────── */}
                <section className="space-y-4">
                    <h2 className="text-base font-semibold">General</h2>
                    <Separator />

                    <div className="grid max-w-md gap-4">
                        {/* Workspace name */}
                        <div className="space-y-1.5">
                            <Label htmlFor="ledger-name">Workspace name</Label>
                            <Input
                                id="ledger-name"
                                value={ledgerName}
                                onChange={(e) => setLedgerName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        handleSaveGeneral();
                                    }
                                }}
                            />
                        </div>

                        {/* Currency */}
                        <div className="space-y-1.5">
                            <Label>Currency code</Label>
                            <CurrencySelect
                                value={currencyCode}
                                onValueChange={setCurrencyCode}
                            />
                            {currencyCode !== ledger.currency_code && (
                                <p className="text-xs text-amber-600 dark:text-amber-400">
                                    Changing the currency code does not convert
                                    existing transaction amounts.
                                </p>
                            )}
                        </div>

                        {/* Cycle start day */}
                        <div className="space-y-1.5">
                            <Label htmlFor="cycle-start-day">
                                Cycle start day
                            </Label>
                            <Input
                                id="cycle-start-day"
                                type="number" inputMode="decimal"
                                min={1}
                                max={31}
                                value={cycleStartDay}
                                onChange={(e) =>
                                    setCycleStartDay(Number(e.target.value))
                                }
                                className="w-24"
                            />
                            <p className="text-xs text-muted-foreground">
                                Day of the month your budget cycle starts
                                (1–31). Months with fewer days will use the last
                                day.
                            </p>
                        </div>

                        <div>
                            <Button
                                onClick={handleSaveGeneral}
                                disabled={isSavingGeneral || !ledgerName.trim()}
                            >
                                {isSavingGeneral ? 'Saving…' : 'Save'}
                            </Button>
                        </div>
                    </div>
                </section>

                {/* ── Account Types ───────────────────────────────────────── */}
                <section className="space-y-4">
                    <h2 className="text-base font-semibold">Account Types</h2>
                    <Separator />

                    <div className="max-w-lg space-y-1">
                        {ledger.account_types.length === 0 && !showAddForm && (
                            <p className="py-4 text-sm text-muted-foreground">
                                No account types yet.
                            </p>
                        )}

                        {ledger.account_types.map((accountType) => {
                            const isEditing =
                                editState?.accountTypeId === accountType.id;
                            const isDragOver = dragOverId === accountType.id;

                            return (
                                <div
                                    key={accountType.id}
                                    draggable
                                    onDragStart={(e) =>
                                        handleDragStart(e, accountType.id)
                                    }
                                    onDragOver={(e) =>
                                        handleDragOver(e, accountType.id)
                                    }
                                    onDragLeave={handleDragLeave}
                                    onDragEnd={() => {
                                        dragOverIdRef.current = null;
                                        setDragOverId(null);
                                    }}
                                    onDrop={(e) =>
                                        handleDrop(e, accountType.id)
                                    }
                                    className={`group flex items-center gap-3 rounded-lg px-3 py-2 transition-colors ${
                                        isDragOver
                                            ? 'border border-primary/40 bg-primary/5'
                                            : 'border border-transparent hover:bg-muted/50'
                                    }`}
                                >
                                    {/* Drag handle */}
                                    <span
                                        aria-hidden="true"
                                        className="cursor-grab text-muted-foreground opacity-0 select-none group-hover:opacity-100"
                                    >
                                        ⋮⋮
                                    </span>

                                    {/* Color dot */}
                                    {colorDot(accountType.color)}

                                    {isEditing && editState ? (
                                        /* Inline edit form */
                                        <div className="flex flex-1 flex-wrap items-center gap-2">
                                            <Input
                                                autoFocus
                                                value={editState.name}
                                                onChange={(e) =>
                                                    setEditState({
                                                        ...editState,
                                                        name: e.target.value,
                                                    })
                                                }
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        saveEdit();
                                                    } else if (
                                                        e.key === 'Escape'
                                                    ) {
                                                        setEditState(null);
                                                    }
                                                }}
                                                className="h-7 w-40 text-sm"
                                            />
                                            <div className="flex items-center gap-1">
                                                <Label
                                                    className="sr-only"
                                                    htmlFor={`at-color-${editState.accountTypeId}`}
                                                >
                                                    Color
                                                </Label>
                                                <ColorPicker
                                                    id={`at-color-${editState.accountTypeId}`}
                                                    value={editState.color}
                                                    onChange={(color) =>
                                                        setEditState({
                                                            ...editState,
                                                            color,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Switch
                                                    id={`at-credit-${editState.accountTypeId}`}
                                                    checked={
                                                        editState.is_credit
                                                    }
                                                    onCheckedChange={(v) =>
                                                        setEditState({
                                                            ...editState,
                                                            is_credit: v,
                                                        })
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`at-credit-${editState.accountTypeId}`}
                                                    className="text-xs"
                                                >
                                                    Credit
                                                </Label>
                                            </div>
                                            <Button
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                                onClick={saveEdit}
                                            >
                                                Save
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="h-7 px-2 text-xs"
                                                onClick={() =>
                                                    setEditState(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    ) : (
                                        <>
                                            <Button
                                                type="button"
                                                variant="link"
                                                onClick={() =>
                                                    startEdit(accountType)
                                                }
                                                className="h-auto flex-1 justify-start p-0 text-sm font-medium text-foreground no-underline hover:underline"
                                            >
                                                {accountType.name}
                                            </Button>

                                            <Badge
                                                variant={
                                                    accountType.is_credit
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {accountType.is_credit
                                                    ? 'Credit'
                                                    : 'Debit'}
                                            </Badge>

                                            <div className="flex items-center gap-1 opacity-100 sm:opacity-0 transition-opacity sm:group-hover:opacity-100">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setDeleteTarget(
                                                            accountType,
                                                        )
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            );
                        })}

                        {/* Add account type form */}
                        {showAddForm ? (
                            <div className="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-border p-3">
                                <Input
                                    autoFocus
                                    value={addState.name}
                                    onChange={(e) =>
                                        setAddState({
                                            ...addState,
                                            name: e.target.value,
                                        })
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            handleAddAccountType();
                                        } else if (e.key === 'Escape') {
                                            setShowAddForm(false);
                                        }
                                    }}
                                    placeholder="Account type name…"
                                    className="h-7 w-48 text-sm"
                                />
                                <ColorPicker
                                    value={addState.color}
                                    onChange={(color) =>
                                        setAddState({
                                            ...addState,
                                            color,
                                        })
                                    }
                                />
                                <div className="flex items-center gap-1.5">
                                    <Switch
                                        id="add-at-credit"
                                        checked={addState.is_credit}
                                        onCheckedChange={(v) =>
                                            setAddState({
                                                ...addState,
                                                is_credit: v,
                                            })
                                        }
                                    />
                                    <Label
                                        htmlFor="add-at-credit"
                                        className="text-xs"
                                    >
                                        Credit
                                    </Label>
                                </div>
                                <Button
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={handleAddAccountType}
                                    disabled={!addState.name.trim()}
                                >
                                    Add
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="h-7 px-2 text-xs"
                                    onClick={() => setShowAddForm(false)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-2"
                                onClick={() => setShowAddForm(true)}
                            >
                                + Add Account Type
                            </Button>
                        )}
                    </div>
                </section>

                {/* ── Sample Data ─────────────────────────────────────────── */}
                {hasSampleData && (
                    <section className="space-y-4">
                        <h2 className="text-base font-semibold">Sample Data</h2>
                        <Separator />

                        <div className="rounded-lg border border-border p-4">
                            <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-medium">
                                        Remove sample data
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        This will delete all sample accounts,
                                        transactions, bills, and payees. Your
                                        own data will not be affected.
                                    </p>
                                </div>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleRemoveSampleData}
                                    disabled={isRemovingSampleData}
                                >
                                    {isRemovingSampleData
                                        ? 'Removing...'
                                        : 'Remove sample data'}
                                </Button>
                            </div>
                        </div>
                    </section>
                )}

                {/* ── Data Export ──────────────────────────────────────────── */}
                <section className="space-y-4">
                    <h2 className="text-base font-semibold">Data Export</h2>
                    <Separator />

                    <div className="rounded-lg border border-border p-4">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium">
                                    Export all data
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Download all accounts, transactions,
                                    categories, payees, tags, bills, and budgets
                                    as a JSON file.
                                </p>
                            </div>
                            <a href={ledgerExport.url(ledger.id)} download>
                                <Button variant="outline" size="sm">
                                    <Download className="mr-2 size-4" />
                                    Export
                                </Button>
                            </a>
                        </div>
                    </div>
                </section>

                {/* ── Danger Zone ─────────────────────────────────────────── */}
                <section className="space-y-4">
                    <h2 className="text-base font-semibold text-destructive">
                        Danger Zone
                    </h2>
                    <Separator />

                    <div className="rounded-lg border border-destructive/30 p-4">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium">
                                    Delete this workspace
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    This will permanently delete all accounts,
                                    transactions, categories, and other data.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    setDeleteConfirmName('');
                                    setShowDeleteDialog(true);
                                }}
                            >
                                Delete workspace
                            </Button>
                        </div>
                    </div>
                </section>
            </div>

            {/* ── Delete account type dialog ───────────────────────────────── */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete account type</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{deleteTarget?.name}</strong>? If any
                            accounts use this type, the deletion will be
                            rejected.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDeleteAccountType}
                            disabled={isDeletingAccountType}
                        >
                            {isDeletingAccountType ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* ── Delete workspace dialog ────────────────────────────────────── */}
            <Dialog
                open={showDeleteDialog}
                onOpenChange={(open) => {
                    if (!open) {
                        setShowDeleteDialog(false);
                        setDeleteConfirmName('');
                        setIsDeletingLedger(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete workspace</DialogTitle>
                        <DialogDescription>
                            This will permanently delete all accounts,
                            transactions, categories, and other data associated
                            with <strong>{ledger.name}</strong>. This cannot be
                            undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-1.5">
                        <Label htmlFor="confirm-ledger-name">
                            Type <strong>{ledger.name}</strong> to confirm
                        </Label>
                        <Input
                            id="confirm-ledger-name"
                            value={deleteConfirmName}
                            onChange={(e) =>
                                setDeleteConfirmName(e.target.value)
                            }
                            placeholder={ledger.name}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowDeleteDialog(false);
                                setDeleteConfirmName('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDeleteLedger}
                            disabled={
                                isDeletingLedger ||
                                deleteConfirmName !== ledger.name
                            }
                        >
                            {isDeletingLedger
                                ? 'Deleting…'
                                : 'Delete workspace'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
