<?php
/** Install the approved Builder bundles where www has a separate public root. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$expected = '/home/u762348670/apps/jw-corporate';
$web = '/home/u762348670/domains/jwaistudio.online/public_html';
if (base_path() !== $expected || public_path() !== $web || parse_url(config('app.url'), PHP_URL_HOST) !== 'www.jwaistudio.online') {
    throw new RuntimeException('Restricted www release paths.');
}
$manifest = json_decode(file_get_contents(base_path('public/build-admin/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
$files = [];
foreach ($manifest as $entry) {
    $files[] = 'build-admin/'.$entry['file'];
    foreach ($entry['css'] ?? [] as $css) $files[] = 'build-admin/'.$css;
}
// Write the manifest last, after every referenced bundle is installed.
$files[] = 'build-admin/manifest.json';
$backup = storage_path('app/releases/passport-20261114/static-before');
$hashes = [];
foreach (array_unique($files) as $file) {
    if (str_contains($file, '..') || str_starts_with($file, '/') || !is_file(base_path('public/'.$file))) {
        throw new RuntimeException('Unexpected or missing build path.');
    }
}
foreach (array_unique($files) as $file) {
    $source = base_path('public/'.$file);
    $destination = $web.'/'.$file;
    if (is_file($destination) && !is_file($backup.'/'.$file)) {
        if (!is_dir(dirname($backup.'/'.$file))) mkdir(dirname($backup.'/'.$file), 0700, true);
        if (!copy($destination, $backup.'/'.$file)) throw new RuntimeException('Backup failed.');
    }
    if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0755, true);
    $temporary = $destination.'.passport-release.tmp';
    if (!copy($source, $temporary) || hash_file('sha256', $source) !== hash_file('sha256', $temporary) || !rename($temporary, $destination)) {
        throw new RuntimeException('Static installation failed.');
    }
    $hashes[$file] = hash_file('sha256', $destination);
}
file_put_contents(storage_path('app/releases/passport-20261114/public-files.json'), json_encode($hashes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['status' => 'static-installed', 'files' => count($hashes), 'manifestHash' => $hashes['build-admin/manifest.json']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
