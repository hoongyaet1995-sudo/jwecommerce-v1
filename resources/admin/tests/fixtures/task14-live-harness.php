<?php

use App\Domain\Cms\PageCrudService;
use App\Models\Category;
use App\Models\CmsContactSubmission;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\NewsletterSubscription;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$requiredEnvironment = static function (string $name): string {
    $value = getenv($name);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("Missing required acceptance environment: {$name}");
    }

    return $value;
};

$action = $argv[1] ?? '';
$token = $requiredEnvironment('CMS_ACCEPTANCE_TOKEN');
$adminEmail = $requiredEnvironment('CMS_ACCEPTANCE_ADMIN_EMAIL');
$adminPassword = $requiredEnvironment('CMS_ACCEPTANCE_ADMIN_PASSWORD');
$couponCode = $requiredEnvironment('CMS_ACCEPTANCE_COUPON_CODE');
$pageSlug = $requiredEnvironment('CMS_ACCEPTANCE_PAGE_SLUG');
$contactEmail = $requiredEnvironment('CMS_ACCEPTANCE_CONTACT_EMAIL');
$newsletterEmail = $requiredEnvironment('CMS_ACCEPTANCE_NEWSLETTER_EMAIL');
$categorySlug = "task14-{$token}-category";
$collectionSlug = "task14-{$token}-collection";
$productHandle = "task14-{$token}-product";

if (preg_match('/\A[a-z0-9]{12,40}\z/', $token) !== 1) {
    throw new RuntimeException('Acceptance token format is invalid.');
}

$tableCounts = static function (): array {
    $tables = [
        'users',
        'pages',
        'page_documents',
        'page_sections',
        'categories',
        'collections',
        'products',
        'product_images',
        'collection_product',
        'coupons',
        'cms_contact_submissions',
        'newsletter_subscriptions',
    ];
    if (Schema::hasTable('sessions')) {
        $tables[] = 'sessions';
    }

    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
};

$rowDigestColumns = [
    'users' => ['id', 'updated_at'],
    'pages' => ['id', 'status', 'updated_at'],
    'page_documents' => ['id', 'page_id', 'stage', 'slug', 'content_hash', 'lock_version'],
    'page_sections' => ['id', 'page_id', 'type', 'position', 'updated_at'],
    'categories' => ['id', 'slug', 'updated_at'],
    'collections' => ['id', 'slug', 'updated_at'],
    'products' => ['id', 'category_id', 'handle', 'updated_at'],
    'product_images' => ['id', 'product_id', 'path', 'position'],
    'collection_product' => ['collection_id', 'product_id'],
    'coupons' => ['id', 'usage_count', 'updated_at'],
    'cms_contact_submissions' => ['id', 'page_id', 'page_id_snapshot', 'document_stage', 'section_id', 'created_at'],
    'newsletter_subscriptions' => ['id', 'page_id', 'page_id_snapshot', 'coupon_id', 'coupon_id_snapshot', 'document_stage', 'section_id', 'created_at'],
    'sessions' => ['id', 'user_id', 'last_activity'],
];

