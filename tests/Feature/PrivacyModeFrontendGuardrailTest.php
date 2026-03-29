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
