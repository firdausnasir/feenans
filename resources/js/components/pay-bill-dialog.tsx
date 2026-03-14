import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
import { pay } from '@/routes/ledgers/bills';
import type { Account, Bill } from '@/types';

type PayBillDialogProps = {
    bill: Bill | null;
    ledgerId: number;
    accounts: Account[];
    onClose: () => void;
};

export function PayBillDialog({ bill, ledgerId, accounts, onClose }: PayBillDialogProps) {
    const [amount, setAmount] = useState('');
    const [accountId, setAccountId] = useState('');
    const [paymentDate, setPaymentDate] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (bill) {
            setAmount(String(bill.amount));
            setAccountId(String(bill.account_id));
            setPaymentDate(new Date().toISOString().slice(0, 10));
            setProcessing(false);
        }
    }, [bill]);

    function handleOpenChange(open: boolean) {
        if (!open) {
            onClose();
        }
    }

    function handlePay() {
        if (!bill) {
            return;
        }

        setProcessing(true);

        const payload: Record<string, string> = {};

        if (amount && amount !== String(bill.amount)) {
            payload.amount = amount;
        }

        if (accountId && accountId !== String(bill.account_id)) {
            payload.account_id = accountId;
        }

        if (paymentDate && paymentDate !== new Date().toISOString().slice(0, 10)) {
            payload.date = paymentDate;
        }

        router.post(
            pay.url({ ledger: ledgerId, bill: bill.id }),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    toast.success(`${bill.name} paid`);
                    onClose();
                },
                onError: () => {
                    setProcessing(false);
                },
            },
        );
    }

    const selectedAccount = accounts.find(
        (account) => String(account.id) === accountId,
    );

    return (
        <Dialog open={bill !== null} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Pay {bill?.name}?</DialogTitle>
                    <DialogDescription>
                        Confirm the payment details below.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="pay-amount">Amount</Label>
                        <Input
                            id="pay-amount"
                            type="number"
                            step="0.01"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="pay-account">Account</Label>
                        <Select
                            value={accountId}
                            onValueChange={setAccountId}
                        >
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

                    <div className="grid gap-2">
                        <Label htmlFor="pay-date">Payment date</Label>
                        <Input
                            id="pay-date"
                            type="date"
                            value={paymentDate}
                            onChange={(e) => setPaymentDate(e.target.value)}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button onClick={handlePay} disabled={processing}>
                        {processing ? 'Paying...' : 'Pay Now'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
