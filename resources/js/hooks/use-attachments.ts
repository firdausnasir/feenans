import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { Attachment, Transaction } from '@/types';
import attachmentRoutes from '@/routes/ledgers/transactions/attachments';

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

    const deleteAttachment = useCallback(
        (attachmentId: number) => {
            if (!transaction) {
                return;
            }

            router.delete(
                attachmentRoutes.destroy.url({
                    ledger: ledgerId,
                    transaction: transaction.id,
                    attachment: attachmentId,
                }),
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setAttachments((current) =>
                            current.filter((a) => a.id !== attachmentId),
                        );
                    },
                },
            );
        },
        [ledgerId, transaction],
    );

    return { attachments, loading, deleteAttachment };
}
