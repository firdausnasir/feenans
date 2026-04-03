const ALL_FILTER = '__all__';

export type ActivityFilters = {
    subject_type: string | null;
    action: string | null;
    page: number;
};

export function getActivityFilterSelectState(filters: ActivityFilters): {
    filterType: string;
    filterAction: string;
} {
    return {
        filterType: filters.subject_type ?? ALL_FILTER,
        filterAction: filters.action ?? ALL_FILTER,
    };
}

export function shouldResetActivityState(
    previousLedgerId: number,
    nextLedgerId: number,
    previousFilters: ActivityFilters,
    nextFilters: ActivityFilters,
): boolean {
    return (
        previousLedgerId !== nextLedgerId ||
        previousFilters.subject_type !== nextFilters.subject_type ||
        previousFilters.action !== nextFilters.action ||
        previousFilters.page !== nextFilters.page
    );
}

export { ALL_FILTER };
