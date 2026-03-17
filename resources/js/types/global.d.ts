import type { User } from '@/types/auth';
import type { Ledger } from '@/types/ledger';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: {
                user: User | null;
            };
            flash: {
                success: string | null;
                error: string | null;
                first_transaction: boolean;
            };
            currentLedger: Ledger | null;
            availableLedgers: Array<
                Pick<Ledger, 'id' | 'name' | 'currency_code'>
            >;
            unread_notifications_count: number;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
