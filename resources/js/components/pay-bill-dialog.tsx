import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { pay as payRoute } from '@/actions/App/Http/Controllers/Ledger/BillController';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Account, Bill } from '@/types';

type PayBillDialogProps = {
    bill: Bill | null;
    ledgerId: number;
    accounts: Account[];
    onClose: () => void;
    onSuccess?: () => void;
};

export function PayBillDialog({
    bill,
    ledgerId,
    accounts,
    onClose,
    onSuccess,
}: PayBillDialogProps) {
    const [amount, setAmount] = useState('');
    const [accountId, setAccountId] = useState('');
    const [toAccountId, setToAccountId] = useState('');
    const [paymentDate, setPaymentDate] = useState('');
    const [processing, setProcessing] = useState(false);
    const [prevBill, setPrevBill] = useState(bill);

    if (bill && bill !== prevBill) {
        setPrevBill(bill);
        setAmount(String(bill.amount));
        setAccountId(String(bill.account_id));
        setToAccountId(bill.to_account_id ? String(bill.to_account_id) : '');
        setPaymentDate(new Date().toISOString().slice(0, 10));
        setProcessing(false);
    }

    function handleOpenChange(open: boolean) {
        if (!open) {
            onClose();
        }
    }

    const isIncome = bill?.transaction_type === 'income';
    const isTransfer = bill?.transaction_type === 'transfer';
    const actionLabel = isIncome ? 'Record Income' : 'Record Payment';
    const actionLabelText = isTransfer ? 'Record Transfer' : actionLabel;
    const successLabel = isIncome ? 'recorded' : 'paid';
    const availableToAccounts = accounts.filter(
        (account) => String(account.id) !== accountId,
    );

    function handlePay() {
        if (!bill) {
            return;
        }

        setProcessing(true);

        const body: Record<string, string> = {};

        if (amount && amount !== String(bill.amount)) {
            body.amount = amount;
        }

        if (accountId && accountId !== String(bill.account_id)) {
            body.account_id = accountId;
        }

        if (
            isTransfer &&
            toAccountId &&
            toAccountId !== String(bill.to_account_id)
        ) {
            body.to_account_id = toAccountId;
        }

        if (
            paymentDate &&
            paymentDate !== new Date().toISOString().slice(0, 10)
        ) {
            body.date = paymentDate;
        }

        router.post(payRoute.url({ ledger: ledgerId, bill: bill.id }), body, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`${bill.name} ${successLabel}`);
                onClose();
                onSuccess?.();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(
                    typeof firstError === 'string'
                        ? firstError
                        : 'Failed to record payment',
                );
            },
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <Dialog open={bill !== null} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {actionLabel} — {bill?.name}
                    </DialogTitle>
                    <DialogDescription>
                        Confirm the details below.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="pay-amount">Amount</Label>
                        <Input
                            id="pay-amount"
                            type="number"
                            inputMode="decimal"
                            step="0.01"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pay-account">Account</Label>
                        <Select value={accountId} onValueChange={setAccountId}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select account" />
                            </SelectTrigger>
                            <SelectContent>
                                {accounts.map((account) => (
                                    <SelectItem
                                        key={account.id}
                                        value={String(account.id)}
                                    >
                                        {account.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isTransfer && (
                        <div className="grid gap-2">
                            <Label>Destination account</Label>
                            <SearchableSelect
                                options={availableToAccounts.map((account) => ({
                                    value: String(account.id),
                                    label: account.name,
                                    color: account.color,
                                }))}
                                value={toAccountId || null}
                                onValueChange={(value) =>
                                    setToAccountId(value ?? '')
                                }
                                placeholder="Select destination account"
                                searchPlaceholder="Search accounts..."
                            />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="pay-date">Payment date</Label>
                        <DatePicker
                            id="pay-date"
                            value={paymentDate}
                            onChange={(date) => setPaymentDate(date)}
                            placeholder="Pick a date"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={handlePay} disabled={processing}>
                        {processing ? 'Processing...' : actionLabelText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
