import { Form, Head } from '@inertiajs/react';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { edit as editLedger, index } from '@/routes/ledgers';
import type { BreadcrumbItem } from '@/types';

type Ledger = {
    id: number;
    name: string;
    currency_code: string;
    uses_seeded_categories: boolean;
};

export default function EditLedger({ ledger }: { ledger: Ledger }) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Ledgers',
            href: index(),
        },
        {
            title: 'Edit ledger',
            href: editLedger(ledger.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit ledger" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit ledger"
                    description="Update your ledger basics."
                />

                <Form
                    {...LedgerController.update.form(ledger.id)}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Ledger name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={ledger.name}
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
                                    defaultValue={ledger.currency_code}
                                    maxLength={3}
                                    required
                                />
                                <InputError message={errors.currency_code} />
                            </div>

                            <input
                                type="hidden"
                                name="uses_seeded_categories"
                                value={
                                    ledger.uses_seeded_categories ? '1' : '0'
                                }
                            />

                            <Button disabled={processing}>Save changes</Button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
