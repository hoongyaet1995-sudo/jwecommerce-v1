<?php

use App\Models\CmsContactSubmission;
use App\Models\NewsletterSubscription;
use Illuminate\Contracts\Console\Kernel;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo json_encode([
    'contact' => CmsContactSubmission::query()->count(),
    'newsletter' => NewsletterSubscription::query()->count(),
    'newsletter_emails' => NewsletterSubscription::query()->orderBy('email')->pluck('email')->all(),
], JSON_THROW_ON_ERROR);
