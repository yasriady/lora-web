<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Clear stale package/route caches left by removed Composer packages.
$cacheDir = __DIR__.'/../bootstrap/cache';
$packagesCache = $cacheDir.'/packages.php';
if (is_file($packagesCache) && str_contains((string) file_get_contents($packagesCache), 'inertiajs')) {
    @unlink($packagesCache);
    @unlink($cacheDir.'/services.php');
    @unlink($cacheDir.'/routes-v7.php');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
