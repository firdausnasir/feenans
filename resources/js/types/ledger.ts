export type Ledger = {
    id: number;
    name: string;
    currency_code: string;
    uses_seeded_categories: boolean;
    cycle_start_day: number; // 1-31
};

export type AccountType = {
    id: number;
    ledger_id: number;
    name: string;
    color: string | null;
    position: number;
    is_credit: boolean;
};

export type Account = {
    id: number;
    ledger_id: number;
    account_type_id: number;
    name: string;
    initial_balance: string;
    statement_day: number | null;
    include_in_totals: boolean;
    current_balance: string;
    accountType?: AccountType;
};

export type Category = {
    id: number;
    ledger_id: number;
    name: string;
    transaction_type: 'expense' | 'income';
    color: string | null;
    icon: string | null;
    position: number;
    parent_id: number | null;
    children?: Category[]; // optional, may not always be loaded
};

export type Payee = {
    id: number;
    ledger_id: number;
    name: string;
};

export type Tag = {
    id: number;
    ledger_id: number;
    name: string;
    color: string | null;
};

export type Attachment = {
    id: number;
    transaction_id: number;
    filename: string;
    path: string;
    mime_type: string;
    size: number;
    url: string;
};

export type TransactionSplit = {
    id: number;
    transaction_id: number;
    category_id: number | null;
    amount: string;
    description: string | null;
    category?: Category | null;
};

export type Transaction = {
    id: number;
    ledger_id: number;
    account_id: number;
    category_id: number | null;
    payee_id: number | null;
    transaction_type: 'expense' | 'income' | 'transfer';
    amount: string;
    description: string | null;
    notes: string | null;
    transaction_date: string;
    transfer_pair_id: string | null;
    account?: Account;
    category?: Category | null;
    payee?: Payee | null;
    transfer_pair?: Transaction | null;
    tags?: Tag[];
    attachments?: Attachment[];
    splits?: TransactionSplit[];
    is_split?: boolean;
};

export type DashboardSummary = {
    income: number;
    expense: number;
    net: number;
};

export type Bill = {
    id: number;
    ledger_id: number;
    account_id: number;
    category_id: number | null;
    payee_id: number | null;
    name: string;
    transaction_type: 'expense' | 'income';
    amount: number;
    recurrence_type: 'daily' | 'weekly' | 'monthly' | 'yearly' | 'custom';
    recurrence_interval: number;
    recurrence_day: number | null;
    next_due_date: string; // ISO date string
    auto_create: boolean;
    end_type: 'never' | 'on_date' | 'after_occurrences' | null;
    end_date: string | null;
    end_after_occurrences: number | null;
    occurrences_count: number;
    is_active: boolean;
    // relationships (optional, may be loaded)
    account?: Account;
    category?: Category | null;
    payee?: Payee | null;
    created_at: string;
    updated_at: string;
};

export type BudgetStat = {
    id: number;
    category_id: number | null;
    category_name: string;
    amount: number;
    period: 'monthly' | 'weekly' | 'yearly';
    spent: number;
    remaining: number;
    percentage: number;
    status: 'good' | 'warning' | 'danger' | 'over';
    rollover: boolean;
};

export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
};
