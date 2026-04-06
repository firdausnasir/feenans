type ReportsFilters = {
    date_from: string;
    date_to: string;
    preset: string;
    account_id: string | null;
    compare_start: string | null;
    compare_end: string | null;
};

export function getNextReportsFilters(
    current: ReportsFilters,
    updates: Partial<ReportsFilters>,
): ReportsFilters {
    return {
        ...current,
        ...updates,
    };
}

export function buildReportsUrl(
    baseUrl: string,
    filters: ReportsFilters,
): string {
    const url = new URL(baseUrl);

    for (const key of [
        'date_from',
        'date_to',
        'account_id',
        'compare_start',
        'compare_end',
    ]) {
        url.searchParams.delete(key);
    }

    for (const [key, value] of Object.entries({
        date_from: filters.date_from,
        date_to: filters.date_to,
        account_id: filters.account_id,
        compare_start: filters.compare_start,
        compare_end: filters.compare_end,
    })) {
        if (value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    }

    return url.toString();
}

export type { ReportsFilters };
