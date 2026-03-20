import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

type TwoFactorPageProps = {
    twoFactorQrCodeSvg?: string | null;
    twoFactorSecretKey?: string | null;
    twoFactorRecoveryCodes?: string[] | null;
};

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    recoveryCodesList: string[];
    hasSetupData: boolean;
    errors: string[];
    clearErrors: () => void;
    clearSetupData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

export const OTP_MAX_LENGTH = 6;

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
    const page = usePage<TwoFactorPageProps>();
    const qrCodeSvg = page.props.twoFactorQrCodeSvg ?? null;
    const manualSetupKey = page.props.twoFactorSecretKey ?? null;
    const recoveryCodesList = useMemo(
        () => page.props.twoFactorRecoveryCodes ?? [],
        [page.props.twoFactorRecoveryCodes],
    );
    const [errors, setErrors] = useState<string[]>([]);

    const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

    const clearErrors = (): void => {
        setErrors([]);
    };

    const reloadProp = useCallback(
        (prop: keyof TwoFactorPageProps, failureMessage: string): Promise<void> => {
            clearErrors();

            return new Promise((resolve) => {
                router.reload({
                    only: [prop],
                    onSuccess: () => resolve(),
                    onError: () => {
                        setErrors((prev) => [...prev, failureMessage]);
                        resolve();
                    },
                });
            });
        },
        [],
    );

    const fetchQrCode = useCallback(async (): Promise<void> => {
        await reloadProp('twoFactorQrCodeSvg', 'Failed to fetch QR code');
    }, [reloadProp]);

    const fetchSetupKey = useCallback(async (): Promise<void> => {
        await reloadProp('twoFactorSecretKey', 'Failed to fetch a setup key');
    }, [reloadProp]);

    const clearSetupData = (): void => {
        clearErrors();
    };

    const fetchRecoveryCodes = useCallback(async (): Promise<void> => {
        await reloadProp(
            'twoFactorRecoveryCodes',
            'Failed to fetch recovery codes',
        );
    }, [reloadProp]);

    const fetchSetupData = useCallback(async (): Promise<void> => {
        clearErrors();
        await Promise.all([fetchQrCode(), fetchSetupKey()]);
    }, [fetchQrCode, fetchSetupKey]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
