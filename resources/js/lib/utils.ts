import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Normalize Inertia error responses into a consistent shape.
 * Inertia returns `Record<string, string>` — this converts each value to a string.
 */
export function mapInertiaErrors<
    T extends Record<string, unknown> = Record<string, string>,
>(errs: Record<string, unknown>): T {
    return Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [
            k,
            typeof v === 'string' ? v : String(v),
        ]),
    ) as T;
}

/**
 * Like mapInertiaErrors but wraps each value in an array.
 * Useful for components that expect `Record<string, string[]>`.
 */
export function mapInertiaErrorsArray(
    errs: Record<string, unknown>,
): Record<string, string[]> {
    return Object.fromEntries(
        Object.entries(errs).map(([k, v]) => [
            k,
            [typeof v === 'string' ? v : String(v)],
        ]),
    );
}
