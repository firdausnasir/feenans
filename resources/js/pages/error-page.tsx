import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

const titles: Record<number, string> = {
    503: 'Service Unavailable',
    500: 'Server Error',
    404: 'Page Not Found',
    403: 'Forbidden',
};

const descriptions: Record<number, string> = {
    503: 'Sorry, we are doing some maintenance. Please check back soon.',
    500: 'Whoops, something went wrong on our servers.',
    404: 'Sorry, the page you are looking for could not be found.',
    403: 'Sorry, you are forbidden from accessing this page.',
};

export default function ErrorPage({ status }: { status: number }) {
    const title = titles[status] ?? 'Error';
    const description =
        descriptions[status] ?? 'An unexpected error occurred.';

    return (
        <>
            <Head title={`${status} - ${title}`} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-background p-6 text-center">
                <div className="mb-6 flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                    <AppLogoIcon className="size-7 fill-current" />
                </div>

                <p className="text-6xl font-bold tabular-nums text-foreground">
                    {status}
                </p>
                <h1 className="mt-2 text-xl font-semibold text-foreground">
                    {title}
                </h1>
                <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>

                <div className="mt-8 flex items-center gap-3">
                    <Button
                        variant="outline"
                        onClick={() => {
                            if (window.history.length > 1) {
                                router.visit(
                                    document.referrer ||
                                        window.location.origin,
                                );
                            } else {
                                router.visit('/');
                            }
                        }}
                    >
                        <ArrowLeft className="mr-1.5 size-4" />
                        Go Back
                    </Button>
                    <Button asChild>
                        <Link href="/">
                            <Home className="mr-1.5 size-4" />
                            Home
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
