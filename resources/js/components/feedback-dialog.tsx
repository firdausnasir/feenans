import { router } from '@inertiajs/react';
import { Bug, Lightbulb, MessageSquare, Send } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import { store } from '@/actions/App/Http/Controllers/FeedbackController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Textarea } from '@/components/ui/textarea';

const feedbackTypes = [
    {
        value: 'general',
        label: 'General',
        icon: MessageSquare,
        description: 'General feedback or comment',
    },
    {
        value: 'bug',
        label: 'Bug Report',
        icon: Bug,
        description: "Something isn't working",
    },
    {
        value: 'feature',
        label: 'Feature Request',
        icon: Lightbulb,
        description: 'Suggest an improvement',
    },
] as const;

type FeedbackType = (typeof feedbackTypes)[number]['value'];

export function FeedbackDialog() {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<FeedbackType>('general');
    const [message, setMessage] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function resetForm() {
        setType('general');
        setMessage('');
        setErrors({});
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post(store.url(), { type, message }, {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                resetForm();
                setOpen(false);
                toast.success('Thank you for your feedback!');
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <Dialog
                    open={open}
                    onOpenChange={(value) => {
                        setOpen(value);

                        if (!value) {
resetForm();
}
                    }}
                >
                    <DialogTrigger asChild>
                        <SidebarMenuButton
                            tooltip={{ children: 'Feedback' }}
                        >
                            <MessageSquare />
                            <span>Feedback</span>
                        </SidebarMenuButton>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-md">
                        <form onSubmit={handleSubmit}>
                            <DialogHeader>
                                <DialogTitle>Send Feedback</DialogTitle>
                                <DialogDescription>
                                    Help us improve by sharing your thoughts.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="mt-4 space-y-4">
                                <div className="space-y-2">
                                    <Label>Type</Label>
                                    <div className="grid grid-cols-3 gap-2">
                                        {feedbackTypes.map((ft) => (
                                            <button
                                                key={ft.value}
                                                type="button"
                                                onClick={() =>
                                                    setType(ft.value)
                                                }
                                                className={`flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition-colors ${
                                                    type === ft.value
                                                        ? 'border-primary bg-primary/5 text-primary'
                                                        : 'border-border hover:border-primary/50'
                                                }`}
                                            >
                                                <ft.icon className="size-4" />
                                                <span className="text-xs font-medium">
                                                    {ft.label}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                    {errors.type && (
                                        <p className="text-sm text-destructive">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="feedback-message">
                                        Message
                                    </Label>
                                    <Textarea
                                        id="feedback-message"
                                        placeholder="Tell us what's on your mind..."
                                        value={message}
                                        onChange={(e) =>
                                            setMessage(e.target.value)
                                        }
                                        rows={4}
                                        maxLength={2000}
                                        required
                                    />
                                    <div className="flex justify-between">
                                        {errors.message ? (
                                            <p className="text-sm text-destructive">
                                                {errors.message}
                                            </p>
                                        ) : (
                                            <span />
                                        )}
                                        <p className="text-xs text-muted-foreground tabular-nums">
                                            {message.length}/2000
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="mt-4">
                                <Button
                                    type="submit"
                                    disabled={processing || !message.trim()}
                                >
                                    <Send className="size-4" />
                                    {processing
                                        ? 'Sending...'
                                        : 'Send Feedback'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
