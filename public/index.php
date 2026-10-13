<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Windows Apache uses worker threads. Keep dotenv request-local: getenv/putenv
// can race across requests and are explicitly discouraged by phpdotenv.
if (PHP_OS_FAMILY === 'Windows' && PHP_SAPI === 'apache2handler') {
    \Illuminate\Support\Env::disablePutenv();
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
