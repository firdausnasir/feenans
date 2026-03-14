import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import { CurrencySelect } from '@/components/currency-select';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { create as createLedger, index } from '@/routes/ledgers';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspaces',
        href: index(),
    },
    {
        title: 'Create workspace',
        href: createLedger(),
    },
];

export default function CreateLedger({
    defaults,
}: {
    defaults: { currency_code: string; uses_seeded_categories: boolean };
}) {
    const [currencyCode, setCurrencyCode] = useState(defaults.currency_code);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create workspace" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create your workspace"
                    description="Set up a new financial space with custom accounts and categories."
                />

                <Form
                    {...LedgerController.store.form()}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    onSuccess={() => toast.success('Ledger created')}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue="My Finances"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Currency code</Label>
                                <CurrencySelect
                                    value={currencyCode}
                                    onValueChange={setCurrencyCode}
                                />
                                <input
                                    type="hidden"
                                    name="currency_code"
                                    value={currencyCode}
                                />
                                <InputError message={errors.currency_code} />
                            </div>

                            <input
                                type="hidden"
                                name="uses_seeded_categories"
                                value={
                                    defaults.uses_seeded_categories ? '1' : '0'
                                }
                            />

                            <Button disabled={processing}>
                                Create workspace
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
