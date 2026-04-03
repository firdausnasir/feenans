<?php

function frontendFile(string $relativePath): string
{
    return file_get_contents(resource_path('js/'.$relativePath));
}

test('privacy mode hook reads the current value from page props', function () {
    $contents = frontendFile('contexts/privacy-mode-context.tsx');

    expect($contents)
        ->toContain('export function usePrivacyMode()')
        ->toContain('usePage().props.auth?.user')
        ->toContain('privacyMode: user?.privacy_mode ?? false,');
});

test('privacy mode provider wraps the inertia app instead of the layout', function () {
    $appContents = frontendFile('app.tsx');
    $ssrContents = frontendFile('ssr.tsx');
    $layoutContents = frontendFile('layouts/app-layout.tsx');

    expect($appContents)->toContain('<PrivacyModeProvider>');
    expect($ssrContents)->toContain('<PrivacyModeProvider>');
    expect($layoutContents)->not->toContain('<PrivacyModeProvider>');
});

test('browser entry uses inertia auto bootstrap for hydration-safe provider wrapping', function () {
    $contents = frontendFile('app.tsx');

    expect($contents)
        ->toContain('createInertiaApp({')
        ->toContain("pages: './pages',")
        ->toContain('withApp: (app) => (')
        ->toContain('<TooltipProvider delayDuration={0}>')
        ->toContain('<PrivacyModeProvider>')
        ->not->toContain('resolvePageComponent(')
        ->not->toContain('createRoot(');
});

test('browser entry initializes theme only in browser bootstrap', function () {
    $contents = frontendFile('app.tsx');

    expect($contents)
        ->toContain("if (typeof window !== 'undefined') {")
        ->toContain('initializeTheme();')
        ->not->toContain("\ninitializeTheme();");
});

test('dedicated ssr entry uses inertia auto bootstrap while keeping provider wrappers and server rendering', function () {
    $contents = frontendFile('ssr.tsx');

    expect($contents)
        ->toContain('createInertiaApp(')
        ->toContain('createServer(')
        ->toContain('withApp:')
        ->toContain('TooltipProvider')
        ->toContain('PrivacyModeProvider')
        ->toContain('ReactDOMServer.renderToString')
        ->toMatch('/pages:\\s*[\'\"]\\.\\/pages[\'\"],?/')
        ->not->toContain('resolvePageComponent(');
});

test('theme initialization is idempotent and only binds one system listener', function () {
    $contents = frontendFile('hooks/use-appearance.tsx');

    $applyThemePosition = strpos($contents, 'applyTheme(currentAppearance);');
    $listenerPosition = strpos($contents, "mediaQuery()?.addEventListener('change', handleSystemThemeChange);");
    $initializedPosition = strpos($contents, 'hasInitializedTheme = true;');

    expect($contents)
        ->toContain('let hasInitializedTheme = false;')
        ->toContain('if (hasInitializedTheme) {')
        ->toContain("mediaQuery()?.addEventListener('change', handleSystemThemeChange);")
        ->not->toContain('// Set up system theme change listener');

    expect($applyThemePosition)->toBeInt();
    expect($listenerPosition)->toBeInt();
    expect($initializedPosition)->toBeInt();
    expect($initializedPosition)->toBeGreaterThan($applyThemePosition);
    expect($initializedPosition)->toBeGreaterThan($listenerPosition);
});

test('dashboard amount rendering goes through centralized formatters', function () {
    $contents = frontendFile('pages/ledgers/dashboard.tsx');

    expect($contents)
        ->not->toContain('`RM${v}`')
        ->not->toContain('Number(value).toLocaleString()')
        ->not->toContain('MASKED_AMOUNT');
});

test('cash flow amount rendering does not use bare star masking', function () {
    $contents = frontendFile('pages/ledgers/reports/cash-flow.tsx');

    expect($contents)->not->toContain("privacyMode ? '***'");
});

test('transaction edit page does not apply privacy mode to amounts', function () {
    $contents = frontendFile('pages/ledgers/transactions/edit.tsx');

    expect($contents)
        ->not->toContain("import { usePrivacyMode } from '@/contexts/privacy-mode-context';")
        ->not->toContain('const { privacyMode } = usePrivacyMode();')
        ->not->toContain('privacyMode,');
});

test('add transaction modal does not apply privacy mode to amounts', function () {
    $contents = frontendFile('components/add-transaction-modal.tsx');

    expect($contents)
        ->not->toContain('usePrivacyMode')
        ->not->toContain('MASKED_AMOUNT')
        ->not->toContain('privacyMode');
});

test('sidebar layout keeps the sticky header outside the overflow clipping container', function () {
    $contents = frontendFile('layouts/app/app-sidebar-layout.tsx');

    expect($contents)
        ->toContain('<AppContent variant="sidebar">')
        ->toContain('<AppSidebarHeader breadcrumbs={breadcrumbs} />')
        ->toContain('className="min-w-0 overflow-x-hidden"')
        ->not->toContain('<AppContent variant="sidebar" className="overflow-x-hidden">');
});

test('accounts desktop table matches the transactions table text size baseline', function () {
    $contents = frontendFile('pages/ledgers/accounts/index.tsx');

    expect($contents)->toContain('<table className="mt-1 w-full text-sm">');
});
