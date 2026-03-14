import { useEffect } from 'react';

type KeyboardShortcutCallbacks = {
    onNewTransaction?: () => void;
    onEscape?: () => void;
    onShowHelp?: () => void;
    onCommandPalette?: () => void;
};

export function useKeyboardShortcuts(callbacks: KeyboardShortcutCallbacks) {
    useEffect(() => {
        function handleKeyDown(e: KeyboardEvent) {
            const target = e.target as HTMLElement;
            const isInput =
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.isContentEditable;

            // Ctrl/Cmd+K always works
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                callbacks.onCommandPalette?.();
                return;
            }

            // Escape always works
            if (e.key === 'Escape') {
                callbacks.onEscape?.();
                return;
            }

            // Other shortcuts only when not in input
            if (isInput) return;

            if (e.key === 'n') callbacks.onNewTransaction?.();
            if (e.key === '?') callbacks.onShowHelp?.();
        }

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [callbacks]);
}
