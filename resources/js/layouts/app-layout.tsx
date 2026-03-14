import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { CommandPalette } from '@/components/command-palette';
import { KeyboardShortcutsHelp } from '@/components/keyboard-shortcuts-help';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { index as transactionsIndex } from '@/routes/ledgers/transactions';
import type { AppLayoutProps } from '@/types';

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => {
    const { currentLedger } = usePage().props as {
        currentLedger: {
            id: number;
            name: string;
            currency_code: string;
        } | null;
    };

    const [helpOpen, setHelpOpen] = useState(false);
    const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);

    const ledgerId = currentLedger?.id ?? null;

    const callbacks = useMemo(
        () => ({
            onNewTransaction: () => {
                if (ledgerId) {
                    router.visit(
                        transactionsIndex.url(ledgerId, {
                            query: { create: '1' },
                        }),
                    );
                }
            },
            onEscape: () => {
                if (commandPaletteOpen) {
                    setCommandPaletteOpen(false);
                } else if (helpOpen) {
                    setHelpOpen(false);
                }
            },
            onShowHelp: () => setHelpOpen(true),
            onCommandPalette: () => setCommandPaletteOpen((prev) => !prev),
        }),
        [ledgerId, commandPaletteOpen, helpOpen],
    );

    useKeyboardShortcuts(callbacks);

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
            {children}
            <KeyboardShortcutsHelp open={helpOpen} onOpenChange={setHelpOpen} />
            <CommandPalette
                open={commandPaletteOpen}
                onOpenChange={setCommandPaletteOpen}
                ledgerId={ledgerId}
            />
        </AppLayoutTemplate>
    );
};
