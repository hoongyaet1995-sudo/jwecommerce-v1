<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\RateLimiter;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
RateLimiter::clear(md5('cms-contactcms-contact:127.0.0.1'));
RateLimiter::clear(md5('cms-newslettercms-newsletter:127.0.0.1'));
