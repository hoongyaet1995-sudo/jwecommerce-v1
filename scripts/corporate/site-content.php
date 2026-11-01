<?php
$root = $argv[1] ?? dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$configure = in_array('--configure', $argv, true);
if ($configure && config('database.connections.mysql.database') !== 'u762348670_jwcorporate') throw new RuntimeException('Independent corporate database required');
$pages = [];
foreach (['jw-homepage', 'jw-privacy-policy', 'jw-terms', 'jw-contact'] as $name) {
    $doc = json_decode(file_get_contents(__DIR__.'/../../docs/content/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    app(App\Domain\Cms\PageDocumentContract::class)->validate($doc);
    $draft = App\Models\PageDocument::where('stage', 'draft')->where('slug', $doc['slug'])->first();
    if ($draft) {
        if (($draft->document['title'] ?? '') !== $doc['title']) throw new RuntimeException('Existing page ownership mismatch');
        $page = App\Models\Page::findOrFail($draft->page_id);
        if ($draft->document !== $doc) app(App\Domain\Cms\PageDraftService::class)->save($page, $doc, $draft->lock_version);
    } else $page = app(App\Domain\Cms\PageCrudService::class)->create($doc);
    $page->refresh()->load('draftDocument');
    if (in_array('--publish', $argv, true)) app(App\Domain\Cms\PagePublicationService::class)->publish($page, $page->draftDocument->lock_version);
    $pages[$name] = $page->fresh()->load('draftDocument');
}
if ($configure) {
    $shell = $pages['jw-homepage']->draftDocument->document['shellDesign'];
    $profile = App\Models\StoreSetting::firstOrNew(['key'=>'shop_profile']);
    $profile->value = ['data'=>array_merge(data_get($profile->value, 'data', []), ['shopName'=>'JW Studio', 'shopLogo'=>'/images/cms/saas/jw-mark.svg', 'tagline'=>'Automate. Engage. Grow.', 'email'=>'sales@jwaistudio.online', 'city'=>'Bestari Jaya', 'state'=>'Selangor', 'country'=>'Malaysia'])];
    $profile->save();
    App\Models\StoreSetting::updateOrCreate(['key'=>'shop_name'], ['value'=>['data'=>'JW Studio']]);
    $service = app(App\Services\StorefrontShellSettingsService::class);
    $settings = $service->live();
    $settings['shellDesign'] = $shell;
    $service->save($settings);
    $home = $pages['jw-homepage'];
    app(App\Domain\Cms\HomepageSelection::class)->select($home, $home->draftDocument->lock_version);
    // This independent instance has no customer-authored menu content.
    foreach (['header','footer'] as $key) {
        $menu = App\Models\Menu::firstOrCreate(['key'=>$key], ['name'=>ucfirst($key)]);
        if ($menu->items()->exists()) throw new RuntimeException('Existing menu requires manual review: '.$key);
        $links = $key==='header'
            ? [['Platform','/#section-'.$home->draftDocument->document['sections'][1]['id']],['Solutions','/#section-'.$home->draftDocument->document['sections'][2]['id']],['Pricing','/#section-'.$home->draftDocument->document['sections'][5]['id']],['Contact','/pages/contact-jw-studio'],['Log in','/admin/login']]
            : [['Privacy Policy','/pages/privacy-policy'],['Terms & Conditions','/pages/terms-and-conditions'],['Contact','/pages/contact-jw-studio']];
        if ($key==='footer') {
            $parent = $menu->items()->create(['label'=>'Company','url'=>'/','position'=>0,'is_active'=>true]);
        }
        foreach ($links as $position=>[$label,$url]) $menu->items()->create(['parent_id'=>$key==='footer'?$parent->id:null,'label'=>$label,'url'=>$url,'position'=>$position,'is_active'=>true,'is_external'=>false]);
    }
}
echo json_encode(array_map(fn($p)=>['id'=>$p->id,'status'=>$p->status,'slug'=>$p->draftDocument->slug],$pages),JSON_PRETTY_PRINT).PHP_EOL;
