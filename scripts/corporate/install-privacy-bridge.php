<?php
// Deployment-only compatibility entrypoint, preserving all legacy callbacks.
$legacy = '/home/u762348670/domains/jwaistudio.online/public_html';
$public = realpath($legacy.'/public');
if ($public !== $legacy.'/public' || !is_file('/home/u762348670/apps/jw-corporate/artisan')) {
    throw new RuntimeException('Unexpected deployment roots');
}
$htaccess = $public.'/.htaccess';
$before = file_get_contents($htaccess);
$marker = '# JW corporate CMS privacy bridge';
$rule = $marker."\n    RewriteRule ^(?:privacy|privacy-policy|pages/privacy-policy)/?$ jw-privacy-cms.php [L]\n";
$backup = '/home/u762348670/backups/jw-privacy-'.date('Ymd-His');
if (!mkdir($backup, 0700, true)) throw new RuntimeException('Backup directory unavailable');
copy($htaccess, $backup.'/public.htaccess');
if (is_file($public.'/jw-privacy-cms.php')) copy($public.'/jw-privacy-cms.php', $backup.'/jw-privacy-cms.php');
if (!str_contains($before, $marker)) {
    if (substr_count($before, 'RewriteEngine On') !== 1) throw new RuntimeException('Unexpected rewrite configuration');
    $after = str_replace('RewriteEngine On', "RewriteEngine On\n\n    ".$rule, $before);
} else $after = $before;
$bridge = file_get_contents(__DIR__.'/privacy-bridge.php');
if (file_put_contents($public.'/jw-privacy-cms.php', $bridge) === false) throw new RuntimeException('Bridge write failed');
if (file_put_contents($htaccess, $after) === false) throw new RuntimeException('Rewrite write failed');
echo json_encode(['backup'=>$backup, 'paths'=>['/privacy','/privacy-policy','/pages/privacy-policy'], 'legacy_callbacks'=>'unchanged']).PHP_EOL;
