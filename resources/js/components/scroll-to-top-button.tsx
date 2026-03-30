import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

export function ScrollToTopButton() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const syncVisibility = () => {
            setVisible(window.scrollY > 300);
        };

        syncVisibility();
        window.addEventListener('scroll', syncVisibility, { passive: true });

        return () => window.removeEventListener('scroll', syncVisibility);
    }, []);

    if (!visible) {
        return null;
    }

    return (
        <Button
            variant="outline"
            size="icon"
            className="fixed right-6 bottom-6 z-50 rounded-full shadow-md"
            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            aria-label="Scroll to top"
        >
            <ArrowUp className="size-4" />
        </Button>
    );
}
