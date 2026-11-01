<?php
// Explicit CMS content migration. Run locally first; --publish is a separate action.
$root = $argv[1] ?? dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (app()->environment('production') && config('database.connections.mysql.database') !== 'u762348670_jwcorporate') {
    throw new RuntimeException('Corporate database required');
}
$document = json_decode(file_get_contents(__DIR__.'/../../docs/content/jw-privacy-policy.json'), true, 512, JSON_THROW_ON_ERROR);
app(App\Domain\Cms\PageDocumentContract::class)->validate($document);
$draft = App\Models\PageDocument::where('stage', 'draft')->where('slug', $document['slug'])->first();
if ($draft) {
    $page = App\Models\Page::findOrFail($draft->page_id);
    if ($draft->document !== $document) {
        app(App\Domain\Cms\PageDraftService::class)->save($page, $document, $draft->lock_version, 'manual');
    }
} else {
    $page = app(App\Domain\Cms\PageCrudService::class)->create($document);
}
$page->refresh()->load('draftDocument');
if (in_array('--publish', $argv, true)) {
    app(App\Domain\Cms\PagePublicationService::class)->publish($page, $page->draftDocument->lock_version);
}
echo json_encode(['page_id'=>$page->id, 'slug'=>$document['slug'], 'status'=>$page->fresh()->status], JSON_PRETTY_PRINT).PHP_EOL;
