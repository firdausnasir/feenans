import { Head, router, useHttp, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { types as accountTypesLoader } from '@/actions/App/Http/Controllers/Api/V1/Ledger/AccountController';
import {
    show as showLedgerLoader,
    hasSampleData as hasSampleDataLoader,
} from '@/actions/App/Http/Controllers/Api/V1/LedgerController';
import SampleDataController from '@/actions/App/Http/Controllers/Ledger/SampleDataController';
import SettingsController from '@/actions/App/Http/Controllers/Ledger/SettingsController';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import { ColorPicker } from '@/components/color-picker';
import { CurrencySelect } from '@/components/currency-select';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { mapInertiaErrorsArray } from '@/lib/utils';
import {
    dashboard as ledgerDashboard,
    exportMethod as ledgerExport,
} from '@/routes/ledgers';
import { index as settingsIndex } from '@/routes/ledgers/settings';
import type { AccountType, BreadcrumbItem } from '@/types';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';

// ─── Types ────────────────────────────────────────────────────────────────────

type LedgerSettings = {
    id: number;
    name: string;
    currency_code: string;
    cycle_start_day: number;
    uses_seeded_categories: boolean;
};

type ApiEnvelope<T> = { data: T };

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

// ─── Loading Skeletons ────────────────────────────────────────────────────────

function SettingsLoadingSkeleton() {
    return (
        <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <section className="space-y-4">
                <Skeleton className="h-5 w-16" />
                <Separator />
                <div className="grid max-w-md gap-4">
                    <div className="space-y-1.5">
                        <Skeleton className="h-4 w-28" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                    <div className="space-y-1.5">
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                    <div className="space-y-1.5">
                        <Skeleton className="h-4 w-28" />
                        <Skeleton className="h-10 w-24" />
                    </div>
                    <Skeleton className="h-10 w-16" />
                </div>
            </section>
            <section className="space-y-4">
                <Skeleton className="h-5 w-28" />
                <Separator />
                <div className="max-w-lg space-y-2">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3 px-3 py-2">
                            <Skeleton className="h-3 w-3 rounded-full" />
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-5 w-14" />
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function SettingsIndex() {
    const { currentLedger } = usePage<{
        currentLedger: { id: number; name: string; currency_code: string; cycle_start_day: number } | null;
    }>().props;
    const ledger = currentLedger!;

    // API loaders
    const ledgerLoaderState = useHttp<Record<string, never>, ApiEnvelope<LedgerSettings>>({});
    const accountTypesLoaderState = useHttp<Record<string, never>, ApiEnvelope<AccountType[]>>({});
    const sampleDataLoaderState = useHttp<Record<string, never>, ApiEnvelope<boolean>>({});

    const [hasLoaded, setHasLoaded] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);

    const ledgerSettings = ledgerLoaderState.response?.data ?? null;
    const accountTypes = accountTypesLoaderState.response?.data ?? [];
    const hasSampleData = sampleDataLoaderState.response?.data ?? false;

    // General settings state
    const [ledgerName, setLedgerName] = useState<string | null>(null);
    const [cycleStartDay, setCycleStartDay] = useState<number | null>(null);
    const [currencyCode, setCurrencyCode] = useState<string | null>(null);
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

    // ── Data loading ──────────────────────────────────────────────────────────

    async function loadAllData(): Promise<boolean> {
        if (!ledger) {
            return false;
        }

        let cancelled = false;

        ledgerLoaderState.cancel();
        accountTypesLoaderState.cancel();
        sampleDataLoaderState.cancel();
        setLoadError(null);

        try {
            await Promise.allSettled([
                ledgerLoaderState.get(showLedgerLoader.url(ledger.id), {
                    onCancel: () => {
 cancelled = true; 
},
                }),
                accountTypesLoaderState.get(accountTypesLoader.url(ledger.id), {
                    onCancel: () => {
 cancelled = true; 
},
                }),
                sampleDataLoaderState.get(hasSampleDataLoader.url(ledger.id), {
                    onCancel: () => {
 cancelled = true; 
},
                }),
            ]);

            return true;
        } catch {
            if (!cancelled) {
                setLoadError('Failed to load settings.');
            }

            return false;
        } finally {
            if (!cancelled) {
                setHasLoaded(true);
            }
        }
    }

    async function reloadAccountTypes(): Promise<void> {
        if (!ledger) {
return;
}

        try {
            await accountTypesLoaderState.get(accountTypesLoader.url(ledger.id));
        } catch {
            // Silently fail — stale data is acceptable after mutations
        }
    }

    async function reloadSampleData(): Promise<void> {
        if (!ledger) {
return;
}

        try {
            await sampleDataLoaderState.get(hasSampleDataLoader.url(ledger.id));
        } catch {
            // Silently fail
        }
    }

    async function reloadLedgerSettings(): Promise<void> {
        if (!ledger) {
return;
}

        try {
            await ledgerLoaderState.get(showLedgerLoader.url(ledger.id));
        } catch {
            // Silently fail
        }
    }

    useEffect(() => {
        void loadAllData();

        return () => {
            ledgerLoaderState.cancel();
            accountTypesLoaderState.cancel();
            sampleDataLoaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ledger?.id]);

    // Derived values: use local overrides or fall back to API data
    const effectiveName = ledgerName ?? ledgerSettings?.name ?? ledger.name;
    const effectiveCycleDay = cycleStartDay ?? ledgerSettings?.cycle_start_day ?? ledger.cycle_start_day;
    const effectiveCurrency = currencyCode ?? ledgerSettings?.currency_code ?? ledger.currency_code;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Workspace Settings', href: settingsIndex.url(ledger.id) },
    ];

    // ── General settings handlers ─────────────────────────────────────────────

    function handleSaveGeneral() {
        if (effectiveCycleDay < 1 || effectiveCycleDay > 31) {
            toast.error('Cycle start day must be between 1 and 31.');

            return;
        }

        setIsSavingGeneral(true);

        router.put(
            SettingsController.update.url(ledger.id),
            {
                name: effectiveName.trim(),
                cycle_start_day: effectiveCycleDay,
                currency_code: effectiveCurrency,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Settings saved.');
                    setLedgerName(null);
                    setCycleStartDay(null);
                    setCurrencyCode(null);
                    void reloadLedgerSettings();
                },
                onError: (errors) => {
                    const msg =
                        typeof errors.name === 'string'
                            ? errors.name
                            : typeof errors.cycle_start_day === 'string'
                              ? errors.cycle_start_day
                              : typeof errors.currency_code === 'string'
                                ? errors.currency_code
                                : 'Failed to save settings.';
                    toast.error(msg);
                },
                onFinish: () => setIsSavingGeneral(false),
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

        const reordered = [...accountTypes];
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
            SettingsController.reorderAccountTypes.url(ledger.id),
            { items },
            {
                preserveScroll: true,
                onError: () => {
                    toast.error('Failed to reorder account types.');
                },
                onFinish: () => {
                    isReorderingRef.current = false;
                    void reloadAccountTypes();
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
            SettingsController.updateAccountType.url({
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
                    void reloadAccountTypes();
                },
                onError: (errors) => {
                    const msg =
                        typeof errors.name === 'string'
                            ? errors.name
                            : typeof errors.color === 'string'
                              ? errors.color
                              : typeof errors.is_credit === 'string'
                                ? errors.is_credit
                                : 'Failed to update account type.';
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
            SettingsController.storeAccountType.url(ledger.id),
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
                    void reloadAccountTypes();
                },
                onError: (errors) => {
                    const msg =
                        typeof errors.name === 'string'
                            ? errors.name
                            : typeof errors.color === 'string'
                              ? errors.color
                              : typeof errors.is_credit === 'string'
                                ? errors.is_credit
                                : 'Failed to add account type.';
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
            SettingsController.destroyAccountType.url({
                ledger: ledger.id,
                accountType: deleteTarget.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteTarget(null);
                    toast.success('Account type deleted.');
                    void reloadAccountTypes();
                },
                onError: (errors) => {
                    const mapped = mapInertiaErrorsArray(errors);
                    toast.error(
                        mapped.account_type?.[0] ??
                            'Cannot delete this account type.',
                    );
                },
                onFinish: () => {
                    setIsDeletingAccountType(false);
                },
            },
        );
    }

    // ── Sample data handler ─────────────────────────────────────────────

    function handleRemoveSampleData() {
        setIsRemovingSampleData(true);

        router.delete(SampleDataController.destroy.url(ledger.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Sample data removed.');
                void reloadSampleData();
            },
            onError: () => {
                toast.error('Failed to remove sample data.');
            },
            onFinish: () => {
                setIsRemovingSampleData(false);
            },
        });
    }

    // ── Ledger delete ─────────────────────────────────────────────────────────

    function handleDeleteLedger() {
        setIsDeletingLedger(true);

        router.delete(LedgerController.destroy.url(ledger.id), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDeleteDialog(false);
            },
            onError: () => {
                toast.error('Failed to delete workspace.');
            },
            onFinish: () => {
                setIsDeletingLedger(false);
            },
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} workspace settings`} />

            <BoneSkeleton
                name="settings-page"
                loading={!hasLoaded}
                fallback={<SettingsLoadingSkeleton />}
            >
                {loadError ? (
                    <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                        <div className="rounded-lg border border-border p-4">
                            <p className="text-sm text-muted-foreground">
                                {loadError}
                            </p>
                            <div className="mt-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void loadAllData()}
                                >
                                    Retry
                                </Button>
                            </div>
                        </div>
                    </div>
                ) : (
            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
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
                                value={effectiveName}
                                onChange={(e) => setLedgerName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        void handleSaveGeneral();
                                    }
                                }}
                            />
                        </div>

                        {/* Currency */}
                        <div className="space-y-1.5">
                            <Label>Currency code</Label>
                            <CurrencySelect
                                value={effectiveCurrency}
                                onValueChange={setCurrencyCode}
                            />
                            {effectiveCurrency !==
                                (ledgerSettings?.currency_code ?? ledger.currency_code) && (
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
                                type="number"
                                inputMode="decimal"
                                min={1}
                                max={31}
                                value={effectiveCycleDay}
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
                                onClick={() => void handleSaveGeneral()}
                                disabled={
                                    isSavingGeneral || !effectiveName.trim()
                                }
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
                        {accountTypes.length === 0 && !showAddForm && (
                            <p className="py-4 text-sm text-muted-foreground">
                                No account types yet.
                            </p>
                        )}

                        {accountTypes.map((accountType) => {
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
                                        void handleDrop(e, accountType.id)
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
                                                        void saveEdit();
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
                                                onClick={() => void saveEdit()}
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

                                            <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
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
                                            void handleAddAccountType();
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
                                    onClick={() => void handleAddAccountType()}
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
                                    onClick={() =>
                                        void handleRemoveSampleData()
                                    }
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

                    <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                        <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-destructive">
                                    Delete this workspace
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Permanently deletes all accounts,
                                    transactions, categories, budgets, and other
                                    data. This action cannot be undone.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
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
                )}
            </BoneSkeleton>

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
                            onClick={() => void handleDeleteAccountType()}
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
                            onClick={() => void handleDeleteLedger()}
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
