import type { Ledger } from '@/types/ledger';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: {
                user: {
                    id: number;
                    name: string;
                    email: string;
                    onboarding_step: number | null;
                } | null;
            };
            flash: {
                success: string | null;
                error: string | null;
            };
            currentLedger: {
                id: number;
                name: string;
                currency_code: string;
                cycle_start_day: number;
            } | null;
            availableLedgers: Array<
                Pick<Ledger, 'id' | 'name' | 'currency_code'>
            >;
            unread_notifications_count: number;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
