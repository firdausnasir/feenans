<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title') - {{ config('app.name', 'Feenans') }}</title>

        <script>
            (function() {
                const appearance = document.cookie.match(/appearance=(\w+)/)?.[1] || 'system';

                if (appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <style>
            :root {
                --background: oklch(1 0 0);
                --foreground: oklch(0.145 0 0);
                --muted-foreground: oklch(0.556 0 0);
                --primary: oklch(0.205 0 0);
                --primary-foreground: oklch(0.985 0 0);
                --border: oklch(0.922 0 0);
                --muted: oklch(0.97 0 0);
            }

            .dark {
                --background: oklch(0.145 0 0);
                --foreground: oklch(0.985 0 0);
                --muted-foreground: oklch(0.708 0 0);
                --primary: oklch(0.985 0 0);
                --primary-foreground: oklch(0.205 0 0);
                --border: oklch(0.269 0 0);
                --muted: oklch(0.269 0 0);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            html {
                background-color: var(--background);
            }

            body {
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
                background-color: var(--background);
                color: var(--foreground);
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            header {
                border-bottom: 1px solid var(--border);
                padding: 0 1rem;
            }

            .header-inner {
                max-width: 72rem;
                margin: 0 auto;
                display: flex;
                align-items: center;
                height: 4rem;
                gap: 0.5rem;
            }

            .logo-box {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 0.375rem;
                background-color: var(--primary);
                color: var(--primary-foreground);
            }

            .logo-text {
                font-size: 1.25rem;
                font-weight: 700;
                letter-spacing: -0.025em;
            }

            main {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
            }

            .error-content {
                text-align: center;
                max-width: 28rem;
            }

            .error-code {
                font-size: 6rem;
                font-weight: 700;
                line-height: 1;
                letter-spacing: -0.05em;
                color: var(--foreground);
                opacity: 0.15;
            }

            .error-title {
                margin-top: 1rem;
                font-size: 1.5rem;
                font-weight: 600;
                letter-spacing: -0.025em;
            }

            .error-message {
                margin-top: 0.75rem;
                color: var(--muted-foreground);
                line-height: 1.625;
            }

            .error-actions {
                margin-top: 2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.5rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 500;
                font-family: inherit;
                border-radius: 0.375rem;
                text-decoration: none;
                transition: opacity 0.15s;
                cursor: pointer;
            }

            .btn:hover {
                opacity: 0.85;
            }

            .btn-primary {
                background-color: var(--primary);
                color: var(--primary-foreground);
                border: none;
            }

            .btn-outline {
                background-color: transparent;
                color: var(--foreground);
                border: 1px solid var(--border);
            }

            footer {
                border-top: 1px solid var(--border);
                padding: 1.5rem 1rem;
            }

            .footer-inner {
                max-width: 72rem;
                margin: 0 auto;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .footer-text {
                font-size: 0.875rem;
                color: var(--muted-foreground);
            }
        </style>
    </head>
    <body>
        <header>
            <div class="header-inner">
                <a href="/" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: inherit;">
                    <div class="logo-box">
                        <svg width="20" height="20" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 6h16v4H12v4h10v4H12v10H8V6z" fill="currentColor" />
                        </svg>
                    </div>
                    <span class="logo-text">Feenans</span>
                </a>
            </div>
        </header>

        <main>
            <div class="error-content">
                <div class="error-code">@yield('code')</div>
                <h1 class="error-title">@yield('title')</h1>
                <p class="error-message">@yield('message')</p>
                <div class="error-actions">
                    @yield('actions', '')
                    <a href="/" class="btn btn-primary">Go Home</a>
                    <button onclick="history.back()" class="btn btn-outline">Go Back</button>
                </div>
            </div>
        </main>

        <footer>
            <div class="footer-inner">
                <p class="footer-text">&copy; {{ date('Y') }} Feenans. Your finances, your privacy.</p>
            </div>
        </footer>
    </body>
</html>
