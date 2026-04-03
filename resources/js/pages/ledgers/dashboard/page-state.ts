type UpcomingBillsState = {
    due: unknown[];
    missed: unknown[];
    upcoming: unknown[];
};

export function shouldShowUpcomingRecurring({
    hasResolvedInitialLoad,
    processing,
    bills,
}: {
    hasResolvedInitialLoad: boolean;
    processing: boolean;
    bills: UpcomingBillsState | null;
}): boolean {
    if (!hasResolvedInitialLoad || processing || bills === null) {
        return false;
    }

    return (
        bills.due.length > 0 ||
        bills.missed.length > 0 ||
        bills.upcoming.length > 0
    );
}

export function buildDashboardUrl(baseUrl: string, offset: number): string {
    const url = new URL(baseUrl);

    url.searchParams.delete('offset');

    if (offset !== 0) {
        url.searchParams.set('offset', String(offset));
    }

    return url.toString();
}
