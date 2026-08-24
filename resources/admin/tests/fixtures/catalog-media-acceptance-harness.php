<?php

use App\Models\CatalogMediaReference;
use App\Models\Category;
use App\Models\CmsMediaAsset;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$required = static function (string $key): string {
    $value = getenv($key);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("Missing required catalog acceptance environment: {$key}");
    }

    return $value;
};

$action = $argv[1] ?? '';
$token = $required('CATALOG_ACCEPTANCE_TOKEN');
$email = $required('CATALOG_ACCEPTANCE_ADMIN_EMAIL');
$password = $required('CATALOG_ACCEPTANCE_ADMIN_PASSWORD');

if (preg_match('/\A[a-z0-9]{12,40}\z/', $token) !== 1) {
    throw new RuntimeException('Catalog acceptance token format is invalid.');
}

$categorySlug = "catalog-acceptance-{$token}-category";
$collectionSlug = "catalog-acceptance-{$token}-collection";
$handle = "catalog-acceptance-{$token}-product";

$owned = static function () use ($categorySlug, $collectionSlug, $email, $handle): array {
    $category = Category::query()->where('slug', $categorySlug)->first();
    $collection = Collection::query()->where('slug', $collectionSlug)->first();
    $product = Product::query()->where('handle', $handle)->first();
    $user = User::query()->where('email', $email)->first();

    $paths = array_values(array_unique(array_filter([
        $category?->image_url,
        $collection?->cover_image,
        ...($product?->images()->pluck('path')->all() ?? []),
    ], static fn ($value): bool => is_string($value) && str_starts_with($value, '/storage/cms/media/'))));

    $assetIds = CmsMediaAsset::query()
        ->whereIn('path', $paths)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    return [
        'ids' => [
            'admin' => $user?->getKey(),
            'category' => $category?->getKey(),
            'collection' => $collection?->getKey(),
            'product' => $product?->getKey(),
            'assets' => $assetIds,
        ],
        'paths' => $paths,
    ];
};

if ($action === 'setup') {
    if (User::query()->where('email', $email)->exists()
        || Category::query()->where('slug', $categorySlug)->exists()
        || Collection::query()->where('slug', $collectionSlug)->exists()
        || Product::query()->where('handle', $handle)->exists()) {
        throw new RuntimeException('Catalog acceptance token already owns rows; refusing to reuse it.');
    }

    DB::transaction(function () use ($categorySlug, $collectionSlug, $email, $handle, $password): void {
        $user = User::query()->create([
            'name' => 'Catalog Acceptance Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Catalog Acceptance Category',
            'slug' => $categorySlug,
            'icon' => 'Headphones',
            'image_url' => '/images/catalog/aether-1.jpg',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'title' => 'Catalog Acceptance Collection',
            'slug' => $collectionSlug,
            'cover_image' => '/images/catalog/collection-monolith.jpg',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->getKey(),
            'handle' => $handle,
            'title' => 'Catalog Acceptance Product',
            'subtitle' => 'Real browser media acceptance fixture',
            'brand' => 'JW Acceptance',
            'base_price' => 14900,
            'compare_at_price' => 16900,
            'status' => 'active',
            'is_featured' => false,
            'has_variants' => true,
            'features' => [],
            'options' => [],
            'tags' => ['acceptance'],
        ]);
        $product->images()->create([
            'path' => '/images/catalog/aether-1.jpg',
            'alt' => 'Catalog acceptance baseline image',
            'position' => 0,
        ]);
        $product->variants()->create([
            'sku' => 'CATALOG-ACCEPTANCE-BASE',
            'title' => 'Acceptance Base SKU',
            'options' => [],
            'price' => 14900,
            'compare_at_price' => 16900,
            'stock' => 12,
            'enabled' => true,
        ]);
        $collection->products()->attach($product->getKey());
        unset($user);
    });

    echo json_encode(['action' => 'setup', 'owned' => $owned()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit(0);
}

if ($action === 'inspect') {
    echo json_encode(['action' => 'inspect', 'owned' => $owned()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit(0);
}

if ($action === 'cleanup') {
    $state = $owned();
    DB::transaction(function () use ($categorySlug, $collectionSlug, $email, $handle, $state): void {
        $product = Product::query()->where('handle', $handle)->first();
        if ($product !== null) {
            DB::table('product_images')->where('product_id', $product->getKey())->delete();
            DB::table('product_variants')->where('product_id', $product->getKey())->delete();
            $product->collections()->detach();
            $product->delete();
        }
        Collection::query()->where('slug', $collectionSlug)->delete();
        Category::query()->where('slug', $categorySlug)->delete();
        if ($state['ids']['assets'] !== []) {
            CatalogMediaReference::query()->whereIn('media_asset_id', $state['ids']['assets'])->delete();
            CmsMediaAsset::query()->whereIn('id', $state['ids']['assets'])->delete();
        }
        $user = User::query()->where('email', $email)->first();
        if ($user !== null && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }
        $user?->delete();
    });
    foreach ($state['paths'] as $path) {
        Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $path), '/'));
    }
    $after = $owned();
    $remaining = array_filter([
        $after['ids']['admin'], $after['ids']['category'], $after['ids']['collection'], $after['ids']['product'], ...$after['ids']['assets'],
    ], static fn ($value): bool => $value !== null);
    echo json_encode(['action' => 'cleanup', 'ownedRemaining' => count($remaining), 'state' => $after], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit($remaining === [] ? 0 : 1);
}

throw new RuntimeException('Expected setup, inspect, or cleanup action.');
