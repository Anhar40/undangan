<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\SweepExpiredInvitations;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$builder = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureUserActive::class,
            'preview.expiry' => SweepExpiredInvitations::class,
        ]);

        // Midtrans posts the notification directly; it has no browser session/CSRF token.
        $middleware->validateCsrfTokens(except: ['payment/notification']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    });

$app = $builder->create();

if (($_ENV['VERCEL'] ?? $_SERVER['VERCEL'] ?? null) !== null || ($_ENV['LARAVEL_STORAGE_PATH'] ?? $_SERVER['LARAVEL_STORAGE_PATH'] ?? null) === '/tmp/storage') {
    $storage = '/tmp/storage';
    $bootstrap = '/tmp/bootstrap';
    $bootstrapCache = $bootstrap.'/cache';
    foreach ([$storage.'/app/public', $storage.'/framework/cache/data', $storage.'/framework/sessions', $storage.'/framework/views', $storage.'/logs', $bootstrapCache] as $dir) {
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
    $app->useStoragePath($storage);
    $app->useBootstrapPath($bootstrap);

    $sourceBootstrap = dirname(__DIR__).'/bootstrap';
    foreach (['providers.php'] as $file) {
        $src = $sourceBootstrap.'/'.$file;
        $dst = $bootstrap.'/'.$file;
        if (is_file($src) && ! is_file($dst)) {
            @copy($src, $dst);
        }
    }
    $sourceCache = $sourceBootstrap.'/cache';
    foreach (['packages.php', 'services.php', 'config.php', 'routes-v7.php', 'events.php'] as $file) {
        $src = $sourceCache.'/'.$file;
        $dst = $bootstrapCache.'/'.$file;
        if (is_file($src) && ! is_file($dst)) {
            @copy($src, $dst);
        }
    }
}

return $app;
