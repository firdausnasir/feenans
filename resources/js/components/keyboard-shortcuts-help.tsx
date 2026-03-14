import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const shortcuts = [
    { keys: ['N'], description: 'New transaction' },
    { keys: ['?'], description: 'Show keyboard shortcuts' },
    { keys: ['Ctrl', 'K'], description: 'Open command palette' },
    { keys: ['Esc'], description: 'Close modal / dialog' },
];

type KeyboardShortcutsHelpProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function KeyboardShortcutsHelp({
    open,
    onOpenChange,
}: KeyboardShortcutsHelpProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Keyboard Shortcuts</DialogTitle>
                    <DialogDescription>
                        Use these shortcuts to navigate quickly.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3 py-4">
                    {shortcuts.map((shortcut) => (
                        <div
                            key={shortcut.description}
                            className="flex items-center justify-between"
                        >
                            <span className="text-sm text-foreground">
                                {shortcut.description}
                            </span>
                            <div className="flex items-center gap-1">
                                {shortcut.keys.map((key, index) => (
                                    <span key={index}>
                                        {index > 0 && (
                                            <span className="mx-0.5 text-xs text-muted-foreground">
                                                +
                                            </span>
                                        )}
                                        <kbd className="inline-flex h-6 min-w-6 items-center justify-center rounded border border-border bg-muted px-1.5 text-xs font-medium text-muted-foreground">
                                            {key}
                                        </kbd>
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
