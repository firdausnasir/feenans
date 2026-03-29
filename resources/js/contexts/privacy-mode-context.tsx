import { router, usePage } from '@inertiajs/react';
import { createContext, useContext, useState } from 'react';
import type { ReactNode } from 'react';
import PrivacyModeController from '@/actions/App/Http/Controllers/PrivacyModeController';

type PrivacyModeContextType = {
    toggling: boolean;
    togglePrivacyMode: () => void;
};

const PrivacyModeContext = createContext<PrivacyModeContextType>({
    toggling: false,
    togglePrivacyMode: () => {},
});

export function PrivacyModeProvider({ children }: { children: ReactNode }) {
    const [toggling, setToggling] = useState(false);

    function togglePrivacyMode() {
        if (toggling) {
            return;
        }

        setToggling(true);
        router.patch(
            PrivacyModeController.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setToggling(false),
            },
        );
    }

    return (
        <PrivacyModeContext.Provider value={{ toggling, togglePrivacyMode }}>
            {children}
        </PrivacyModeContext.Provider>
    );
}

export function usePrivacyMode() {
    const { toggling, togglePrivacyMode } = useContext(PrivacyModeContext);
    const user = usePage().props.auth?.user;

    return {
        privacyMode: user?.privacy_mode ?? false,
        toggling,
        togglePrivacyMode,
    };
}
