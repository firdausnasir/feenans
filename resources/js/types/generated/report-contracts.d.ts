declare namespace App {
namespace Data {
namespace Reports {
namespace Output {
namespace Web {
export type BudgetPerformanceReportData = {
readonly budget_stats: {
            id: number,
            category_name: string,
            amount: number,
            spent: number,
            remaining: number,
            percentage: number,
            period: string,
            status: 'good' | 'warning' | 'danger' | 'over',
        }[],
readonly period_label: string,
};
export type CashFlowReportData = {
readonly daily_cash_flow: {
date: string,
income: number,
expense: number,
net: number,
cumulative: number,
}[],
readonly upcoming_bills: {
id: number,
name: string,
amount: number,
transaction_type: string,
next_due_date: string,
account_name: string | null,
}[],
readonly period_label: string,
};
export type FinancialHealthReportData = {
readonly net_worth_history: {
month: string,
assets: number,
liabilities: number,
net_worth: number,
}[],
readonly savings_rate_history: {
month: string,
income: number,
expense: number,
savings: number,
rate: number,
}[],
readonly current_snapshot: {
assets: number,
liabilities: number,
net_worth: number,
debt_to_asset_ratio: number,
},
};
export type ReportDateRangeData = {
readonly date_from: string,
readonly date_to: string,
readonly preset: string,
readonly account_id: string | null,
};
export type SpendingReportData = {
readonly monthly_trends: Record<string, any>[],
readonly category_breakdown: {
items: Record<string, any>[],
parents: Record<string, any>[],
},
readonly payee_breakdown: Record<string, any>[],
readonly income_category_breakdown: {
items: Record<string, any>[],
parents: Record<string, any>[],
},
readonly income_payee_breakdown: Record<string, any>[],
readonly spending_heatmap: Record<string, any>[],
readonly summary: Record<string, any>,
readonly date_range: App.Data.Reports.Output.Web.ReportDateRangeData,
readonly comparison: Record<string, any> | null,
};
}
}
}
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
