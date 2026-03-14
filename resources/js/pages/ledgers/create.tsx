import { Form, Head } from '@inertiajs/react';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
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
        title: 'Ledgers',
        href: index(),
    },
    {
        title: 'Create ledger',
        href: createLedger(),
    },
];

export default function CreateLedger({
    defaults,
}: {
    defaults: { currency_code: string; uses_seeded_categories: boolean };
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create ledger" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create ledger"
                    description="Set up a new financial space with custom accounts and categories."
                />

                <Form
                    {...LedgerController.store.form()}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Ledger name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="Personal"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="currency_code">
                                    Currency code
                                </Label>
                                <Input
                                    id="currency_code"
                                    name="currency_code"
                                    defaultValue={defaults.currency_code}
                                    maxLength={3}
                                    required
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

                            <Button disabled={processing}>Create ledger</Button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