$keyedRowDigests = static function () use ($rowDigestColumns): array {
    $digests = [];
    foreach ($rowDigestColumns as $table => $columns) {
        if (! Schema::hasTable($table)) {
            continue;
        }
        $query = DB::table($table);
        foreach ($columns as $column) {
            $query->orderBy($column);
        }
        $rows = $query->get($columns)
            ->map(static fn (object $row): array => (array) $row)
            ->all();
        $digests[$table] = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    return $digests;
};

$countEvidence = static function () use ($keyedRowDigests, $tableCounts): array {
    $counts = $tableCounts();
    $rowDigests = $keyedRowDigests();

    return [
        'counts' => $counts,
        'rowDigests' => $rowDigests,
        'hash' => hash('sha256', json_encode(['counts' => $counts, 'rowDigests' => $rowDigests], JSON_THROW_ON_ERROR)),
    ];
};

$findPage = static function () use ($pageSlug): ?Page {
    return Page::query()
        ->whereHas('documents', fn ($query) => $query->where('slug', $pageSlug))
        ->with(['draftDocument', 'publishedDocument'])
        ->first();
};

$ownedState = static function () use (
    $adminEmail,
    $categorySlug,
    $collectionSlug,
    $contactEmail,
    $couponCode,
    $findPage,
    $newsletterEmail,
    $productHandle,
    $token,
): array {
    $page = $findPage();
    $admin = User::query()->where('email', $adminEmail)->first();
    $product = Product::query()->where('handle', $productHandle)->first();
    $contacts = CmsContactSubmission::query()->where('email', $contactEmail)->orderBy('id')->get();
    $newsletters = NewsletterSubscription::query()->where('email', $newsletterEmail)->orderBy('id')->get();
    $draft = $page?->draftDocument;
    $published = $page?->publishedDocument;
    $draftDocument = $draft?->document;
    $sections = is_array($draftDocument) && is_array($draftDocument['sections'] ?? null)
        ? $draftDocument['sections']
        : [];
    $sectionById = [];
    foreach ($sections as $section) {
        if (is_array($section) && is_string($section['id'] ?? null)) {
            $sectionById[$section['id']] = $section;
        }
    }
    $hero = $sectionById["task14-{$token}-hero"] ?? null;
    $tabs = $sectionById["task14-{$token}-collection"]['tabs'] ?? [];
    $testimonials = $sectionById["task14-{$token}-testimonials"]['items'] ?? [];

    $sessionCount = 0;
    if ($admin !== null && Schema::hasTable('sessions')) {
        $sessionCount = DB::table('sessions')->where('user_id', $admin->getKey())->count();
    }

    return [
        'ids' => [
            'admin' => $admin?->getKey(),
            'page' => $page?->getKey(),
            'documents' => $page?->documents()->orderBy('stage')->pluck('id')->all() ?? [],
            'legacySections' => $page?->sections()->pluck('id')->all() ?? [],
            'category' => Category::query()->where('slug', $categorySlug)->value('id'),
            'collection' => Collection::query()->where('slug', $collectionSlug)->value('id'),
            'product' => Product::query()->where('handle', $productHandle)->value('id'),
            'productImages' => $product === null ? [] : DB::table('product_images')->where('product_id', $product->getKey())->orderBy('id')->pluck('id')->all(),
            'collectionProducts' => $product === null ? [] : DB::table('collection_product')->where('product_id', $product->getKey())->orderBy('collection_id')->get(['collection_id', 'product_id'])->map(static fn (object $row): string => $row->collection_id.':'.$row->product_id)->all(),
            'coupon' => Coupon::query()->where('code', $couponCode)->value('id'),
            'contacts' => $contacts->pluck('id')->all(),
            'newsletters' => $newsletters->pluck('id')->all(),
        ],
        'page' => $page === null ? null : [
            'status' => $page->status,
            'isPublishedLegacyFlag' => (bool) $page->is_published,
            'draftHash' => $draft?->content_hash,
            'draftVersion' => $draft?->lock_version,
            'publishedHash' => $published?->content_hash,
            'publishedVersion' => $published?->lock_version,
            'sectionIds' => array_keys($sectionById),
            'hero' => is_array($hero) ? [
                'title' => $hero['title'] ?? null,
                'subtitle' => $hero['subtitle'] ?? null,
                'content' => $hero['content'] ?? null,
                'mediaUrl' => $hero['mediaUrl'] ?? null,
                'buttonText' => $hero['buttonText'] ?? null,
                'buttonLink' => $hero['buttonLink'] ?? null,
                'secondaryButtonText' => $hero['secondaryButtonText'] ?? null,
                'secondaryButtonLink' => $hero['secondaryButtonLink'] ?? null,
                'textAlignment' => $hero['textAlignment'] ?? null,
                'paddingY' => $hero['paddingY'] ?? null,
                'backgroundColor' => $hero['backgroundColor'] ?? null,
                'customColors' => $hero['customColors'] ?? null,
            ] : null,
            'tabs' => $tabs,
            'testimonialItemCount' => is_array($testimonials) ? count($testimonials) : 0,
            'testimonialFirstTitle' => is_array($testimonials) ? ($testimonials[0]['title'] ?? null) : null,
        ],
        'history' => [
            'contactCount' => $contacts->count(),
            'contactPageIds' => $contacts->pluck('page_id')->all(),
            'contactPageSnapshots' => $contacts->pluck('page_id_snapshot')->all(),
            'contactStages' => $contacts->pluck('document_stage')->all(),
            'newsletterCount' => $newsletters->count(),
            'newsletterPageIds' => $newsletters->pluck('page_id')->all(),
            'newsletterPageSnapshots' => $newsletters->pluck('page_id_snapshot')->all(),
            'newsletterCouponIds' => $newsletters->pluck('coupon_id')->all(),
            'newsletterStages' => $newsletters->pluck('document_stage')->all(),
        ],
        'sessionCount' => $sessionCount,
    ];
};

if ($action === 'setup') {
    $before = $countEvidence();
    $existingOwnedRows = [
        User::query()->where('email', $adminEmail)->exists(),
        Category::query()->where('slug', $categorySlug)->exists(),
        Collection::query()->where('slug', $collectionSlug)->exists(),
        Product::query()->where('handle', $productHandle)->exists(),
        Coupon::query()->where('code', $couponCode)->exists(),
        CmsContactSubmission::query()->where('email', $contactEmail)->exists(),
        NewsletterSubscription::query()->where('email', $newsletterEmail)->exists(),
        $findPage() !== null,
    ];
    if (in_array(true, $existingOwnedRows, true)) {
        throw new RuntimeException('Acceptance token already owns rows; refusing to reuse it.');
    }

    DB::transaction(function () use (
        $adminEmail,
        $adminPassword,
        $categorySlug,
        $collectionSlug,
        $couponCode,
        $productHandle,
    ): void {
        User::query()->create([
            'name' => 'Task 14 Acceptance Admin',
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
            'is_admin' => true,
        ]);
        $category = Category::query()->create([
            'slug' => $categorySlug,
            'name' => 'Task 14 Acceptance Category',
            'is_active' => true,
        ]);
        $collection = Collection::query()->create([
            'slug' => $collectionSlug,
            'title' => 'Task 14 Acceptance Collection',
            'type' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->getKey(),
            'handle' => $productHandle,
            'title' => 'Task 14 Acceptance Product',
            'subtitle' => 'Real MySQL runtime product',
            'brand' => 'JW Acceptance',
            'base_price' => 14900,
            'compare_at_price' => 17900,
            'status' => 'active',
            'tags' => ['Acceptance'],
        ]);
        $product->images()->create([
            'path' => '/images/catalog/solis-1.jpg',
            'alt' => 'Task 14 acceptance product',
            'position' => 0,
        ]);
        $collection->products()->attach($product->getKey());
        Coupon::query()->create([
            'code' => $couponCode,
            'name' => 'Task 14 Acceptance Coupon',
            'discount_type' => 'percentage',
            'value' => 14,
            'usage_limit' => 10,
            'usage_count' => 0,
            'per_customer_limit' => 1,
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ]);
    });

    echo json_encode([
        'action' => 'setup',
        'before' => $before,
        'after' => $countEvidence(),
        'owned' => $ownedState(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit(0);
}

if ($action === 'inspect') {
    echo json_encode([
        'action' => 'inspect',
        'counts' => $countEvidence(),
        'owned' => $ownedState(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit(0);
}

if ($action === 'cleanup') {
    $before = $countEvidence();
    $snapshot = $ownedState();
    $expectedBaselineHash = getenv('CMS_ACCEPTANCE_BASELINE_DIGEST');

    DB::transaction(function () use (
        $adminEmail,
        $categorySlug,
        $collectionSlug,
        $contactEmail,
        $couponCode,
        $findPage,
        $newsletterEmail,
        $productHandle,
    ): void {
        $page = $findPage();
        if ($page !== null) {
            app(PageCrudService::class)->delete($page);
        }

        foreach (CmsContactSubmission::query()->where('email', $contactEmail)->get() as $submission) {
            $submission->delete();
        }
        foreach (NewsletterSubscription::query()->where('email', $newsletterEmail)->get() as $subscription) {
            $subscription->delete();
        }

        $admin = User::query()->where('email', $adminEmail)->first();
        if ($admin !== null && Schema::hasTable('sessions')) {
            foreach (DB::table('sessions')->where('user_id', $admin->getKey())->pluck('id') as $sessionId) {
                DB::table('sessions')->where('id', $sessionId)->delete();
            }
        }
        $admin?->delete();

        $product = Product::query()->where('handle', $productHandle)->first();
        if ($product !== null) {
            $product->collections()->detach();
            $product->delete();
        }
        Collection::query()->where('slug', $collectionSlug)->first()?->delete();
        Category::query()->where('slug', $categorySlug)->first()?->delete();
        Coupon::query()->where('code', $couponCode)->first()?->delete();
    });

    $afterState = $ownedState();
    $ownedRemaining = [
        'admin' => $afterState['ids']['admin'] === null ? 0 : 1,
        'page' => $afterState['ids']['page'] === null ? 0 : 1,
        'documents' => count($afterState['ids']['documents']),
        'legacySections' => count($afterState['ids']['legacySections']),
        'category' => $afterState['ids']['category'] === null ? 0 : 1,
        'collection' => $afterState['ids']['collection'] === null ? 0 : 1,
        'product' => $afterState['ids']['product'] === null ? 0 : 1,
        'productImages' => count($afterState['ids']['productImages']),
        'collectionProducts' => count($afterState['ids']['collectionProducts']),
        'coupon' => $afterState['ids']['coupon'] === null ? 0 : 1,
        'contacts' => count($afterState['ids']['contacts']),
        'newsletters' => count($afterState['ids']['newsletters']),
        'sessions' => $afterState['sessionCount'],
    ];

    $after = $countEvidence();
    $baselineMatches = is_string($expectedBaselineHash)
        && $expectedBaselineHash !== ''
        && hash_equals($expectedBaselineHash, $after['hash']);

    echo json_encode([
        'action' => 'cleanup',
        'before' => $before,
        'snapshot' => $snapshot,
        'after' => $after,
        'expectedBaselineHash' => is_string($expectedBaselineHash) && $expectedBaselineHash !== '' ? $expectedBaselineHash : null,
        'baselineMatches' => $baselineMatches,
        'ownedRemaining' => $ownedRemaining,
        'ownedRemainingHash' => hash('sha256', json_encode($ownedRemaining, JSON_THROW_ON_ERROR)),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit(0);
}

throw new RuntimeException('Expected setup, inspect, or cleanup action.');
