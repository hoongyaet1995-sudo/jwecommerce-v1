<?php

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Category;
use App\Models\Collection as CatalogCollection;
use App\Models\Coupon;
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
    'name' => 'Task 11 Admin',
    'email' => 'task11-admin@example.test',
    'password' => Hash::make('Task11-password!'),
    'is_admin' => true,
]);

$header = Menu::query()->create(['key' => 'header', 'name' => 'Task 11 Header']);
MenuItem::query()->create([
    'menu_id' => $header->id,
    'label' => 'Task 11 Real Menu',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);

$footer = Menu::query()->create(['key' => 'footer', 'name' => 'Task 11 Footer']);
$footerSection = MenuItem::query()->create([
    'menu_id' => $footer->id,
    'label' => 'Task 11 Customer Care',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);
MenuItem::query()->create([
    'menu_id' => $footer->id,
    'parent_id' => $footerSection->id,
    'label' => 'Task 11 Catalog Link',
    'url' => '/products',
    'position' => 1,
    'is_active' => true,
    'link_type' => 'custom_url',
]);

$electronics = Category::query()->create([
    'slug' => 'task-11-electronics',
    'name' => 'Electronics & Gadgets',
    'is_active' => true,
]);
$audio = Category::query()->create([
    'slug' => 'task-11-premium-audio',
    'name' => 'Premium Audio',
    'is_active' => true,
]);
$bestSellers = CatalogCollection::query()->create([
    'slug' => 'task-11-best-sellers',
    'title' => 'Best Sellers 2026',
    'type' => 'manual',
    'status' => 'active',
    'is_active' => true,
]);
$studioEssentials = CatalogCollection::query()->create([
    'slug' => 'task-11-studio-essentials',
    'title' => 'Studio Essentials',
    'type' => 'manual',
    'status' => 'active',
    'is_active' => true,
]);

$makeProduct = function (Category $category, string $handle, string $title, string $summary, int $price): Product {
    $product = Product::query()->create([
        'category_id' => $category->id,
        'handle' => $handle,
        'title' => $title,
        'subtitle' => $summary,
        'brand' => 'Aurora Audio',
        'base_price' => $price,
        'status' => 'active',
        'tags' => ['Task 11 fixture'],
    ]);
    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1600&q=80',
        'alt' => $title,
        'position' => 1,
    ]);

    return $product;
};

$first = $makeProduct($electronics, 'task-11-reference-one', 'Aurora Reference One', 'First product by canonical database order.', 21900);
$second = $makeProduct($electronics, 'task-11-reference-two', 'Aurora Reference Two', 'Second product inside the configured limit.', 24900);
$third = $makeProduct($electronics, 'task-11-reference-three', 'Aurora Reference Three', 'Third product inside the configured limit.', 27900);
$excluded = $makeProduct($electronics, 'task-11-reference-four', 'Aurora Limit Excluded', 'Must not render after the tab limit.', 29900);
$premium = $makeProduct($audio, 'task-11-studio-pro', 'Aurora Studio Pro Wireless Headphones', 'Premium Audio runtime projection.', 37900);

$bestSellers->products()->sync([$first->id, $premium->id]);
$studioEssentials->products()->sync([$second->id, $premium->id]);

$coupon = Coupon::query()->create([
    'code' => 'TASK11SECRET',
    'name' => '$30 Subscriber Voucher',
    'description' => 'Task 11 isolated coupon identity fixture.',
    'discount_type' => 'fixed_amount',
    'value' => 3000,
    'usage_limit' => 20,
    'usage_count' => 0,
    'per_customer_limit' => 1,
    'is_active' => true,
]);

$page = Page::query()->create([
    'title' => 'Task 11 legacy identity',
    'slug' => 'task-11-legacy',
    'status' => 'published',
    'is_published' => true,
]);

