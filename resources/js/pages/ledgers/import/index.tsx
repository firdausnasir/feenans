import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    create as importCreate,
    parse as importParse,
    store as importStore,
} from '@/routes/ledgers/import';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { Account, BreadcrumbItem, Category, Ledger, Payee } from '@/types';

type ParseResult = {
    headers: string[];
    preview_rows: string[][];
    total_rows: number;
    file_path: string;
    detected_bank?: string;
    suggested_mapping?: Record<string, string>;
};

type Mapping = {
    date: string;
    amount: string;
    description: string;
    payee: string;
    type: string;
};

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

const NOT_MAPPED = '__not_mapped__';

const TARGET_FIELDS: {
    key: keyof Mapping;
    label: string;
    required: boolean;
}[] = [
    { key: 'date', label: 'Date', required: true },
    { key: 'amount', label: 'Amount', required: true },
    { key: 'description', label: 'Description', required: false },
    { key: 'payee', label: 'Payee', required: false },
    { key: 'type', label: 'Transaction Type', required: false },
];

export default function ImportIndex({
    ledger,
    accounts,
    importHistory = [],
}: {
    ledger: Ledger;
    accounts: Account[];
    categories: Category[];
    payees: Payee[];
    importHistory?: ImportHistoryRecord[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Transactions', href: transactionsIndex.url(ledger.id) },
        { title: 'Import', href: importCreate.url(ledger.id) },
    ];

    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [isLoading, setIsLoading] = useState(false);
    const [parseResult, setParseResult] = useState<ParseResult | null>(null);
    const [mapping, setMapping] = useState<Mapping>({
        date: NOT_MAPPED,
        amount: NOT_MAPPED,
        description: NOT_MAPPED,
        payee: NOT_MAPPED,
        type: NOT_MAPPED,
    });
    const [accountId, setAccountId] = useState<string>('');
    const [skipDuplicates, setSkipDuplicates] = useState(true);
    const [dragOver, setDragOver] = useState(false);
    const [parseError, setParseError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Import mapping state
    const [savedMappings, setSavedMappings] = useState<SavedMapping[]>([]);
    const [saveMappingName, setSaveMappingName] = useState('');
    const [isSavingMapping, setIsSavingMapping] = useState(false);
    const [showSaveMappingInput, setShowSaveMappingInput] = useState(false);
    const [detectedBank, setDetectedBank] = useState<string | null>(null);

    // Import history state
    const [historyOpen, setHistoryOpen] = useState(false);

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    const fetchSavedMappings = useCallback(() => {
        fetch(`/ledgers/${ledger.id}/import/mappings`, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        })
            .then((res) => {
                if (res.ok) {
                    return res.json();
                }
                return [];
            })
            .then((data: SavedMapping[]) => setSavedMappings(data))
            .catch(() => setSavedMappings([]));
    }, [ledger.id, csrfToken]);

    useEffect(() => {
        fetchSavedMappings();
    }, [fetchSavedMappings]);

    const handleFile = async (file: File) => {
        setParseError(null);
        setIsLoading(true);
        setDetectedBank(null);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(importParse.url(ledger.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                const message =
                    errorData?.errors?.file?.[0] ??
                    errorData?.message ??
                    'Failed to parse CSV. Please check the file and try again.';
                setParseError(message);
                return;
            }

            const data: ParseResult = await response.json();
            setParseResult(data);

            // If bank format detected, use suggested mapping
            if (data.detected_bank && data.suggested_mapping) {
                setDetectedBank(data.detected_bank);
                const bankMapping: Mapping = {
                    date: data.suggested_mapping.date ?? NOT_MAPPED,
                    amount: data.suggested_mapping.amount ?? NOT_MAPPED,
                    description:
                        data.suggested_mapping.description ?? NOT_MAPPED,
                    payee: data.suggested_mapping.payee ?? NOT_MAPPED,
                    type: data.suggested_mapping.type ?? NOT_MAPPED,
                };
                setMapping(bankMapping);
                setStep(2);
                return;
            }

            // Auto-detect columns by common header names
            const autoMapping: Mapping = {
                date: NOT_MAPPED,
                amount: NOT_MAPPED,
                description: NOT_MAPPED,
                payee: NOT_MAPPED,
                type: NOT_MAPPED,
            };

            for (const header of data.headers) {
                const lower = header.toLowerCase();
                if (lower.includes('date') && autoMapping.date === NOT_MAPPED) {
                    autoMapping.date = header;
                } else if (
                    lower.includes('amount') &&
                    autoMapping.amount === NOT_MAPPED
                ) {
                    autoMapping.amount = header;
                } else if (
                    (lower.includes('description') ||
                        lower.includes('memo') ||
                        lower.includes('narration')) &&
                    autoMapping.description === NOT_MAPPED
                ) {
                    autoMapping.description = header;
                } else if (
                    (lower.includes('payee') ||
                        lower.includes('merchant') ||
                        lower.includes('vendor')) &&
                    autoMapping.payee === NOT_MAPPED
                ) {
                    autoMapping.payee = header;
                } else if (
                    (lower.includes('type') ||
                        lower.includes('transaction type')) &&
                    autoMapping.type === NOT_MAPPED
                ) {
                    autoMapping.type = header;
                }
            }

            setMapping(autoMapping);
            setStep(2);
        } catch {
            setParseError('An unexpected error occurred. Please try again.');
        } finally {
            setIsLoading(false);
        }
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

        try {
            const response = await fetch(
                `/ledgers/${ledger.id}/import/mappings`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        name: saveMappingName.trim(),
                        mapping: {
                            date: mapping.date,
                            amount: mapping.amount,
                            description:
                                mapping.description !== NOT_MAPPED
                                    ? mapping.description
                                    : undefined,
                            payee:
                                mapping.payee !== NOT_MAPPED
                                    ? mapping.payee
                                    : undefined,
                            type:
                                mapping.type !== NOT_MAPPED
                                    ? mapping.type
                                    : undefined,
                        },
                    }),
                },
            );

            if (response.ok) {
                toast.success(`Mapping "${saveMappingName.trim()}" saved`);
                setSaveMappingName('');
                setShowSaveMappingInput(false);
                fetchSavedMappings();
            } else {
                toast.error('Failed to save mapping');
            }
        } catch {
            toast.error('Failed to save mapping');
        } finally {
            setIsSavingMapping(false);
        }
    };

    const handleDeleteMapping = async (mappingId: number) => {
        try {
            const response = await fetch(
                `/ledgers/${ledger.id}/import/mappings/${mappingId}`,
                {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                },
            );

            if (response.ok) {
                toast.success('Mapping deleted');
                fetchSavedMappings();
            }
        } catch {
            toast.error('Failed to delete mapping');
        }
    };

    const canProceedToPreview =
        mapping.date !== NOT_MAPPED &&
        mapping.amount !== NOT_MAPPED &&
        accountId !== '';

    const getPreviewValue = (row: string[], field: keyof Mapping): string => {
        if (!parseResult) {
            return '';
        }
        const col = mapping[field];
        if (!col || col === NOT_MAPPED) {
            return '\u2014';
        }
        const idx = parseResult.headers.indexOf(col);
        return idx >= 0 ? (row[idx] ?? '\u2014') : '\u2014';
    };

    const handleConfirmImport = () => {
        if (!parseResult) {
            return;
        }

        const payload = {
            file_path: parseResult.file_path,
            account_id: accountId,
            mapping: {
                date: mapping.date,
                amount: mapping.amount,
                description:
                    mapping.description !== NOT_MAPPED
                        ? mapping.description
                        : null,
                category: null,
                payee: mapping.payee !== NOT_MAPPED ? mapping.payee : null,
                type: mapping.type !== NOT_MAPPED ? mapping.type : null,
            },
            skip_duplicates: skipDuplicates,
        };

        router.post(importStore.url(ledger.id), payload, {
            onSuccess: () => {
                toast.success('Transactions imported');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Import Transactions — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Import Transactions"
                        description="Import transactions from a CSV file in 3 easy steps."
                    />
                </div>

                {/* Step indicator */}
                <div className="flex items-center gap-2 sm:gap-4">
                    {[
                        { num: 1, label: 'Upload CSV' },
                        { num: 2, label: 'Map Columns' },
                        { num: 3, label: 'Preview & Confirm' },
                    ].map(({ num, label }, idx) => (
                        <div
                            key={num}
                            className="flex items-center gap-1 sm:gap-2"
                        >
                            <div
                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold sm:h-8 sm:w-8 sm:text-sm ${
                                    step === num
                                        ? 'bg-primary text-primary-foreground'
                                        : step > num
                                          ? 'bg-primary/20 text-primary'
                                          : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {num}
                            </div>
                            <span
                                className={`hidden text-sm sm:inline ${step === num ? 'font-medium' : 'text-muted-foreground'}`}
                            >
                                {label}
                            </span>
                            {idx < 2 && (
                                <div className="mx-1 h-px w-4 bg-border sm:mx-2 sm:w-8" />
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
                                onClick={() => fileInputRef.current?.click()}
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
                                                Supports .csv and .txt files up
                                                to 5MB
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
                    <Card>
                        <CardHeader>
                            <CardTitle>Map Columns</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <p className="text-sm text-muted-foreground">
                                Your CSV has {parseResult.total_rows} rows and{' '}
                                {parseResult.headers.length} columns. Map each
                                field below.
                            </p>

                            {/* Detected bank notice */}
                            {detectedBank && (
                                <Alert>
                                    <AlertTitle>
                                        Bank format detected
                                    </AlertTitle>
                                    <AlertDescription>
                                        Detected {detectedBank} format — mapping
                                        auto-applied.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Load saved mapping */}
                            {savedMappings.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <Label>Load saved mapping</Label>
                                    <div className="flex items-center gap-2">
                                        <Select
                                            onValueChange={
                                                handleLoadSavedMapping
                                            }
                                        >
                                            <SelectTrigger className="w-full max-w-xs">
                                                <SelectValue placeholder="Select a saved mapping..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {savedMappings.map((sm) => (
                                                    <SelectItem
                                                        key={sm.id}
                                                        value={String(sm.id)}
                                                    >
                                                        {sm.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {savedMappings.length > 0 && (
                                            <Select
                                                onValueChange={(val) =>
                                                    void handleDeleteMapping(
                                                        Number(val),
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-auto">
                                                    <SelectValue placeholder="Delete..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {savedMappings.map((sm) => (
                                                        <SelectItem
                                                            key={sm.id}
                                                            value={String(
                                                                sm.id,
                                                            )}
                                                        >
                                                            Delete &quot;
                                                            {sm.name}&quot;
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Account selector */}
                            <div className="flex flex-col gap-2">
                                <Label>
                                    Account{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={accountId}
                                    onValueChange={setAccountId}
                                >
                                    <SelectTrigger className="w-full max-w-xs">
                                        <SelectValue placeholder="Select account..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accounts.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={String(account.id)}
                                            >
                                                {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Field mappings */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {TARGET_FIELDS.map(
                                    ({ key, label, required }) => (
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
                                            <Select
                                                value={mapping[key]}
                                                onValueChange={(val) =>
                                                    handleMappingChange(
                                                        key,
                                                        val,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="— not mapped —" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {!required && (
                                                        <SelectItem
                                                            value={NOT_MAPPED}
                                                        >
                                                            — not mapped —
                                                        </SelectItem>
                                                    )}
                                                    {parseResult.headers.map(
                                                        (header) => (
                                                            <SelectItem
                                                                key={header}
                                                                value={header}
                                                            >
                                                                {header}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    ),
                                )}
                            </div>

                            {/* Skip duplicates */}
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="skip-duplicates"
                                    checked={skipDuplicates}
                                    onCheckedChange={(checked) =>
                                        setSkipDuplicates(checked === true)
                                    }
                                />
                                <Label htmlFor="skip-duplicates">
                                    Skip duplicate transactions (same date,
                                    amount, and description)
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
                                            setShowSaveMappingInput(true)
                                        }
                                        disabled={
                                            mapping.date === NOT_MAPPED ||
                                            mapping.amount === NOT_MAPPED
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
                                                setShowSaveMappingInput(false);
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
                                {parseResult.total_rows} rows. Review before
                                importing.
                            </p>

                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>Description</TableHead>
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
                                <Button onClick={handleConfirmImport}>
                                    Import {parseResult.total_rows} transactions
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Import History */}
                {importHistory.length > 0 && (
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
                                                    <TableHead>Date</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {importHistory.map((record) => (
                                                    <TableRow key={record.id}>
                                                        <TableCell className="font-medium">
                                                            {record.filename}
                                                        </TableCell>
                                                        <TableCell>
                                                            {record.row_count}
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
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>
                )}
            </div>
        </AppLayout>
    );
}
