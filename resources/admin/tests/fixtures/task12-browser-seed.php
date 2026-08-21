<?php

use App\Domain\Cms\PageDocumentContract;
use App\Domain\Cms\PageDocumentHasher;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$repositoryRoot = dirname(__DIR__, 4);
require $repositoryRoot.'/vendor/autoload.php';
$app = require $repositoryRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

User::query()->create([
    'name' => 'Task 12 Admin',
    'email' => 'task12-admin@example.test',
    'password' => Hash::make('Task12-password!'),
    'is_admin' => true,
]);

$validCoupon = Coupon::query()->create([
    'code' => 'TASK12VOUCHER', 'discount_type' => 'percentage', 'value' => 15,
    'usage_limit' => 10, 'usage_count' => 0, 'per_customer_limit' => 1,
    'is_active' => true, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
]);
$expiredCoupon = Coupon::query()->create([
    'code' => 'TASK12EXPIRED', 'discount_type' => 'percentage', 'value' => 15,
    'usage_limit' => 10, 'usage_count' => 0, 'per_customer_limit' => 1,
    'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute(),
]);

$makeDocument = static function (string $slug, string $newsletterId, Coupon $coupon): array {
    return [
        'title' => 'Task 12 Operational Forms', 'slug' => $slug, 'description' => '',
        'template' => 'landing', 'heroImage' => null, 'seoTitle' => '', 'seoDescription' => '',
        'sections' => [
            [
                'id' => 'task12-contact', 'type' => 'contact_form', 'title' => 'Connect with Our Acoustic Atelier',
                'formSuccessMessage' => 'Thank you! Your message has been routed to our Stockholm acoustic engineers.',
                'subjectPlaceholder' => 'e.g. Custom Engraving / Setup Inquiry', 'categoryPlaceholder' => 'Select an inquiry category...',
                'messagePlaceholder' => 'Tell us about your audio setup or custom acoustic requirements...',
                'inquiryCategories' => [['id' => 'product', 'label' => 'Product Enquiry']],
                'textAlignment' => 'left', 'backgroundColor' => 'bg-neutral-950', 'textColor' => 'light', 'paddingY' => 'normal', 'isVisible' => true,
            ],
            [
                'id' => $newsletterId, 'type' => 'newsletter_signup', 'title' => 'Receive 15% Off Your First Order',
                'buttonText' => 'Claim 15% Voucher', 'formSuccessMessage' => 'Your subscriber voucher has been sent to your email!',
                'newsletterCouponId' => (string) $coupon->id,
                'textAlignment' => 'center', 'backgroundColor' => 'bg-neutral-950', 'textColor' => 'light', 'paddingY' => 'normal', 'isVisible' => true,
            ],
        ],
    ];
};

$makePage = static function (string $slug, string $newsletterId, Coupon $coupon) use ($makeDocument): Page {
    $page = Page::query()->create(['title' => 'Task 12 Forms', 'slug' => $slug, 'status' => 'published', 'is_published' => true]);
    $document = $makeDocument($slug, $newsletterId, $coupon);
    app(PageDocumentContract::class)->validate($document);
    foreach (['draft', 'published'] as $stage) {
        $page->documents()->create([
            'stage' => $stage, 'slug' => $slug, 'schema_version' => PageDocumentContract::VERSION,
            'lock_version' => 1, 'content_hash' => PageDocumentHasher::hash($document), 'document' => $document,
        ]);
    }
    return $page;
};

$makePage('task-12-forms', 'task12-newsletter', $validCoupon);
$makePage('task-12-exhausted', 'task12-expired-newsletter', $expiredCoupon);
