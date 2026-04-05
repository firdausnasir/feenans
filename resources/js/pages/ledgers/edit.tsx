import { Head, useForm, useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { show as showLedgerLoader } from '@/actions/App/Http/Controllers/Api/V1/LedgerController';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { edit as editLedger, index } from '@/routes/ledgers';
import type { BreadcrumbItem } from '@/types';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';

type Ledger = {
    id: number;
    name: string;
    currency_code: string;
    uses_seeded_categories: boolean;
};

type ApiEnvelope<T> = { data: T };

function EditLoadingSkeleton() {
    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <div className="space-y-6 rounded-xl border border-sidebar-border/70 p-6">
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-10 w-full" />
                </div>
                <div className="grid gap-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-10 w-full" />
                </div>
                <Skeleton className="h-10 w-28" />
            </div>
        </div>
    );
}

function EditErrorState({ onRetry }: { readonly onRetry: () => void }) {
    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <Card>
                <CardContent className="flex flex-col gap-3 py-4">
                    <p className="text-sm text-muted-foreground">
                        Failed to load workspace details.
                    </p>
                    <div>
                        <Button variant="outline" size="sm" onClick={onRetry}>
                            Retry
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function EditForm({ ledger }: { readonly ledger: Ledger }) {
    const { data, setData, put, processing, errors, clearErrors } = useForm({
        name: ledger.name,
        currency_code: ledger.currency_code,
        uses_seeded_categories: ledger.uses_seeded_categories ? '1' : '0',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        put(LedgerController.update.url(ledger.id), {
            onSuccess: () => toast.success('Workspace updated'),
        });
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
            <form
                onSubmit={submit}
                className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Workspace name</Label>
                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        onChange={(e) => {
                            setData('name', e.target.value);
                            clearErrors('name');
                        }}
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="currency_code">Currency code</Label>
                    <Input
                        id="currency_code"
                        name="currency_code"
                        value={data.currency_code}
                        onChange={(e) => {
                            setData('currency_code', e.target.value);
                            clearErrors('currency_code');
                        }}
                        maxLength={3}
                        required
                    />
                    <InputError message={errors.currency_code} />
                </div>

                <Button disabled={processing}>Save changes</Button>
            </form>
        </div>
    );
}

export default function EditLedger() {
    const { currentLedger } = usePage<{
        currentLedger: { id: number; name: string } | null;
    }>().props;

    const loaderState = useHttp<Record<string, never>, ApiEnvelope<Ledger>>(
        {},
    );
    const [hasLoaded, setHasLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const ledger = loaderState.response?.data ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Workspaces',
            href: index(),
        },
        {
            title: 'Edit workspace',
            href: currentLedger ? editLedger(currentLedger.id) : '#',
        },
    ];

    async function loadLedger(): Promise<boolean> {
        if (!currentLedger) {
            return false;
        }

        let cancelled = false;

        loaderState.cancel();
        setError(null);

        try {
            await loaderState.get(showLedgerLoader.url(currentLedger.id), {
                onCancel: () => {
                    cancelled = true;
                },
            });

            return true;
        } catch {
            if (!cancelled) {
                setError('Failed to load workspace details.');
            }

            return false;
        } finally {
            if (!cancelled) {
                setHasLoaded(true);
            }
        }
    }

    useEffect(() => {
        void loadLedger();

        return () => {
            loaderState.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentLedger?.id]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit workspace" />

            <BoneSkeleton
                name="edit-ledger"
                loading={!hasLoaded}
                fallback={<EditLoadingSkeleton />}
            >
                {error ? (
                    <EditErrorState onRetry={() => void loadLedger()} />
                ) : ledger ? (
                    <EditForm ledger={ledger} />
                ) : null}
            </BoneSkeleton>
        </AppLayout>
    );
}
