<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use App\Domain\Reviews\ProductReviewMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductReviewOperationalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_attempts_preserve_rejected_history_but_only_one_active_attempt(): void
    {
        $customer = $this->customer();
        $item = $this->deliveredItemFor($customer);
        $productId = DB::table('product_variants')->where('id', $item->product_variant_id)->value('product_id');

        DB::table('product_reviews')->insert([
            'order_item_id' => $item->id,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'rejected',
            'rating' => 3,
            'title' => 'First attempt',
            'comment' => 'Rejected for a valid moderation reason.',
            'order_number_snapshot' => 'JW-REVIEW-001',
            'product_title_snapshot' => 'Review product',
            'customer_name_snapshot' => 'Review Buyer',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_reviews')->insert([
            'order_item_id' => $item->id,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            'attempt_number' => 2,
            'status' => 'pending',
            'active_lock' => 'active',
            'rating' => 5,
            'title' => 'Second attempt',
            'comment' => 'A replacement attempt awaits approval.',
            'order_number_snapshot' => 'JW-REVIEW-001',
            'product_title_snapshot' => 'Review product',
            'customer_name_snapshot' => 'Review Buyer',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('product_reviews', 2);
        $this->assertDatabaseHas('product_reviews', [
            'order_item_id' => $item->id,
            'attempt_number' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_customer_media_is_private_and_uses_a_review_scoped_path(): void
    {
        Storage::fake('review-private');
        $customer = $this->customer();
        $review = $this->pendingReviewFor($customer);

        $media = app(ProductReviewMediaService::class)->storePending(
            $review,
            $this->sourceJpeg('review-proof.jpg'),
            0,
        );

        Storage::disk('review-private')->assertExists($media->original_path);
        Storage::disk('public')->assertMissing($media->original_path);
        $this->assertSame('image/jpeg', $media->mime_type);
    }

    public function test_customer_review_media_denies_a_non_owner(): void
    {
        Storage::fake('review-private');
        $owner = $this->customer();
        $review = $this->pendingReviewFor($owner);
        $media = app(ProductReviewMediaService::class)->storePending($review, $this->sourceJpeg('owner-proof.jpg'), 0);
        $other = $this->customer();

        $this->actingAs($other->user, 'customer')
            ->get(route('customer.reviews.media.show', $media))
            ->assertForbidden();

        $this->actingAs($owner->user, 'customer')
            ->get(route('customer.reviews.media.show', $media))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_delivered_owner_can_submit_once_and_event_is_appended(): void
    {
        $customer = $this->customer();
        $item = $this->deliveredItemFor($customer);

        $response = $this->actingAs($customer->user, 'customer')->postJson(
            route('customer.reviews.store'),
            ['orderItemId' => $item->id, 'rating' => 5, 'title' => 'Excellent build', 'comment' => 'Delivered exactly as ordered.'],
        );

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('product_review_events', ['event_type' => 'submitted']);
    }

    public function test_rejected_review_can_be_resubmitted_but_an_active_attempt_cannot_be_duplicated(): void
    {
        $customer = $this->customer();
        $item = $this->deliveredItemFor($customer);
        $payload = ['orderItemId' => $item->id, 'rating' => 4, 'title' => 'Solid build', 'comment' => 'A review attempt for resubmission coverage.'];

        $this->actingAs($customer->user, 'customer')->postJson(route('customer.reviews.store'), $payload)->assertCreated();
        $this->actingAs($customer->user, 'customer')->postJson(route('customer.reviews.store'), $payload)->assertConflict();

        ProductReview::query()->where('order_item_id', $item->id)->update(['status' => 'rejected', 'active_lock' => null]);

        $this->actingAs($customer->user, 'customer')->postJson(route('customer.reviews.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.attemptNumber', 2);
    }

    public function test_pdp_exposes_only_published_order_linked_reviews_in_its_rating_summary(): void
    {
        $customer = $this->customer();
        $pending = $this->pendingReviewFor($customer);
        $published = $this->publishedReviewFor($customer, $pending->product);
        $pending->product->pdpContent()->create(['rating_score' => 4.9, 'review_count' => 142]);
        $pending->product->pdpReviews()->create([
            'author' => 'Legacy Buyer',
            'rating' => 4,
            'review_date' => '3 days ago',
            'title' => 'Legacy seeded review',
            'comment' => 'This legacy review must stay dormant.',
            'verified_buyer' => true,
            'sort_order' => 0,
        ]);

        $html = $this->get('/products/review-product')->assertOk()->getContent();

        $this->assertStringContainsString('5.0', $html);
        $this->assertStringContainsString('1 Verified Studio Reviews', $html);
        $this->assertStringContainsString($published->title, $html);
        $this->assertStringNotContainsString($pending->title, $html);
        $this->assertStringNotContainsString('142 Verified Studio Reviews', $html);
        $this->assertStringNotContainsString('Legacy seeded review', $html);
    }

    public function test_customer_review_preview_uses_the_source_photos_field_without_replacing_account(): void
    {
        $customer = $this->customer();
        $this->deliveredItemFor($customer);

        $this->actingAs($customer->user, 'customer')
            ->get(route('customer.reviews.preview'))
            ->assertOk()
            ->assertSee('Photos')
            ->assertSee('Up to 5 images, 5 MB each.')
            ->assertSee('Review this product');
    }

    private function customer(): Customer
    {
        $user = User::factory()->create();

        return Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Review',
            'last_name' => 'Buyer',
        ]);
    }

    private function deliveredItemFor(Customer $customer, ?Product $product = null, ?string $orderNumber = null)
    {
        if ($product === null) {
            $category = Category::query()->create([
                'name' => 'Review category',
                'slug' => 'review-category',
                'status' => 'active',
            ]);
            $product = Product::query()->create([
                'category_id' => $category->id,
                'title' => 'Review product',
                'handle' => 'review-product',
                'base_price' => 12000,
                'status' => 'active',
            ]);
            $variant = $product->variants()->create([
                'sku' => 'REVIEW-ITEM-1',
                'title' => 'Default',
                'price' => 12000,
                'stock' => 4,
            ]);
        } else {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->firstOrFail();
        }

        $order = Order::query()->create([
            'number' => $orderNumber ?? 'JW-REVIEW-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'delivered',
            'subtotal' => 12000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 12000,
        ]);

        return $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'title' => $product->title,
            'variant_title' => $variant->title,
            'unit_price' => $variant->price,
            'quantity' => 1,
            'line_total' => $variant->price,
        ]);
    }

    private function pendingReviewFor(Customer $customer): ProductReview
    {
        $item = $this->deliveredItemFor($customer);
        $productId = DB::table('product_variants')->where('id', $item->product_variant_id)->value('product_id');

        return ProductReview::query()->create([
            'order_item_id' => $item->id,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'active_lock' => 'active',
            'rating' => 5,
            'title' => 'Image review',
            'comment' => 'A pending review with private buyer proof.',
            'order_number_snapshot' => 'JW-REVIEW-001',
            'product_title_snapshot' => 'Review product',
            'variant_title_snapshot' => 'Default',
            'customer_name_snapshot' => 'Review Buyer',
            'submitted_at' => now(),
        ]);
    }

    private function publishedReviewFor(Customer $customer, Product $product): ProductReview
    {
        $item = $this->deliveredItemFor($customer, $product, 'JW-REVIEW-002');

        return ProductReview::query()->create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'published',
            'active_lock' => 'active',
            'rating' => 5,
            'title' => 'Published review',
            'comment' => 'A verified published review for the PDP.',
            'order_number_snapshot' => 'JW-REVIEW-002',
            'product_title_snapshot' => 'Review product',
            'variant_title_snapshot' => 'Default',
            'customer_name_snapshot' => 'Published Buyer',
            'submitted_at' => now()->subHour(),
            'published_at' => now(),
        ]);
    }

    private function sourceJpeg(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('public/images/source/jwstudio_logo_1786766390670.jpg'),
            $name,
            'image/jpeg',
            null,
            true,
        );
    }
}
