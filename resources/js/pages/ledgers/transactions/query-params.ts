export type Filters = {
    search: string | null;
    date_from: string;
    date_to: string;
    account_ids: string[];
    category_ids: string[];
    transaction_types: string[];
    payee_ids: string[];
    tag_ids: string[];
    bill_id: string | null;
    uncategorized: string | null;
};

export const EMPTY_FILTERS: Filters = {
    search: null,
    date_from: '',
    date_to: '',
    account_ids: [],
    category_ids: [],
    transaction_types: [],
    payee_ids: [],
    tag_ids: [],
    bill_id: null,
    uncategorized: null,
};

export function buildQueryParams(
    filters: Filters,
): Record<string, string | string[]> {
    const params: Record<string, string | string[]> = {};

    if (filters.search) {
        params.search = filters.search;
    }

    if (filters.date_from) {
        params.date_from = filters.date_from;
    }

    if (filters.date_to) {
        params.date_to = filters.date_to;
    }

    if (filters.account_ids.length > 0) {
        params.account_ids = filters.account_ids;
    }

    if (filters.category_ids.length > 0) {
        params.category_ids = filters.category_ids;
    }

    if (filters.transaction_types.length > 0) {
        params.transaction_types = filters.transaction_types;
    }

    if (filters.payee_ids.length > 0) {
        params.payee_ids = filters.payee_ids;
    }

    if (filters.tag_ids.length > 0) {
        params.tag_ids = filters.tag_ids;
    }

    if (filters.bill_id) {
        params.bill_id = filters.bill_id;
    }

    if (filters.uncategorized) {
        params.uncategorized = filters.uncategorized;
    }

    return params;
}

export function deriveSelectionState({
    allVisibleIds,
    selectedIds,
    excludedIds,
    allAcrossPages,
}: {
    allVisibleIds: number[];
    selectedIds: number[];
    excludedIds: number[];
    allAcrossPages: boolean;
}): {
    allSelected: boolean;
    someSelected: boolean;
} {
    const isVisibleSelected = (id: number): boolean =>
        allAcrossPages ? !excludedIds.includes(id) : selectedIds.includes(id);

    const allSelected =
        allVisibleIds.length > 0 && allVisibleIds.every(isVisibleSelected);
    const someSelected =
        !allSelected && allVisibleIds.some(isVisibleSelected);

    return {
        allSelected,
        someSelected,
    };
}
