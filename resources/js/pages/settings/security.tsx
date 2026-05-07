import { Transition } from '@headlessui/react';
import { Form, Head, useForm, useHttp } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';

import SecuritySettingsController from '@/actions/App/Http/Controllers/Api/V1/Settings/SecuritySettingsController';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { BoneSkeleton } from '@/components/ui/bone-skeleton';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

type SecurityConfig = {
    canManageTwoFactor: boolean;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
};

type ApiEnvelope<T> = {
    data: T;
};

type Props = {
    passwordReset?: {
        email: string;
        status: string | null;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: edit(),
    },
];

function SecuritySkeleton() {
    return (
        <div className="space-y-6">
            <div className="space-y-2">
                <Skeleton className="h-5 w-48" />
                <Skeleton className="h-4 w-72" />
            </div>
            <div className="space-y-4">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
            </div>
        </div>
    );
}

export default function Security({ passwordReset }: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const securityLoader = useHttp<
        Record<string, never>,
        ApiEnvelope<SecurityConfig>
    >({});
    const [hasResolved, setHasResolved] = useState(false);
    const [securityConfig, setSecurityConfig] = useState<SecurityConfig>({
        canManageTwoFactor: false,
        requiresConfirmation: false,
        twoFactorEnabled: false,
    });
    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors: twoFactorErrors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const resetLinkForm = useForm({});

    useEffect(() => {
        let cancelled = false;

        securityLoader.cancel();

        void securityLoader
            .get(SecuritySettingsController.url(), {
                onCancel: () => {
                    cancelled = true;
                },
            })
            .then((response) => {
                if (!cancelled && response?.data) {
                    setSecurityConfig(response.data);
                }
            })
            .catch(() => {})
            .finally(() => {
                if (!cancelled) {
                    setHasResolved(true);
                }
            });

        return () => {
            cancelled = true;
            securityLoader.cancel();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const submitPassword = (e: FormEvent) => {
        e.preventDefault();

        passwordForm.put(SecurityController.update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset(
                    'current_password',
                    'password',
                    'password_confirmation',
                );
            },
            onError: (errors) => {
                if (errors.password) {
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Update password"
                        description="Ensure your account is using a long, random password to stay secure"
                    />

                    <form onSubmit={submitPassword} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Current password
                            </Label>

                            <PasswordInput
                                id="current_password"
                                ref={currentPasswordInput}
                                className="mt-1 block w-full"
                                autoComplete="current-password"
                                placeholder="Current password"
                                value={passwordForm.data.current_password}
                                onChange={(e) => {
                                    passwordForm.clearErrors(
                                        'current_password',
                                    );
                                    passwordForm.setData(
                                        'current_password',
                                        e.target.value,
                                    );
                                }}
                            />

                            <InputError
                                message={passwordForm.errors.current_password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>

                            <PasswordInput
                                id="password"
                                ref={passwordInput}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="New password"
                                value={passwordForm.data.password}
                                onChange={(e) => {
                                    passwordForm.clearErrors('password');
                                    passwordForm.setData(
                                        'password',
                                        e.target.value,
                                    );
                                }}
                            />

                            <InputError
                                message={passwordForm.errors.password}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>

                            <PasswordInput
                                id="password_confirmation"
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                placeholder="Confirm password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => {
                                    passwordForm.clearErrors(
                                        'password_confirmation',
                                    );
                                    passwordForm.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    );
                                }}
                            />

                            <InputError
                                message={
                                    passwordForm.errors.password_confirmation
                                }
                            />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={passwordForm.processing}
                                data-test="update-password-button"
                            >
                                Save password
                            </Button>

                            <Transition
                                show={passwordForm.recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">
                                    Saved
                                </p>
                            </Transition>
                        </div>
                    </form>

                    <div className="mt-4 border-t pt-4">
                        <p className="text-sm text-muted-foreground">
                            Forgot your current password?{' '}
                            <button
                                type="button"
                                className="text-primary underline hover:text-primary/80"
                                onClick={() => {
                                    resetLinkForm.post(
                                        SecurityController.sendResetLink.url(),
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    );
                                }}
                                disabled={resetLinkForm.processing}
                            >
                                {resetLinkForm.processing
                                    ? 'Sending...'
                                    : 'Send a password reset link'}
                            </button>
                        </p>
                        {passwordReset?.status && (
                            <p className="mt-2 text-sm text-green-600 dark:text-green-400">
                                {passwordReset.status}
                            </p>
                        )}
                    </div>
                </div>

                <BoneSkeleton
                    name="settings-security"
                    loading={!hasResolved}
                    fallback={<SecuritySkeleton />}
                >
                    {securityConfig.canManageTwoFactor && (
                        <div className="space-y-6">
                            <Heading
                                variant="small"
                                title="Two-factor authentication"
                                description="Manage your two-factor authentication settings"
                            />
                            {securityConfig.twoFactorEnabled ? (
                                <div className="flex flex-col items-start justify-start space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        You will be prompted for a secure,
                                        random pin during login, which you can
                                        retrieve from the TOTP-supported
                                        application on your phone.
                                    </p>

                                    <div className="relative inline">
                                        <Form {...disable.form()}>
                                            {({ processing }) => (
                                                <Button
                                                    variant="destructive"
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    Disable 2FA
                                                </Button>
                                            )}
                                        </Form>
                                    </div>

                                    <TwoFactorRecoveryCodes
                                        recoveryCodesList={recoveryCodesList}
                                        fetchRecoveryCodes={fetchRecoveryCodes}
                                        errors={twoFactorErrors}
                                    />
                                </div>
                            ) : (
                                <div className="flex flex-col items-start justify-start space-y-4">
                                    <p className="text-sm text-muted-foreground">
                                        When you enable two-factor
                                        authentication, you will be prompted for
                                        a secure pin during login. This pin can
                                        be retrieved from a TOTP-supported
                                        application on your phone.
                                    </p>

                                    <div>
                                        {hasSetupData ? (
                                            <Button
                                                onClick={() =>
                                                    setShowSetupModal(true)
                                                }
                                            >
                                                <ShieldCheck />
                                                Continue setup
                                            </Button>
                                        ) : (
                                            <Form
                                                {...enable.form()}
                                                onSuccess={() =>
                                                    setShowSetupModal(true)
                                                }
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Enable 2FA
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </div>
                            )}

                            <TwoFactorSetupModal
                                isOpen={showSetupModal}
                                onClose={() => setShowSetupModal(false)}
                                requiresConfirmation={
                                    securityConfig.requiresConfirmation
                                }
                                twoFactorEnabled={
                                    securityConfig.twoFactorEnabled
                                }
                                qrCodeSvg={qrCodeSvg}
                                manualSetupKey={manualSetupKey}
                                clearSetupData={clearSetupData}
                                fetchSetupData={fetchSetupData}
                                errors={twoFactorErrors}
                            />
                        </div>
                    )}
                </BoneSkeleton>
            </SettingsLayout>
        </AppLayout>
    );
}
