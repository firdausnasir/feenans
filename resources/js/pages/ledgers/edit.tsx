import { Form, Head } from '@inertiajs/react';
import { toast } from 'sonner';
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
            title: 'Workspaces',
            href: index(),
        },
        {
            title: 'Edit workspace',
            href: editLedger(ledger.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit workspace" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit workspace"
                    description="Update your workspace basics."
                />

                <Form
                    {...LedgerController.update.form(ledger.id)}
                    className="space-y-6 rounded-xl border border-sidebar-border/70 p-6"
                    onSuccess={() => toast.success('Workspace updated')}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Workspace name</Label>
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
