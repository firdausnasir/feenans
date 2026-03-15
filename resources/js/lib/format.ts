/**
 * Shared formatting utilities for the financial tracker.
 *
 * Currency: MYR displayed as "RM 1,234.56"
 * Dates: Parsed with timezone-safe ISO handling
 */

const currencyFormatter = new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Format a monetary amount as "RM 1,234.56".
 * Accepts number or string (will be parsed to float).
 * Returns the formatted string with sign preserved.
 */
export function formatAmount(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    if (isNaN(num)) {
        return 'RM 0.00';
    }

    return currencyFormatter.format(num).replace('MYR', 'RM');
}

/**
 * Format a monetary amount as "RM 1,234.56" using absolute value.
 * Useful for displaying expense/income amounts where sign is indicated by color/context.
 */
export function formatAbsAmount(amount: number | string): string {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;

    if (isNaN(num)) {
        return 'RM 0.00';
    }

    return currencyFormatter.format(Math.abs(num)).replace('MYR', 'RM');
}

/**
 * Parse a date string safely for display.
 * Handles both ISO 8601 ("2026-03-13T00:00:00.000000Z") and
 * plain date ("2026-03-13") formats by normalising to midnight local time.
 */
function parseDate(dateStr: string): Date {
    // Strip any time/timezone component and treat as local midnight
    const dateOnly = dateStr.slice(0, 10); // "YYYY-MM-DD"

    return new Date(dateOnly + 'T00:00:00');
}

/**
 * Format a date string as "13 Mar 2026".
 */
export function formatDate(dateStr: string): string {
    return parseDate(dateStr).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Format a date string as "Mar 2026" (month and year only).
 */
export function formatMonthYear(dateStr: string): string {
    return parseDate(dateStr).toLocaleDateString('en-MY', {
        year: 'numeric',
        month: 'short',
    });
}
