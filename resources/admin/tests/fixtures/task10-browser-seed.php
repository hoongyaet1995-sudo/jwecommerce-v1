<?php

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

User::query()->create([
    'name' => 'Task 10 Admin',
    'email' => 'task10-admin@example.test',
    'password' => Hash::make('Task10-password!'),
    'is_admin' => true,
]);

$header = Menu::query()->create(['key' => 'header', 'name' => 'Task 10 Header']);
MenuItem::query()->create([
    'menu_id' => $header->id,
    'label' => 'Task 10 Real Menu',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);

$footer = Menu::query()->create(['key' => 'footer', 'name' => 'Task 10 Footer']);
$footerSection = MenuItem::query()->create([
    'menu_id' => $footer->id,
    'label' => 'Saved Footer Menu',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);
MenuItem::query()->create([
    'menu_id' => $footer->id,
    'parent_id' => $footerSection->id,
    'label' => 'Saved Catalog Link',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);

$category = Category::query()->create([
    'slug' => 'task-10-category',
    'name' => 'Task 10 Category',
    'is_active' => true,
]);
$product = Product::query()->create([
    'category_id' => $category->id,
    'handle' => 'saved-catalog-product',
    'title' => 'Saved Catalog Product',
    'subtitle' => 'Database-backed public summary',
    'brand' => 'JW Studio',
    'base_price' => 21900,
    'compare_at_price' => 24900,
    'status' => 'active',
    'tags' => ['Database fixture'],
]);
ProductImage::query()->create([
    'product_id' => $product->id,
    'path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&q=80',
    'alt' => 'JW Studio mark',
    'position' => 1,
]);

$page = Page::query()->create([
    'title' => 'Task 10 legacy identity',
    'slug' => 'task-10-legacy',
    'status' => 'published',
    'is_published' => true,
]);

$draft = [
    'title' => 'Task 10 Saved Draft',
    'slug' => 'task-10-saved-draft',
    'description' => 'Saved draft rendered through the shared public entry.',
    'template' => 'landing',
    'heroImage' => null,
    'seoTitle' => 'Task 10 Saved Preview',
    'seoDescription' => 'Authenticated saved-preview browser fixture.',
    'sections' => [
        [
            'id' => 'saved-hero',
            'type' => 'hero_banner',
            'title' => 'SAVED MYSQL HERO',
            'subtitle' => 'Authenticated preview fixture',
            'content' => 'This copy is owned by the canonical saved draft.',
            'mediaUrl' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&q=80',
            'mediaType' => 'image',
            'buttonText' => 'Explore the saved catalog',
            'buttonLink' => '/products',
            'isVisible' => true,
        ],
        [
            'id' => 'saved-products',
            'type' => 'product_grid',
            'title' => 'DATABASE CATALOG FACTS',
            'subtitle' => 'Resolved by Task 7 runtime adapters',
            'content' => 'The product below comes from the isolated MySQL-compatible fixture.',
            'productIds' => [(string) $product->id],
            'columnsCount' => 3,
            'isVisible' => true,
        ],
        [
            'id' => 'saved-story',
            'type' => 'rich_text',
            'title' => 'SAVED CLOSING STORY',
            'subtitle' => 'Shared markup proof',
            'content' => 'The same section renderer owns Admin Canvas and protected Preview. This final block provides an explicit outline navigation target.',
            'quote' => 'Canonical saved documents remain the rendering source of truth.',
            'authorName' => 'JW Studio',
            'authorRole' => 'Storefront renderer',
            'isVisible' => true,
        ],
        [
            'id' => 'hidden-secret',
            'type' => 'rich_text',
            'title' => 'TASK 10 HIDDEN SECRET',
            'content' => 'TASK 10 HIDDEN SECRET',
            'isVisible' => false,
        ],
    ],
];
$published = $draft;
$published['title'] = 'Task 10 Old Published';
$published['slug'] = 'task-10-old-published';
$published['seoTitle'] = 'Task 10 Old Published';
$published['sections'][0]['title'] = 'OLD PUBLISHED HERO';

app(PageDocumentContract::class)->validate($draft);
app(PageDocumentContract::class)->validate($published);

foreach (['draft' => $draft, 'published' => $published] as $stage => $document) {
    $page->documents()->create([
        'stage' => $stage,
        'slug' => $document['slug'],
        'schema_version' => PageDocumentContract::VERSION,
        'lock_version' => $stage === 'draft' ? 3 : 2,
        'content_hash' => PageDocumentHasher::hash($document),
        'document' => $document,
    ]);
}

echo $page->id.PHP_EOL;
