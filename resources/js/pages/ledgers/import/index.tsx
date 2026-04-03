import { Head, router, useHttp, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    accounts as importAccountsLoader,
    history as importHistoryLoader,
    savedMappings as savedMappingsLoader,
} from '@/actions/App/Http/Controllers/Api/V1/Ledger/ImportController';
import {
    destroyMapping as destroyImportMapping,
    execute as executeImport,
    parse as parseImport,
    storeMapping as storeImportMapping,
} from '@/actions/App/Http/Controllers/Ledger/ImportController';
import { SearchableSelect } from '@/components/searchable-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { mapInertiaErrorsArray } from '@/lib/utils';
import {
    createInitialImportPageState,
    emptyMapping,
    deriveMapping,
    NOT_MAPPED,
    
    
    shouldBlockImportStepTwo
} from '@/pages/ledgers/import/page-state';
import type {Mapping, ParseResult} from '@/pages/ledgers/import/page-state';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { Account, BreadcrumbItem } from '@/types';

type SavedMapping = {
    id: number;
    name: string;
    mapping: Record<string, string>;
};

type ImportHistoryRecord = {
    id: number;
    filename: string;
    row_count: number;
    imported_count: number;
    skipped_count: number;
    mapping_used: Record<string, string> | null;
    imported_at: string;
};

type ApiEnvelope<T> = {
    data: T;
};

type ImportPageProps = {
    currentLedger: { id: number; name: string };
    flash: {
        success: string | null;
        error: string | null;
        import_parse_result?: ParseResult | null;
    };
    parseResult?: ParseResult | null;
};

const TARGET_FIELDS: {
    key: keyof Mapping;
    label: string;
    required: boolean;
}[] = [
    { key: 'date', label: 'Date', required: true },
    { key: 'amount', label: 'Amount', required: true },
    { key: 'description', label: 'Description', required: false },
    { key: 'category', label: 'Category', required: false },
    { key: 'payee', label: 'Payee', required: false },
    { key: 'type', label: 'Transaction Type', required: false },
];

function ImportLoadingSkeleton() {
    return (
        <div className="space-y-6">
            <Skeleton className="h-8 w-48" />
            <Card>
                <CardContent className="p-6">
                    <Skeleton className="h-40 w-full" />
                </CardContent>
            </Card>
        </div>
    );
}

