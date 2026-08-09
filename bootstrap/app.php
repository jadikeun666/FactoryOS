<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    // Auto-discovery event/listener DIMATIKAN eksplisit (root cause bug
    // double-dispatch oee.updated, ditemukan & fix 2026-08-09): tanpa ini,
    // Laravel 12 otomatis scan app/Listeners/ dan mendaftarkan listener
    // yang polanya sesuai konvensi (method handle() dengan type-hint event)
    // DI ATAS registrasi manual Event::listen() di AppServiceProvider::boot()
    // -- hasilnya listener yang sama terdaftar dua kali untuk event yang
    // sama. Konfirmasi via `php artisan event:list` (listener duncul 2x)
    // dan Log::info diagnostik di listener (handle() dieksekusi 2x per
    // 1 dispatch event). @see docs/architecture.md § Events & Listeners
    ->withEvents(discover: false)
    ->create();
