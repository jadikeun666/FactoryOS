<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'FactoryOS') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /*
             * Halaman auth (login/register/dst) SENGAJA tema gelap tetap,
             * TIDAK ikut toggle dark/light seperti halaman lain -- app.js
             * di sini pakai Alpine polos (bukan Vue), jadi useTheme.js
             * composable tidak bisa dipakai. data-theme="dark" statis di
             * <html> sudah cukup, semua var(--token) resolve otomatis
             * tanpa JS. Keputusan: 2026-08-09, checkpoint modernisasi
             * visual auth pages.
             */
html, body {
                height: 100%;
                margin: 0;
            }

            body {
                display: flex;
                background: var(--page-bg);
                font-family: var(--font-body);
                overflow: hidden;
            }

            .auth-shell {
                display: flex;
                width: 100%;
                height: 100vh;
            }

            .auth-hero {
                position: relative;
                flex: 1.1;
                height: 100%;
                background-image:
                    linear-gradient(180deg, rgba(20, 22, 27, 0.55) 0%, rgba(20, 22, 27, 0.85) 100%),
                    url('{{ asset('images/auth-hero.jpg') }}');
                background-size: cover;
                background-position: center;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                padding: 2.5rem;
                animation: hero-fade-in 0.7s ease both;
            }

            @keyframes hero-fade-in {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .auth-hero__brand {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 1.5rem;
            }

            .auth-hero__brand-dot {
                width: 0.6rem;
                height: 0.6rem;
                border-radius: 2px;
                background: var(--signal-green);
                box-shadow: 0 0 8px 2px rgba(74, 155, 110, 0.6);
            }

            .auth-hero__brand-text {
                font-family: var(--font-display);
                font-size: 1.0625rem;
                font-weight: 700;
                color: #E8EAED;
                letter-spacing: 0.02em;
            }

            .auth-hero__tagline {
                max-width: 26rem;
                font-size: 1.375rem;
                font-weight: 600;
                color: #E8EAED;
                line-height: 1.35;
                margin: 0 0 0.6rem;
            }

            .auth-hero__sub {
                max-width: 24rem;
                font-size: 0.875rem;
                color: rgba(232, 234, 237, 0.7);
                line-height: 1.5;
                margin: 0;
            }

.auth-panel {
                flex: 0.9;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                background: var(--page-bg);
                animation: panel-fade-in 0.5s ease 0.1s both;
                overflow-y: auto;
            }

            @keyframes panel-fade-in {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .auth-card {
                width: 100%;
                max-width: 22rem;
            }

            .auth-card__logo-row {
                display: flex;
                justify-content: center;
                margin-bottom: 1.5rem;
            }

            .auth-label {
                display: block;
                font-family: var(--font-display);
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.02em;
                color: var(--data-ink-muted);
                margin-bottom: 0.3rem;
            }

            .auth-input {
                display: block;
                width: 100%;
                padding: 0.6rem 0.75rem;
                font-size: 0.875rem;
                font-family: var(--font-body);
                color: var(--data-ink);
                background: var(--surface-steel);
                border: 1px solid var(--hairline-border);
                border-radius: 6px;
                box-sizing: border-box;
                transition: border-color 0.15s ease;
            }

            .auth-input:focus {
                outline: none;
                border-color: var(--signal-amber);
                box-shadow: 0 0 0 1px var(--signal-amber);
            }

            .auth-btn-primary {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.6rem 1.25rem;
                font-family: var(--font-display);
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: #1C1F26;
                background: var(--signal-amber);
                border: 1px solid transparent;
                border-radius: 6px;
                cursor: pointer;
                transition: filter 0.15s ease, transform 0.12s ease;
            }

            .auth-btn-primary:hover {
                filter: brightness(1.08);
            }

            .auth-btn-primary:active {
                transform: translateY(1px);
            }

            .auth-error-list {
                margin: 0.4rem 0 0;
                padding: 0;
                list-style: none;
                font-size: 0.75rem;
                color: var(--signal-red);
            }

            .auth-status {
                font-size: 0.8125rem;
                font-weight: 500;
                color: var(--signal-green);
                margin-bottom: 1rem;
            }

            .auth-checkbox {
                width: 1rem;
                height: 1rem;
                accent-color: var(--signal-amber);
            }

            .auth-link {
                font-size: 0.8125rem;
                color: var(--data-ink-muted);
                text-decoration: underline;
            }

            .auth-link:hover {
                color: var(--data-ink);
            }

            .auth-title {
                font-family: var(--font-display);
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--data-ink);
                margin: 0 0 0.35rem;
            }

            .auth-subtitle {
                font-size: 0.8125rem;
                color: var(--data-ink-muted);
                margin: 0 0 1.5rem;
            }

            @media (max-width: 860px) {
                .auth-hero {
                    display: none;
                }
                .auth-panel {
                    flex: 1;
                }
            }
        </style>
    </head>
    <body class="antialiased">
        <div class="auth-shell">
            <div class="auth-hero">
                <div class="auth-hero__brand">
                    <span class="auth-hero__brand-dot"></span>
                    <span class="auth-hero__brand-text">FactoryOS</span>
                </div>
                <p class="auth-hero__tagline">Production Intelligence Platform</p>
                <p class="auth-hero__sub">
                    Satu platform terpadu untuk penjadwalan produksi, monitoring OEE,
                    dan optimasi inventory — menggantikan Excel dan WhatsApp.
                </p>
            </div>

            <div class="auth-panel">
                <div class="auth-card">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