function LoaderErrorCard({
    message,
    onRetry,
}: {
    readonly message: string;
    readonly onRetry: () => void;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-3 p-6">
                <p className="text-sm text-muted-foreground">{message}</p>
                <div>
                    <Button variant="outline" size="sm" onClick={onRetry}>
                        Retry
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

export default function ImportIndex() {
    const page = usePage<ImportPageProps>();
    const {
        currentLedger,
        flash,
        parseResult: pageParseResult,
    } = page.props;
    const ledger = currentLedger!;
    const latestParseResult =
        pageParseResult ?? flash.import_parse_result ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
        { title: 'Import', href: '#' },
    ];

    const accountsLoaderState = useHttp<Record<string, never>, ApiEnvelope<Account[]>>({});
    const savedMappingsLoaderState = useHttp<Record<string, never>, ApiEnvelope<SavedMapping[]>>({});
    const historyLoaderState = useHttp<Record<string, never>, ApiEnvelope<ImportHistoryRecord[]>>({});

    const initialPageState = createInitialImportPageState(latestParseResult);

    const [step, setStep] = useState<1 | 2 | 3>(initialPageState.step);
    const [isLoading, setIsLoading] = useState(false);
    const [parseResult, setParseResult] = useState<ParseResult | null>(
        initialPageState.parseResult,
    );
    const [mapping, setMapping] = useState<Mapping>(initialPageState.mapping);
    const [accountId, setAccountId] = useState<string>('');
    const [skipDuplicates, setSkipDuplicates] = useState(true);
    const [dragOver, setDragOver] = useState(false);
    const [parseError, setParseError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Import mapping state
    const [saveMappingName, setSaveMappingName] = useState('');
    const [isSavingMapping, setIsSavingMapping] = useState(false);
    const [showSaveMappingInput, setShowSaveMappingInput] = useState(false);
    const [detectedBank, setDetectedBank] = useState<string | null>(
        initialPageState.detectedBank,
    );

    // Import history state
    const [historyOpen, setHistoryOpen] = useState(false);
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [savedMappings, setSavedMappings] = useState<SavedMapping[]>([]);
    const [importHistory, setImportHistory] = useState<ImportHistoryRecord[]>([]);
    const [accountsError, setAccountsError] = useState<string | null>(null);
    const [savedMappingsError, setSavedMappingsError] = useState<string | null>(null);
    const [historyError, setHistoryError] = useState<string | null>(null);
    const [hasLoadedAccounts, setHasLoadedAccounts] = useState(false);
    const [hasLoadedSavedMappings, setHasLoadedSavedMappings] =
        useState(false);
    const [hasLoadedHistory, setHasLoadedHistory] = useState(false);
    const accountsRequestIdRef = useRef(0);
    const savedMappingsRequestIdRef = useRef(0);
    const historyRequestIdRef = useRef(0);
    const previousLedgerIdRef = useRef(ledger.id);

    useEffect(() => {
        const nextPageState = createInitialImportPageState(latestParseResult);

        accountsLoaderState.cancel();
        savedMappingsLoaderState.cancel();
        historyLoaderState.cancel();
        accountsRequestIdRef.current += 1;
        savedMappingsRequestIdRef.current += 1;
        historyRequestIdRef.current += 1;

        setStep(nextPageState.step);
        setIsLoading(false);
        setParseResult(nextPageState.parseResult);
        setMapping(nextPageState.mapping);
        setAccountId('');
        setSkipDuplicates(true);
        setDragOver(false);
        setParseError(null);
        setSaveMappingName('');
        setIsSavingMapping(false);
        setShowSaveMappingInput(false);
        setDetectedBank(nextPageState.detectedBank);
        setHistoryOpen(false);
        setAccounts([]);
        setSavedMappings([]);
        setImportHistory([]);
        setAccountsError(null);
        setSavedMappingsError(null);
        setHistoryError(null);
        setHasLoadedAccounts(false);
        setHasLoadedSavedMappings(false);
        setHasLoadedHistory(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [latestParseResult, ledger.id]);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.error, flash.success]);

    async function loadAccounts(options?: { force?: boolean }): Promise<boolean> {
        if (
            !options?.force &&
            (hasLoadedAccounts || accountsLoaderState.processing)
        ) {
            return true;
        }

        let cancelled = false;
        const requestId = accountsRequestIdRef.current + 1;

        accountsRequestIdRef.current = requestId;
        accountsLoaderState.cancel();
        setAccountsError(null);

        try {
            await accountsLoaderState.get(importAccountsLoader.url(ledger.id), {
                onCancel: () => {
                    cancelled = true;
                },
            });

            if (!cancelled && accountsRequestIdRef.current === requestId) {
                setAccounts(accountsLoaderState.response?.data ?? []);
                setHasLoadedAccounts(true);
            }

            return true;
        } catch {
            if (!cancelled && accountsRequestIdRef.current === requestId) {
                setAccountsError('Failed to load accounts.');
            }

            return false;
        }
    }

    async function loadSavedMappings(options?: {
        force?: boolean;
    }): Promise<boolean> {
        if (
            !options?.force &&
            (hasLoadedSavedMappings || savedMappingsLoaderState.processing)
        ) {
            return true;
        }

        let cancelled = false;
        const requestId = savedMappingsRequestIdRef.current + 1;

        savedMappingsRequestIdRef.current = requestId;
        savedMappingsLoaderState.cancel();
        setSavedMappingsError(null);

        try {
            await savedMappingsLoaderState.get(savedMappingsLoader.url(ledger.id), {
                onCancel: () => {
                    cancelled = true;
                },
            });

            if (
                !cancelled &&
                savedMappingsRequestIdRef.current === requestId
            ) {
                setSavedMappings(savedMappingsLoaderState.response?.data ?? []);
                setHasLoadedSavedMappings(true);
            }

            return true;
        } catch {
            if (
                !cancelled &&
                savedMappingsRequestIdRef.current === requestId
            ) {
                setSavedMappingsError('Failed to load saved mappings.');
            }

            return false;
        }
    }

    async function loadImportHistory(options?: {
        force?: boolean;
    }): Promise<boolean> {
        if (
            !options?.force &&
            (hasLoadedHistory || historyLoaderState.processing)
        ) {
            return true;
        }

        let cancelled = false;
        const requestId = historyRequestIdRef.current + 1;

        historyRequestIdRef.current = requestId;
        historyLoaderState.cancel();
        setHistoryError(null);

        try {
            await historyLoaderState.get(importHistoryLoader.url(ledger.id), {
                onCancel: () => {
                    cancelled = true;
                },
            });

            if (!cancelled && historyRequestIdRef.current === requestId) {
                setImportHistory(historyLoaderState.response?.data ?? []);
                setHasLoadedHistory(true);
            }

            return true;
        } catch {
            if (!cancelled && historyRequestIdRef.current === requestId) {
                setHistoryError('Failed to load import history.');
            }

            return false;
        }
    }

    useEffect(() => {
        void loadImportHistory({ force: true });

        return () => {
            accountsLoaderState.cancel();
            savedMappingsLoaderState.cancel();
            historyLoaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ledger.id]);

    useEffect(() => {
        const ledgerChanged = previousLedgerIdRef.current !== ledger.id;

        previousLedgerIdRef.current = ledger.id;

        if (step >= 2) {
            void loadAccounts({ force: ledgerChanged });
        }

        if (step === 2) {
            void loadSavedMappings({ force: ledgerChanged });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ledger.id, step]);

    const resetImportState = () => {
        setStep(1);
        setParseResult(null);
        setMapping(emptyMapping());
        setAccountId('');
        setSkipDuplicates(true);
        setDetectedBank(null);
        setParseError(null);
        setShowSaveMappingInput(false);
        setSaveMappingName('');
    };

    const handleFile = async (file: File) => {
        setParseError(null);
        setIsLoading(true);
        setDetectedBank(null);

        router.post(
            parseImport.url(ledger.id),
            { file },
            {
                forceFormData: true,
                only: ['parseResult', 'flash'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    const nextPage = page.props as ImportPageProps;
                    const nextParseResult =
                        nextPage.parseResult ??
                        nextPage.flash?.import_parse_result ??
                        null;

                    if (!nextParseResult) {
                        return;
                    }

                    const nextMappingState = deriveMapping(nextParseResult);

                    setParseResult(nextParseResult);
                    setMapping(nextMappingState.mapping);
                    setDetectedBank(nextMappingState.detectedBank);
                    setParseError(null);
                    setStep(2);
                },
                onError: (errors) => {
                    const mapped = mapInertiaErrorsArray(errors);

                    setParseError(
                        mapped.file?.[0] ??
                            'Failed to parse CSV. Please check the file and try again.',
                    );
                },
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (file) {
            void handleFile(file);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(false);
        const file = e.dataTransfer.files[0];

        if (file) {
            void handleFile(file);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setDragOver(true);
    };

    const handleDragLeave = () => {
        setDragOver(false);
    };

    const handleMappingChange = (field: keyof Mapping, value: string) => {
        setMapping((prev) => ({ ...prev, [field]: value }));
        setDetectedBank(null);
    };

    const handleLoadSavedMapping = (mappingId: string) => {
        const saved = savedMappings.find((m) => String(m.id) === mappingId);

        if (!saved) {
            return;
        }

        setMapping({
            date: saved.mapping.date ?? NOT_MAPPED,
            amount: saved.mapping.amount ?? NOT_MAPPED,
            description: saved.mapping.description ?? NOT_MAPPED,
            category: saved.mapping.category ?? NOT_MAPPED,
            payee: saved.mapping.payee ?? NOT_MAPPED,
            type: saved.mapping.type ?? NOT_MAPPED,
        });
        setDetectedBank(null);
        toast.success(`Loaded mapping "${saved.name}"`);
    };

    const handleSaveMapping = async () => {
        if (!saveMappingName.trim()) {
            return;
        }

        setIsSavingMapping(true);

        router.post(
            storeImportMapping.url(ledger.id),
            {
                name: saveMappingName.trim(),
                mapping: {
                    date: mapping.date,
                    amount: mapping.amount,
                    description:
                        mapping.description !== NOT_MAPPED
                            ? mapping.description
                            : undefined,
                    category:
                        mapping.category !== NOT_MAPPED
                            ? mapping.category
                            : undefined,
                    payee:
                        mapping.payee !== NOT_MAPPED
                            ? mapping.payee
                            : undefined,
                    type:
                        mapping.type !== NOT_MAPPED ? mapping.type : undefined,
                },
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    setSaveMappingName('');
                    setShowSaveMappingInput(false);
                    void loadSavedMappings({ force: true });
                },
                onError: (errors) => {
                    const mapped = mapInertiaErrorsArray(errors);

                    toast.error(
                        mapped.name?.[0] ??
                            mapped.mapping?.[0] ??
                            'Failed to save mapping',
                    );
                },
                onFinish: () => setIsSavingMapping(false),
            },
        );
    };

    const handleDeleteMapping = async (mappingId: number) => {
        router.delete(
            destroyImportMapping.url({
                ledger: ledger.id,
                importMapping: mappingId,
            }),
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    void loadSavedMappings({ force: true });
                },
                onError: () => {
                    toast.error('Failed to delete mapping');
                },
            },
        );
    };

    const canProceedToPreview =
        mapping.date !== NOT_MAPPED &&
        mapping.amount !== NOT_MAPPED &&
        accountId !== '';
    const shouldBlockStepTwo = shouldBlockImportStepTwo({
        accountsError,
        accountsCount: accounts.length,
        savedMappingsError,
        savedMappingsCount: savedMappings.length,
    });
    const showStepTwoSkeleton =
        step === 2 &&
        parseResult !== null &&
        !hasLoadedAccounts &&
        accountsLoaderState.processing;

    const getPreviewValue = (row: string[], field: keyof Mapping): string => {
        if (!parseResult) {
            return '';
        }

        const col = mapping[field];

        if (!col || col === NOT_MAPPED) {
            return '\u2014';
        }

        const idx = parseResult.headers.indexOf(col);

        const raw = idx >= 0 ? (row[idx] ?? '') : '';

        if (!raw) {
            return '\u2014';
        }

        if (field === 'date') {
            const parsed = new Date(raw);

            if (!isNaN(parsed.getTime())) {
                return parsed.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });
            }
        }

        return raw;
    };

    const handleConfirmImport = async () => {
        if (!parseResult) {
            return;
        }

        setIsLoading(true);

        router.post(
            executeImport.url(ledger.id),
            {
                file_path: parseResult.file_path,
                account_id: accountId,
                mapping: {
                    date: mapping.date,
                    amount: mapping.amount,
                    description:
                        mapping.description !== NOT_MAPPED
                            ? mapping.description
                            : null,
                    category:
                        mapping.category !== NOT_MAPPED
                            ? mapping.category
                            : null,
                    payee: mapping.payee !== NOT_MAPPED ? mapping.payee : null,
                    type: mapping.type !== NOT_MAPPED ? mapping.type : null,
                },
                skip_duplicates: skipDuplicates,
            },
            {
                only: ['parseResult', 'flash'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: async () => {
                    resetImportState();
                    await loadImportHistory({ force: true });
                },
                onError: (errors) => {
                    const mapped = mapInertiaErrorsArray(errors);

                    toast.error(
                        mapped.file_path?.[0] ??
                            mapped.account_id?.[0] ??
                            'Failed to import transactions.',
                    );
                },
                onFinish: () => setIsLoading(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Import Transactions — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <>
                    {/* Step indicator */}
                    <div className="flex items-center justify-center gap-2 sm:gap-4">
                        {[
                            {
                                num: 1,
                                label: 'Upload CSV',
                                description:
                                    'Select or drag your bank statement CSV file',
                            },
                            {
                                num: 2,
                                label: 'Map Columns',
                                description:
                                    'Match your CSV columns to transaction fields',
                            },
                            {
                                num: 3,
                                label: 'Preview & Confirm',
                                description:
                                    'Review your data and import into your account',
                            },
                        ].map(({ num, label, description }, idx) => (
                            <div
                                key={num}
                                className="flex items-center gap-1 sm:gap-2"
                            >
                                <div className="relative">
                                    {step === num && (
                                        <span className="absolute inset-0 animate-ping rounded-full bg-primary/30" />
                                    )}
                                    <div
                                        className={`relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold sm:h-8 sm:w-8 sm:text-sm ${
                                            step === num
                                                ? 'bg-primary text-primary-foreground'
                                                : step > num
                                                  ? 'bg-emerald-500 text-white'
                                                  : 'bg-muted text-muted-foreground'
                                        }`}
                                    >
                                        {step > num ? (
                                            <Check className="size-4" />
                                        ) : (
                                            num
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <span
                                        className={`text-sm ${step === num ? 'font-medium' : step > num ? 'font-medium text-emerald-500' : 'text-muted-foreground'}`}
                                    >
                                        {label}
                                    </span>
                                    <p
                                        className={`text-xs ${step === num ? 'text-muted-foreground' : 'text-muted-foreground/60'}`}
                                    >
                                        {description}
                                    </p>
                                </div>
                                {idx < 2 && (
                                    <div
                                        className={`mx-1 h-px w-4 sm:mx-2 sm:w-8 ${
                                            step > idx + 1
                                                ? 'bg-emerald-500'
                                                : 'border-t border-dashed border-border bg-transparent'
                                        }`}
                                    />
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Step 1: Upload */}
                    {step === 1 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Upload CSV File</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    className={`flex cursor-pointer flex-col items-center justify-center gap-4 rounded-lg border-2 border-dashed p-12 transition-colors ${
                                        dragOver
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-primary/50 hover:bg-muted/30'
                                    }`}
                                    onDrop={handleDrop}
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onClick={() =>
                                        fileInputRef.current?.click()
                                    }
                                >
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".csv,.txt"
                                        className="hidden"
                                        onChange={handleFileInput}
                                    />
                                    {isLoading ? (
                                        <p className="text-muted-foreground">
                                            Parsing file...
                                        </p>
                                    ) : (
                                        <>
                                            <div className="text-4xl">
                                                &#128196;
                                            </div>
                                            <div className="text-center">
                                                <p className="font-medium">
                                                    Drop your CSV file here, or
                                                    click to browse
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    Supports .csv and .txt files
                                                    up to 5MB
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>
                                {parseError && (
                                    <p className="mt-3 text-sm text-destructive">
                                        {parseError}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Step 2: Map Columns */}
                    {step === 2 && parseResult && (
                        showStepTwoSkeleton ? (
                            <ImportLoadingSkeleton />
                        ) : shouldBlockStepTwo ? (
                            <LoaderErrorCard
                                message={accountsError ?? 'Failed to load accounts.'}
                                onRetry={() => {
                                    void loadAccounts({ force: true });
                                }}
                            />
                        ) : (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Map Columns</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-6">
                                    <p className="text-sm text-muted-foreground">
                                        Your CSV has {parseResult.total_rows}{' '}
                                        rows and {parseResult.headers.length}{' '}
                                        columns. Map each field below.
                                    </p>

                                    {/* Detected bank notice */}
                                    {detectedBank && (
                                        <Alert>
                                            <AlertTitle>
                                                Bank format detected
                                            </AlertTitle>
                                            <AlertDescription>
                                                Detected {detectedBank} format —
                                                mapping auto-applied.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Load saved mapping */}
                                    {savedMappingsError && (
                                        <Alert>
                                            <AlertTitle>
                                                Saved mappings unavailable
                                            </AlertTitle>
                                            <AlertDescription className="flex items-center justify-between gap-3">
                                                <span>{savedMappingsError}</span>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        void loadSavedMappings({
                                                            force: true,
                                                        });
                                                    }}
                                                >
                                                    Retry
                                                </Button>
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {savedMappings.length > 0 && (
                                        <div className="flex flex-col gap-2">
                                            <Label>Load saved mapping</Label>
                                            <div className="flex items-center gap-2">
                                                <SearchableSelect
                                                    options={savedMappings.map(
                                                        (sm) => ({
                                                            value: String(
                                                                sm.id,
                                                            ),
                                                            label: sm.name,
                                                        }),
                                                    )}
                                                    value={null}
                                                    onValueChange={(val) => {
                                                        if (val) {
                                                            handleLoadSavedMapping(
                                                                val,
                                                            );
                                                        }
                                                    }}
                                                    placeholder="Select a saved mapping..."
                                                    searchPlaceholder="Search mappings..."
                                                    className="w-full max-w-xs"
                                                />
                                                {savedMappings.length > 0 && (
                                                    <SearchableSelect
                                                        options={savedMappings.map(
                                                            (sm) => ({
                                                                value: String(
                                                                    sm.id,
                                                                ),
                                                                label: `Delete "${sm.name}"`,
                                                            }),
                                                        )}
                                                        value={null}
                                                        onValueChange={(
                                                            val,
                                                        ) => {
                                                            if (val) {
                                                                void handleDeleteMapping(
                                                                    Number(val),
                                                                );
                                                            }
                                                        }}
                                                        placeholder="Delete..."
                                                        searchPlaceholder="Search mappings..."
                                                        className="w-auto"
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Account selector */}
                                    <div className="flex flex-col gap-2">
                                        <Label>
                                            Account{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <SearchableSelect
                                            options={accounts.map(
                                                (account) => ({
                                                    value: String(account.id),
                                                    label: account.name,
                                                    color: account.color,
                                                }),
                                            )}
                                            value={accountId || null}
                                            onValueChange={(val) =>
                                                setAccountId(val ?? '')
                                            }
                                            placeholder="Select account..."
                                            searchPlaceholder="Search accounts..."
                                            className="w-full max-w-xs"
                                        />
                                    </div>

                                    {/* Field mappings */}
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        {TARGET_FIELDS.map(
                                            ({ key, label, required }) => {
                                                const headerOptions =
                                                    parseResult.headers.map(
                                                        (header) => ({
                                                            value: header,
                                                            label: header,
                                                        }),
                                                    );

                                                const options = !required
                                                    ? [
                                                          {
                                                              value: NOT_MAPPED,
                                                              label: '— not mapped —',
                                                          },
                                                          ...headerOptions,
                                                      ]
                                                    : headerOptions;

                                                const currentValue =
                                                    mapping[key] ===
                                                        NOT_MAPPED && required
                                                        ? null
                                                        : mapping[key];

                                                return (
                                                    <div
                                                        key={key}
                                                        className="flex flex-col gap-2"
                                                    >
                                                        <Label>
                                                            {label}{' '}
                                                            {required && (
                                                                <span className="text-destructive">
                                                                    *
                                                                </span>
                                                            )}
                                                        </Label>
                                                        <SearchableSelect
                                                            options={options}
                                                            value={currentValue}
                                                            onValueChange={(
                                                                val,
                                                            ) =>
                                                                handleMappingChange(
                                                                    key,
                                                                    val ??
                                                                        NOT_MAPPED,
                                                                )
                                                            }
                                                            placeholder="— not mapped —"
                                                            searchPlaceholder="Search columns..."
                                                        />
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>

                                    {/* Skip duplicates */}
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="skip-duplicates"
                                            checked={skipDuplicates}
                                            onCheckedChange={(checked) =>
                                                setSkipDuplicates(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label htmlFor="skip-duplicates">
                                            Skip duplicate transactions (same
                                            date, amount, and description)
                                        </Label>
                                    </div>

                                    {/* Save mapping */}
                                    <div className="flex flex-col gap-2">
                                        {!showSaveMappingInput ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-fit"
                                                onClick={() =>
                                                    setShowSaveMappingInput(
                                                        true,
                                                    )
                                                }
                                                disabled={
                                                    mapping.date ===
                                                        NOT_MAPPED ||
                                                    mapping.amount ===
                                                        NOT_MAPPED
                                                }
                                            >
                                                Save this mapping
                                            </Button>
                                        ) : (
                                            <div className="flex items-center gap-2">
                                                <Input
                                                    placeholder="Mapping name..."
                                                    value={saveMappingName}
                                                    onChange={(e) =>
                                                        setSaveMappingName(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="max-w-xs"
                                                    onKeyDown={(e) => {
                                                        if (e.key === 'Enter') {
                                                            void handleSaveMapping();
                                                        }
                                                    }}
                                                />
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        void handleSaveMapping()
                                                    }
                                                    disabled={
                                                        !saveMappingName.trim() ||
                                                        isSavingMapping
                                                    }
                                                >
                                                    {isSavingMapping
                                                        ? 'Saving...'
                                                        : 'Save'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setShowSaveMappingInput(
                                                            false,
                                                        );
                                                        setSaveMappingName('');
                                                    }}
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex gap-3">
                                        <Button
                                            variant="outline"
                                            onClick={() => setStep(1)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            disabled={!canProceedToPreview}
                                            onClick={() => setStep(3)}
                                        >
                                            Preview
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )
                    )}

                    {/* Step 3: Preview */}
                    {step === 3 && parseResult && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Preview & Confirm</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-6">
                                    <p className="text-sm text-muted-foreground">
                                        Showing the first{' '}
                                        {parseResult.preview_rows.length} of{' '}
                                        {parseResult.total_rows} rows. Review
                                        before importing.
                                    </p>

                                    <div className="overflow-x-auto rounded-md border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>
                                                        Amount
                                                    </TableHead>
                                                    <TableHead>
                                                        Description
                                                    </TableHead>
                                                    <TableHead>
                                                        Category
                                                    </TableHead>
                                                    <TableHead>Payee</TableHead>
                                                    <TableHead>Type</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {parseResult.preview_rows.map(
                                                    (row, i) => (
                                                        <TableRow key={i}>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'date',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'amount',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'description',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'category',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'payee',
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {getPreviewValue(
                                                                    row,
                                                                    'type',
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>

                                    <div className="flex gap-3">
                                        <Button
                                            variant="outline"
                                            onClick={() => setStep(2)}
                                        >
                                            Back
                                        </Button>
                                        <Button
                                            onClick={() =>
                                                void handleConfirmImport()
                                            }
                                            disabled={isLoading}
                                        >
                                            {isLoading
                                                ? 'Importing...'
                                                : `Import ${parseResult.total_rows} transactions`}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                    )}

                    {/* Import History */}
                    {historyError && importHistory.length === 0 ? (
                        <LoaderErrorCard
                            message={historyError}
                            onRetry={() => {
                                void loadImportHistory({ force: true });
                            }}
                        />
                    ) : importHistory.length > 0 ? (
                        <Collapsible
                            open={historyOpen}
                            onOpenChange={setHistoryOpen}
                        >
                            <Card>
                                <CardHeader>
                                    <CollapsibleTrigger asChild>
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between"
                                        >
                                            <CardTitle>Import History</CardTitle>
                                            <span className="text-sm text-muted-foreground">
                                                {historyOpen
                                                    ? 'Hide'
                                                    : `Show (${importHistory.length})`}
                                            </span>
                                        </button>
                                    </CollapsibleTrigger>
                                </CardHeader>
                                <CollapsibleContent>
                                    <CardContent>
                                        <div className="overflow-x-auto rounded-md border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Filename
                                                        </TableHead>
                                                        <TableHead>
                                                            Total Rows
                                                        </TableHead>
                                                        <TableHead>
                                                            Imported
                                                        </TableHead>
                                                        <TableHead>
                                                            Skipped
                                                        </TableHead>
                                                        <TableHead>
                                                            Date
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {importHistory.map(
                                                        (record) => (
                                                            <TableRow
                                                                key={record.id}
                                                            >
                                                                <TableCell className="font-medium">
                                                                    {
                                                                        record.filename
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    {
                                                                        record.row_count
                                                                    }
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge variant="secondary">
                                                                        {
                                                                            record.imported_count
                                                                        }
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {record.skipped_count >
                                                                    0 ? (
                                                                        <Badge variant="outline">
                                                                            {
                                                                                record.skipped_count
                                                                            }
                                                                        </Badge>
                                                                    ) : (
                                                                        '0'
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-muted-foreground">
                                                                    {new Date(
                                                                        record.imported_at,
                                                                    ).toLocaleDateString(
                                                                        undefined,
                                                                        {
                                                                            year: 'numeric',
                                                                            month: 'short',
                                                                            day: 'numeric',
                                                                            hour: '2-digit',
                                                                            minute: '2-digit',
                                                                        },
                                                                    )}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </CollapsibleContent>
                            </Card>
                        </Collapsible>
                    ) : null}
                </>
            </div>
        </AppLayout>
    );
}
