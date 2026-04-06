import assert from 'node:assert/strict';
import test from 'node:test';

const { appSidebarHeaderClassName, welcomeHeaderClassName, sidebarInsetClassName } = await import(
    new URL('./layout-header-classes.ts', import.meta.url).href
);

test('app sidebar header does not use sticky positioning', () => {
    assert.match(appSidebarHeaderClassName, /\bh-16\b/);
    assert.match(appSidebarHeaderClassName, /\bborder-b\b/);
    assert.doesNotMatch(appSidebarHeaderClassName, /\bsticky\b/);
    assert.doesNotMatch(appSidebarHeaderClassName, /\btop-0\b/);
});

test('welcome header does not use sticky positioning', () => {
    assert.match(welcomeHeaderClassName, /\bborder-b\b/);
    assert.match(welcomeHeaderClassName, /\bbackdrop-blur-sm\b/);
    assert.doesNotMatch(welcomeHeaderClassName, /\bsticky\b/);
    assert.doesNotMatch(welcomeHeaderClassName, /\btop-0\b/);
});

test('sidebar inset prevents page-level horizontal overflow', () => {
    assert.match(sidebarInsetClassName, /\bmin-w-0\b/);
    assert.doesNotMatch(sidebarInsetClassName, /\boverflow-x-hidden\b/);
});
