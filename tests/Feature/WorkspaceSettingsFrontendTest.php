<?php

function workspaceSettingsFrontendFile(string $relativePath): string
{
    return file_get_contents(resource_path('js/'.$relativePath));
}

test('workspace settings page exposes tabbed settings navigation', function () {
    $contents = workspaceSettingsFrontendFile('pages/ledgers/settings/index.tsx');

    expect($contents)
        ->toContain('const workspaceSettingsTabs')
        ->toContain("value: 'general'")
        ->toContain("value: 'account-types'")
        ->toContain("value: 'webhook-keys'")
        ->toContain("value: 'data-export'")
        ->toContain("value: 'danger-zone'")
        ->toContain('aria-label="Workspace settings"')
        ->toContain('activeWorkspaceTab');
});

test('workspace webhook key creation warns that keys are only shown once', function () {
    $contents = workspaceSettingsFrontendFile('components/ledger-webhook-token-management.tsx');

    expect($contents)
        ->toContain('shown only once')
        ->toContain('cannot be shown again')
        ->toContain('Copy it now');
});
