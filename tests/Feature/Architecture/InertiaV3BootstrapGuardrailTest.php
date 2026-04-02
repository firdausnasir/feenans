<?php

use Illuminate\Support\Facades\File;

test('composer json pins inertia laravel to v3', function () {
    $composer = json_decode(File::get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require']['inertiajs/inertia-laravel'])->toBe('^3.0');
});

test('package json pins inertia react to v3', function () {
    $package = json_decode(File::get(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($package['dependencies']['@inertiajs/react'])->toBe('^3.0');
});

test('package json includes the inertia vite package', function () {
    $package = json_decode(File::get(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($package['dependencies'])->toHaveKey('@inertiajs/vite');
});

test('composer dev flow does not require a separate inertia ssr daemon', function () {
    $composer = json_decode(File::get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $package = json_decode(File::get(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $composerDevScript = $composer['scripts']['dev'] ?? [];
    $packageDevScript = $package['scripts']['dev'] ?? '';

    expect(implode(' ', $composerDevScript))->not->toContain('inertia:start-ssr');
    expect($packageDevScript)->not->toContain('inertia:start-ssr');
    expect($composer['scripts'])->not->toHaveKey('dev:ssr');
});
