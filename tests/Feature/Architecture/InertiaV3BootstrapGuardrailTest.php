<?php

use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

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

test('vite config registers the inertia vite plugin while preserving the dedicated ssr entry', function () {
    $config = File::get(base_path('vite.config.ts'));

    expect($config)
        ->toContain("import inertia from '@inertiajs/vite';")
        ->toContain("input: ['resources/css/app.css', 'resources/js/app.tsx']")
        ->toContain('inertia({')
        ->toContain("entry: 'resources/js/ssr.tsx'")
        ->toContain("ssr: 'resources/js/ssr.tsx'");
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

test('inertia config uses the v3 pages structure', function () {
    $config = File::get(config_path('inertia.php'));

    expect($config)
        ->toContain("'pages' => [")
        ->toContain("resource_path('js/pages')")
        ->toContain("'paths' => [")
        ->toContain("'extensions' => [")
        ->toContain("'testing' => [")
        ->toContain("'ensure_pages_exist' => true")
        ->not->toContain("'page_paths' => [")
        ->not->toContain("'page_extensions' => [");
});

test('root inertia blade view loads the shared css and js app entries instead of page specific vite entries', function () {
    $view = File::get(resource_path('views/app.blade.php'));

    expect($view)
        ->toContain('@viteReactRefresh')
        ->toContain("@vite(['resources/css/app.css', 'resources/js/app.tsx'])")
        ->not->toContain("@vite('resources/js/app.tsx')")
        ->not->toContain('resources/js/pages/')
        ->not->toContain('$page[\'component\']');
});

test('root js bootstrap does not own the app css entry', function () {
    $bootstrap = File::get(resource_path('js/app.tsx'));

    expect($bootstrap)->not->toContain("import '../css/app.css';");
});

test('route model bindings run before inertia shared props middleware', function () {
    $middleware = app()->make(Kernel::class)->getMiddlewarePriority();

    expect(array_search(SubstituteBindings::class, $middleware, true))
        ->toBeLessThan(array_search(Middleware::class, $middleware, true));
});
