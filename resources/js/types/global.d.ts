import type { User } from '@/types/auth';
import type { Account, Category, Ledger, Payee, Tag } from '@/types/ledger';

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
                attachment_uploads: Array<{
                    id: number;
                    transaction_id: number;
                    filename: string;
                    mime_type: string;
                    size: number;
                    url: string;
                }>;
                deleted_attachment_id: number | null;
            };
            currentLedger: Ledger | null;
            availableLedgers: Array<
                Pick<Ledger, 'id' | 'name' | 'currency_code'>
            >;
            unread_notifications_count: number;
            sidebarOpen: boolean;
            transactionModalData?: {
                accounts: Account[];
                categories: Category[];
                payees: Payee[];
                tags: Tag[];
            } | null;
            [key: string]: unknown;
        };
    }
}
