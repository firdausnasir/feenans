import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as reportsIndex,
    financialHealth as financialHealthRoute,
    budgetPerformance as budgetPerformanceRoute,
    cashFlow as cashFlowRoute,
} from '@/routes/ledgers/reports';

type ReportViewSelectProps = {
    ledgerId: number;
    currentView: string;
};

const VIEWS = [
    { key: 'income-expense', label: 'Income & Expense' },
    { key: 'financial-health', label: 'Financial Health' },
    { key: 'budget-performance', label: 'Budget Performance' },
    { key: 'cash-flow', label: 'Cash Flow' },
] as const;

function getViewUrl(ledgerId: number, viewKey: string): string {
    switch (viewKey) {
        case 'income-expense':
            return reportsIndex.url(ledgerId);
        case 'financial-health':
            return financialHealthRoute.url(ledgerId);
        case 'budget-performance':
            return budgetPerformanceRoute.url(ledgerId);
        case 'cash-flow':
            return cashFlowRoute.url(ledgerId);
        default:
            return reportsIndex.url(ledgerId);
    }
}

export function ReportViewSelect({ ledgerId, currentView }: ReportViewSelectProps) {
    return (
        <Select
            value={currentView}
            onValueChange={(val) => router.visit(getViewUrl(ledgerId, val))}
        >
            <SelectTrigger className="w-[200px] bg-primary text-primary-foreground">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {VIEWS.map((view) => (
                    <SelectItem key={view.key} value={view.key}>
                        {view.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
