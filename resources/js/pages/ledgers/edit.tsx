import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import { edit as editLedger, index } from '@/routes/ledgers';

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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit workspace" />

            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit workspace"
                    description="Update your workspace basics."
                />

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
                        <Label htmlFor="currency_code">
                            Currency code
                        </Label>
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
        </AppLayout>
    );
}