$draft = [
    'title' => 'Task 11 Source Partials',
    'slug' => 'task-11-source-partials',
    'description' => 'Source-faithful ZIP4, ZIP5, and ZIP6 selected regions.',
    'template' => 'landing',
    'heroImage' => null,
    'seoTitle' => 'Task 11 Saved Preview',
    'seoDescription' => 'Authenticated source-fidelity browser fixture.',
    'sections' => [
        [
            'id' => 'task11-collection',
            'type' => 'collection_tabs',
            'title' => 'Explore Aurora Audio Ecosystem',
            'subtitle' => 'BROWSE BY CATEGORY',
            'content' => 'Discover engineered precision for every listening environment.',
            'tabCategories' => ['Electronics & Gadgets', 'Premium Audio', 'Best Sellers 2026', 'Studio Essentials'],
            'tabs' => [
                ['id' => 'tab-1', 'label' => 'Electronics & Gadgets', 'sourceType' => 'category', 'sourceId' => (string) $electronics->id, 'productLimit' => 3],
                ['id' => 'tab-2', 'label' => 'Premium Audio', 'sourceType' => 'category', 'sourceId' => (string) $audio->id, 'productLimit' => 3],
                ['id' => 'tab-3', 'label' => 'Best Sellers 2026', 'sourceType' => 'collection', 'sourceId' => (string) $bestSellers->id, 'productLimit' => 3],
                ['id' => 'tab-4', 'label' => 'Studio Essentials', 'sourceType' => 'collection', 'sourceId' => (string) $studioEssentials->id, 'productLimit' => 3],
            ],
            'textAlignment' => 'center',
            'backgroundColor' => 'bg-neutral-900',
            'textColor' => 'light',
            'paddingY' => 'normal',
            'isVisible' => true,
        ],
        [
            'id' => 'task11-contact',
            'type' => 'contact_form',
            'title' => 'Connect with Our Acoustic Atelier',
            'subtitle' => 'DEDICATED CONCIERGE',
            'content' => 'Have questions about pairing with your DAC setup, or looking for custom engraving? Our engineers respond within 24 hours.',
            'formEmail' => 'concierge@aurora-audio.se',
            'contactPhone' => '+46 8 123 4567 (Mon–Fri 09:00–18:00 CET)',
            'contactAddress' => 'Acoustic Atelier, Stockholm, Sweden',
            'contactButtonText' => 'Send Inquiries to Atelier',
            'formSuccessMessage' => 'Thank you! Your message has been routed to our Stockholm acoustic engineers.',
            'subjectPlaceholder' => 'e.g. Custom Engraving / Setup Inquiry',
            'categoryPlaceholder' => 'Select an inquiry category...',
            'messagePlaceholder' => 'Tell us about your audio setup or custom acoustic requirements...',
            'inquiryCategories' => [
                ['id' => 'cat-1', 'label' => 'Product Enquiry'],
                ['id' => 'cat-2', 'label' => 'Order Support'],
                ['id' => 'cat-3', 'label' => 'Wholesale & Partnership'],
                ['id' => 'cat-4', 'label' => 'Press & Collaboration'],
                ['id' => 'cat-5', 'label' => 'Other'],
            ],
            'textAlignment' => 'left',
            'backgroundColor' => 'bg-neutral-950',
            'textColor' => 'light',
            'paddingY' => 'normal',
            'isVisible' => true,
        ],
        [
            'id' => 'task11-newsletter',
            'type' => 'newsletter_signup',
            'title' => 'Receive $30 Off Your First Order',
            'subtitle' => 'JOIN THE AURORA INSIDER CIRCLE',
            'content' => 'Be the first to access limited-batch atelier releases, studio master recordings, and private events.',
            'buttonText' => 'Claim $30 Voucher',
            'formSuccessMessage' => 'Your subscriber voucher has been sent to your email!',
            'newsletterCouponId' => (string) $coupon->id,
            'textAlignment' => 'center',
            'backgroundColor' => 'bg-neutral-950',
            'textColor' => 'light',
            'paddingY' => 'normal',
            'isVisible' => true,
        ],
    ],
];
$published = $draft;

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
