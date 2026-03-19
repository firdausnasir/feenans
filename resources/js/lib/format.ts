/**
 * Shared formatting utilities for the financial tracker.
 *
 * Currency: MYR displayed as "RM 1,234.56"
 * Dates: Parsed with timezone-safe handling
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
 * plain date ("2026-03-13") formats by normalising to local time.
 */
export function parseDate(dateStr: string): Date {
    const dateOnly = dateStr.slice(0, 10);

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

/**
 * Return a Tailwind text-color class based on the sign of a numeric value.
 * Negative → red, non-negative → foreground.
 */
export function amountColor(value: number): string {
    return value < 0 ? 'text-red-500 dark:text-red-400' : 'text-foreground';
}

/**
 * Describe a recurrence schedule in human-readable form.
 * Accepts string inputs (from form fields) or numbers.
 */
export function describeRecurrence(
    type: string,
    interval: string | number,
    day?: string | number,
): string {
    const n =
        typeof interval === 'number' ? interval : parseInt(interval, 10) || 1;
    const dayNum =
        day !== undefined
            ? typeof day === 'number'
                ? day
                : parseInt(day as string, 10)
            : 0;
    const dayStr = dayNum ? ` on day ${dayNum}` : '';

    const labels: Record<string, [string, string]> = {
        daily: ['day', 'days'],
        weekly: ['week', 'weeks'],
        monthly: ['month', 'months'],
        yearly: ['year', 'years'],
        custom: ['period', 'periods'],
    };

    const [singular, plural] = labels[type] ?? ['period', 'periods'];

    if (n === 1) {
        return `Every ${singular}${dayStr}`;
    }

    return `Every ${n} ${plural}${dayStr}`;
}

/**
 * Flatten a parent-child category tree into SearchableSelect options.
 */
export function buildCategoryOptions(
    categories: Array<{
        id: number;
        name: string;
        color: string | null;
        children?: Array<{ id: number; name: string; color: string | null }>;
    }>,
): Array<{
    value: string;
    label: string;
    group?: string;
    color: string | null;
}> {
    return categories.flatMap((parent) => {
        const hasChildren = (parent.children?.length ?? 0) > 0;
        const items: Array<{
            value: string;
            label: string;
            group?: string;
            color: string | null;
        }> = [
            {
                value: String(parent.id),
                label: hasChildren ? `${parent.name} (general)` : parent.name,
                group: hasChildren ? parent.name : undefined,
                color: parent.color,
            },
        ];

        if (parent.children) {
            for (const child of parent.children) {
                items.push({
                    value: String(child.id),
                    label: child.name,
                    group: parent.name,
                    color: child.color,
                });
            }
        }

        return items;
    });
}
