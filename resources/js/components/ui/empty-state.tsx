import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type EmptyStateProps = {
    icon: React.ReactNode;
    title: string;
    description: string;
    action?: { label: string; href: string };
};

export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-4 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                {icon}
            </div>
            <div className="space-y-1">
                <h3 className="font-medium">{title}</h3>
                <p className="text-sm text-muted-foreground max-w-[280px]">
                    {description}
                </p>
            </div>
            {action && (
                <Button asChild size="sm">
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            )}
        </div>
    );
}
