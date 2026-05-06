import type { Filters } from './query-params';

const ARRAY_FILTER_KEYS = [
    'account_ids',
    'category_ids',
    'transaction_types',
    'payee_ids',
    'tag_ids',
] as const;

const SCALAR_FILTER_KEYS = [
    'search',
    'date_from',
    'date_to',
    'bill_id',
    'uncategorized',
] as const;

function areStringArraysEqual(left: string[], right: string[]): boolean {
    return (
        left.length === right.length &&
        left.every((value, index) => value === right[index])
    );
}

function buildTransactionsQueryParams(
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

export function buildTransactionsUrl(
    baseUrl: string,
    filters: Filters,
    page = 1,
): string {
    const url = new URL(baseUrl);

    for (const key of ARRAY_FILTER_KEYS) {
        url.searchParams.delete(key);
        url.searchParams.delete(`${key}[]`);
    }

    for (const key of SCALAR_FILTER_KEYS) {
        url.searchParams.delete(key);
    }

    url.searchParams.delete('page');

    for (const [key, value] of Object.entries(
        buildTransactionsQueryParams(filters),
    )) {
        if (Array.isArray(value)) {
            value.forEach((item) => {
                url.searchParams.append(`${key}[]`, item);
            });
            continue;
        }

        url.searchParams.set(key, value);
    }

    if (page > 1) {
        url.searchParams.set('page', String(page));
    }

    return url.toString();
}

export function shouldResetTransactionsState(
    previousLedgerId: number,
    nextLedgerId: number,
    previousFilters: Filters,
    nextFilters: Filters,
): boolean {
    return (
        previousLedgerId !== nextLedgerId ||
        previousFilters.search !== nextFilters.search ||
        previousFilters.date_from !== nextFilters.date_from ||
        previousFilters.date_to !== nextFilters.date_to ||
        previousFilters.bill_id !== nextFilters.bill_id ||
        previousFilters.uncategorized !== nextFilters.uncategorized ||
        !areStringArraysEqual(previousFilters.account_ids, nextFilters.account_ids) ||
        !areStringArraysEqual(
            previousFilters.category_ids,
            nextFilters.category_ids,
        ) ||
        !areStringArraysEqual(
            previousFilters.transaction_types,
            nextFilters.transaction_types,
        ) ||
        !areStringArraysEqual(previousFilters.payee_ids, nextFilters.payee_ids) ||
        !areStringArraysEqual(previousFilters.tag_ids, nextFilters.tag_ids)
    );
}

export function shouldApplyTransactionsResponse({
    cancelled,
    latestRequestId,
    requestId,
}: {
    cancelled: boolean;
    latestRequestId: number;
    requestId: number;
}): boolean {
    return !cancelled && latestRequestId === requestId;
}

export function resolveTransactionsResponse<T>(
    requestResponse: T | null | undefined,
    currentResponse: T | null,
): T | null {
    return requestResponse ?? currentResponse;
}

export function isLastTransactionsPage<
    T extends { meta: { next_page_url: string | null } },
>(response: T | null): boolean {
    return response?.meta.next_page_url === null;
}

export function canAppendTransactionsPage({
    nextPageUrl,
    processing,
    isAppending,
}: {
    nextPageUrl: string | null;
    processing: boolean;
    isAppending: boolean;
}): boolean {
    return nextPageUrl !== null && !processing && !isAppending;
}

export function shouldFetchNextTransactionsPage({
    hasMore,
    loading,
    alreadyTriggered,
    isVisible,
}: {
    hasMore: boolean;
    loading: boolean;
    alreadyTriggered: boolean;
    isVisible: boolean;
}): boolean {
    return hasMore && !loading && !alreadyTriggered && isVisible;
}

export function shouldContinueTransactionsReload({
    operationId,
    latestOperationId,
    wasSuccessful,
}: {
    operationId: number;
    latestOperationId: number;
    wasSuccessful: boolean;
}): boolean {
    return wasSuccessful && operationId === latestOperationId;
}

export function mergeTransactionPageData<T extends { id: number }>(
    current: T[],
    incoming: T[],
): T[] {
    const merged = [...current];
    const seenIds = new Set(current.map((item) => item.id));

    for (const item of incoming) {
        if (seenIds.has(item.id)) {
            continue;
        }

        seenIds.add(item.id);
        merged.push(item);
    }

    return merged;
}
