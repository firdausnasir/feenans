import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import LedgerController from '@/actions/App/Http/Controllers/LedgerController';
import { CurrencySelect } from '@/components/currency-select';
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
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        name: 'My Finances',
        currency_code: defaults.currency_code,
        uses_seeded_categories: defaults.uses_seeded_categories ? '1' : '0',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(LedgerController.store.url(), {
            onSuccess: () => toast.success('Workspace created'),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create workspace" />

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
                        <Label>Currency code</Label>
                        <CurrencySelect
                            value={data.currency_code}
                            onValueChange={(value) => {
                                setData('currency_code', value);
                                clearErrors('currency_code');
                            }}
                        />
                        <InputError message={errors.currency_code} />
                    </div>

                    <Button disabled={processing}>Create workspace</Button>
                </form>
            </div>
        </AppLayout>
    );
}
