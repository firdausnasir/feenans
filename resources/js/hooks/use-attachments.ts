import { useEffect, useState } from 'react';
import attachmentRoutes from '@/routes/ledgers/transactions/attachments';
import type { Attachment, Transaction } from '@/types';

export function useAttachments(
    ledgerId: number,
    transaction: Transaction | null,
) {
    const [attachments, setAttachments] = useState<Attachment[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!transaction) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        fetch(
            attachmentRoutes.index.url({
                ledger: ledgerId,
                transaction: transaction.id,
            }),
        )
            .then((res) => res.json())
            .then((data) => {
                if (!cancelled) {
                    setAttachments(data.attachments ?? []);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setAttachments([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [ledgerId, transaction]);

    return { attachments, loading };
}
