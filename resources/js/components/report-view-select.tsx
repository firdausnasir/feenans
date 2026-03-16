import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger
} from '@/components/ui/select';
import {
    budgetPerformance as budgetPerformanceRoute,
    cashFlow as cashFlowRoute,
    financialHealth as financialHealthRoute,
    index as reportsIndex,
} from '@/routes/ledgers/reports';
import { router } from '@inertiajs/react';

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

function getViewLabel(viewKey: string): string {
    return VIEWS.find((v) => v.key === viewKey)?.label ?? 'Select view';
}

export function ReportViewSelect({
    ledgerId,
    currentView,
}: ReportViewSelectProps) {
    return (
        <Select
            key={currentView}
            value={currentView}
            onValueChange={(val) => router.visit(getViewUrl(ledgerId, val))}
        >
            <SelectTrigger className="w-[200px] bg-primary text-white [&_svg]:text-white/70">
                <span className="truncate">{getViewLabel(currentView)}</span>
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
