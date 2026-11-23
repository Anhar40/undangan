<?php

// Vercel serverless entry point for Laravel.
// Ensure writable storage paths on the read-only serverless filesystem.
$storage = '/tmp/storage';
$directories = [
    $storage.'/app/public',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/testing',
    $storage.'/framework/views',
    $storage.'/logs',
    '/tmp/bootstrap/cache',
];

foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
}

$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['APP_STORAGE'] = $storage;
$_ENV['APP_STORAGE'] = $storage;

// Cache compiled artifacts in /tmp on Vercel.
$_ENV['APP_SERVICES_CACHE'] = '/tmp/bootstrap/cache/services.php';
$_ENV['APP_PACKAGES_CACHE'] = '/tmp/bootstrap/cache/packages.php';
$_ENV['APP_CONFIG_CACHE'] = '/tmp/bootstrap/cache/config.php';
$_ENV['APP_ROUTES_CACHE'] = '/tmp/bootstrap/cache/routes-v7.php';
$_ENV['APP_EVENTS_CACHE'] = '/tmp/bootstrap/cache/events.php';
$_SERVER['APP_SERVICES_CACHE'] = $_ENV['APP_SERVICES_CACHE'];
$_SERVER['APP_PACKAGES_CACHE'] = $_ENV['APP_PACKAGES_CACHE'];
$_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'];
$_SERVER['APP_ROUTES_CACHE'] = $_ENV['APP_ROUTES_CACHE'];
$_SERVER['APP_EVENTS_CACHE'] = $_ENV['APP_EVENTS_CACHE'];
$_SERVER['VIEW_COMPILED_PATH'] = $storage.'/framework/views';
$_ENV['VIEW_COMPILED_PATH'] = $storage.'/framework/views';

require __DIR__.'/../public/index.php';
