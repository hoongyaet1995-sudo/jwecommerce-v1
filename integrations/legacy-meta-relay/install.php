<?php
// Run with: php install.php /absolute/path/to/retained/application
$root = realpath($argv[1] ?? '');
if (!$root || !is_file($root.'/artisan') || !is_dir($root.'/app/Services/Social')) throw new RuntimeException('Invalid retained application root');
$patches = [
    'app/Http/Controllers/Api/MetaSocialWebhookController.php' => function ($source) {
        if (str_contains($source, 'RelayMetaMessengerToEcommerce::class')) return $source;
        $needle = "        \$payload = \$request->all();";
        if (substr_count($source, $needle) !== 1) throw new RuntimeException('Controller patch anchor mismatch');
        return str_replace($needle, "        app(\\App\\Services\\Social\\RelayMetaMessengerToEcommerce::class)->capture(\$request);\n".$needle, $source);
    },
    'config/services.php' => function ($source) {
        if (str_contains($source, 'ecommerce_relay_enabled')) return $source;
        $needle = "\n    'facebook' => [";
        if (substr_count($source, $needle) !== 1) throw new RuntimeException('Configuration patch anchor mismatch');
        return str_replace($needle, $needle."\n        'ecommerce_relay_enabled' => env('FACEBOOK_ECOMMERCE_RELAY_ENABLED', false),\n        'ecommerce_relay_page_ids' => array_values(array_filter(array_map('trim', explode(',', env('FACEBOOK_ECOMMERCE_RELAY_PAGE_IDS', ''))))),", $source);
    },
    'routes/console.php' => function ($source) {
        if (str_contains($source, '--queue=meta-ecommerce-relay')) return $source;
        return $source."\n// Retained Meta ingress: isolated, bounded Ecommerce relay worker.\nSchedule::command('queue:work database --queue=meta-ecommerce-relay --stop-when-empty --max-time=50 --timeout=25 --tries=12')\n    ->everyMinute()\n    ->withoutOverlapping();\n";
    },
];
$writes = [];
foreach ($patches as $path => $patch) {
    $writes[$path] = $patch(file_get_contents($root.'/'.$path));
}
$writes['app/Jobs/ForwardMetaMessengerToEcommerce.php'] = file_get_contents(__DIR__.'/ForwardMetaMessengerToEcommerce.php');
$writes['app/Services/Social/RelayMetaMessengerToEcommerce.php'] = file_get_contents(__DIR__.'/RelayMetaMessengerToEcommerce.php');
$backup = $root.'/storage/app/meta-relay-backup-'.date('YmdHis');
mkdir($backup, 0700, true);
foreach ($writes as $path => $source) {
    if (is_file($root.'/'.$path)) copy($root.'/'.$path, $backup.'/'.str_replace('/', '_', $path));
    if (!is_dir(dirname($root.'/'.$path))) mkdir(dirname($root.'/'.$path), 0755, true);
    file_put_contents($root.'/'.$path, $source);
    echo $path.' '.hash('sha256', $source).PHP_EOL;
}
