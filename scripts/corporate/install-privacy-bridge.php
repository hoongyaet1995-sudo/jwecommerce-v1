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
// Keep CMS assets separate from the legacy application's bundles.
$assetRoot = $public.'/jw-cms-assets';
if (!is_dir($assetRoot)) mkdir($assetRoot, 0755);
function copyPublicAssets(string $source, string $destination): void {
    if (!is_dir($destination)) mkdir($destination, 0755, true);
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot() || $entry->isLink()) continue;
        $to = $destination.'/'.$entry->getFilename();
        if ($entry->isDir()) copyPublicAssets($entry->getPathname(), $to);
        elseif ($entry->isFile()) {
            if (!copy($entry->getPathname(), $to)) throw new RuntimeException('Public asset copy failed');
            chmod($to, 0644);
        }
    }
}
foreach (['build', 'build-admin', 'css', 'js', 'marketing-studio'] as $directory) {
    $target = '/home/u762348670/apps/jw-corporate/public/'.$directory;
    $link = $assetRoot.'/'.$directory;
    if (is_link($link)) {
        if (readlink($link) !== $target) throw new RuntimeException('Unexpected asset link');
        unlink($link);
    }
    if (is_dir($target)) copyPublicAssets($target, $link);
}
$envPath = '/home/u762348670/apps/jw-corporate/.env';
$env = file_get_contents($envPath);
copy($envPath, $backup.'/corporate.env');
$assetSetting = 'ASSET_URL=https://www.jwaistudio.online/jw-cms-assets';
$env = preg_match('/^ASSET_URL=/m', $env)
    ? preg_replace('/^ASSET_URL=.*$/m', $assetSetting, $env)
    : rtrim($env)."\n".$assetSetting."\n";
file_put_contents($envPath, $env);
if (file_put_contents($public.'/jw-privacy-cms.php', $bridge) === false) throw new RuntimeException('Bridge write failed');
if (file_put_contents($htaccess, $after) === false) throw new RuntimeException('Rewrite write failed');
echo json_encode(['backup'=>$backup, 'paths'=>['/privacy','/privacy-policy','/pages/privacy-policy'], 'legacy_callbacks'=>'unchanged']).PHP_EOL;
