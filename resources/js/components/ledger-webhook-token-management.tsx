import { useHttp } from '@inertiajs/react';
import { AlertTriangle, Check, Copy, KeyRound, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import ApiTokenController from '@/actions/App/Http/Controllers/Api/V1/Auth/ApiTokenController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';

type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    plain_text_token: string | null;
    created_at: string;
    last_used_at: string | null;
};

type ApiTokenForm = {
    device_name: string;
    ledger_id: number;
};

type ApiEnvelope<T> = {
    data: T;
};

type Props = {
    ledgerId: number;
};

const TRANSACTION_WEBHOOK_LEDGER_ABILITY_PREFIX =
    'transactions:webhook:ledger:';

function formatTokenDate(value: string | null) {
    if (!value) {
        return 'Never';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

export function LedgerWebhookTokenManagement({ ledgerId }: Props) {
    const webhookAbility = `${TRANSACTION_WEBHOOK_LEDGER_ABILITY_PREFIX}${ledgerId}`;
    const tokenLoader = useHttp<Record<string, never>, ApiEnvelope<ApiToken[]>>(
        {},
    );
    const createTokenForm = useHttp<ApiTokenForm, ApiEnvelope<ApiToken>>({
        device_name: '',
        ledger_id: ledgerId,
    });
    const revokeTokenForm = useHttp<Record<string, never>, unknown>({});
    const [hasResolvedTokens, setHasResolvedTokens] = useState(false);
    const [apiTokens, setApiTokens] = useState<ApiToken[]>([]);
    const [plainTextToken, setPlainTextToken] = useState<string | null>(null);
    const [copiedToken, setCopiedToken] = useState(false);
    const [revokingTokenId, setRevokingTokenId] = useState<number | null>(null);

    useEffect(() => {
        let cancelled = false;

        tokenLoader.cancel();
        setHasResolvedTokens(false);
        setPlainTextToken(null);
        setCopiedToken(false);

        void tokenLoader
            .get(
                ApiTokenController.index.url({
                    query: { ledger_id: ledgerId },
                }),
                {
                    onCancel: () => {
                        cancelled = true;
                    },
                },
            )
            .then((response) => {
                if (!cancelled && response?.data) {
                    setApiTokens(response.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    toast.error('Failed to load webhook keys.');
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setHasResolvedTokens(true);
                }
            });

        return () => {
            cancelled = true;
            tokenLoader.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ledgerId]);

    const submitApiToken = (e: FormEvent) => {
        e.preventDefault();
        setCopiedToken(false);

        void createTokenForm
            .post(ApiTokenController.store.url())
            .then((response) => {
                if (!response?.data) {
                    return;
                }

                setPlainTextToken(response.data.plain_text_token);
                setApiTokens((tokens) => [
                    { ...response.data, plain_text_token: null },
                    ...tokens,
                ]);
                createTokenForm.setData('device_name', '');
                toast.success(
                    'Webhook key created. Copy it now; it will not be shown again.',
                );
            })
            .catch(() => {});
    };

    const revokeApiToken = (token: ApiToken) => {
        setRevokingTokenId(token.id);

        void revokeTokenForm
            .delete(ApiTokenController.destroy.url(token.id))
            .then(() => {
                setApiTokens((tokens) =>
                    tokens.filter((item) => item.id !== token.id),
                );
            })
            .catch(() => {
                toast.error('Failed to revoke webhook key.');
            })
            .finally(() => {
                setRevokingTokenId(null);
            });
    };

    const copyPlainTextToken = () => {
        if (
            !plainTextToken ||
            typeof navigator === 'undefined' ||
            !navigator.clipboard
        ) {
            return;
        }

        void navigator.clipboard.writeText(plainTextToken).then(() => {
            setCopiedToken(true);
        });
    };

    const canCopyToken =
        typeof navigator !== 'undefined' && Boolean(navigator.clipboard);

    return (
        <div className="grid max-w-2xl gap-4">
            <form onSubmit={submitApiToken} className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor={`webhook-token-name-${ledgerId}`}>
                        Key name
                    </Label>
                    <Input
                        id={`webhook-token-name-${ledgerId}`}
                        autoComplete="off"
                        placeholder="Transaction webhook"
                        value={createTokenForm.data.device_name}
                        onChange={(e) => {
                            createTokenForm.clearErrors('device_name');
                            createTokenForm.setData(
                                'device_name',
                                e.target.value,
                            );
                        }}
                    />
                    <InputError
                        message={
                            typeof createTokenForm.errors.device_name ===
                            'string'
                                ? createTokenForm.errors.device_name
                                : undefined
                        }
                    />
                    <InputError
                        message={
                            typeof createTokenForm.errors.ledger_id === 'string'
                                ? createTokenForm.errors.ledger_id
                                : undefined
                        }
                    />
                </div>

                <Alert className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Copy the key when it appears</AlertTitle>
                    <AlertDescription className="text-amber-800 dark:text-amber-200">
                        Webhook keys are shown only once when created and cannot
                        be shown again afterwards.
                    </AlertDescription>
                </Alert>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Button type="submit" disabled={createTokenForm.processing}>
                        <KeyRound />
                        Create webhook key
                    </Button>
                    <Badge
                        variant="outline"
                        className="max-w-full text-left break-all"
                    >
                        {webhookAbility}
                    </Badge>
                </div>
            </form>

            {plainTextToken && (
                <div className="rounded-md border bg-muted/30 p-3">
                    <Alert className="mb-3 border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>This key is shown only once</AlertTitle>
                        <AlertDescription className="text-amber-800 dark:text-amber-200">
                            Copy it now. Once you leave or refresh this page, it
                            cannot be shown again.
                        </AlertDescription>
                    </Alert>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <code className="min-w-0 flex-1 rounded border bg-background px-2 py-2 text-xs break-all">
                            {plainTextToken}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={copyPlainTextToken}
                            disabled={!canCopyToken}
                        >
                            {copiedToken ? <Check /> : <Copy />}
                            {copiedToken ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                </div>
            )}

            <BoneSkeleton
                name="ledger-webhook-tokens"
                loading={!hasResolvedTokens}
                fallback={
                    <div className="space-y-3">
                        <Skeleton className="h-14 w-full" />
                        <Skeleton className="h-14 w-full" />
                    </div>
                }
            >
                <div className="divide-y rounded-md border">
                    {apiTokens.length === 0 ? (
                        <p className="px-3 py-4 text-sm text-muted-foreground">
                            No webhook keys yet.
                        </p>
                    ) : (
                        apiTokens.map((token) => (
                            <div
                                key={token.id}
                                className="flex flex-col gap-3 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="truncate text-sm font-medium">
                                            {token.name}
                                        </p>
                                        {token.abilities.map((ability) => (
                                            <Badge
                                                key={ability}
                                                variant="outline"
                                                className="max-w-full text-left break-all"
                                            >
                                                {ability}
                                            </Badge>
                                        ))}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Created{' '}
                                        {formatTokenDate(token.created_at)} -
                                        Last used{' '}
                                        {formatTokenDate(token.last_used_at)}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    title="Revoke key"
                                    aria-label={`Revoke ${token.name}`}
                                    disabled={
                                        revokeTokenForm.processing &&
                                        revokingTokenId === token.id
                                    }
                                    onClick={() => revokeApiToken(token)}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))
                    )}
                </div>
            </BoneSkeleton>
        </div>
    );
}
