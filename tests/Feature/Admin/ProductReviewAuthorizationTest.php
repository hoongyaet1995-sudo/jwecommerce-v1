<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_viewer_cannot_publish_but_moderator_can(): void
    {
        $viewer = $this->staffWith(['reviews.view']);
        $moderator = $this->staffWith(['reviews.view', 'reviews.moderate']);
        $review = $this->pendingReview();

        $this->assertFalse($viewer->can('moderate', $review));
        $this->assertTrue($moderator->can('moderate', $review));
    }

    public function test_super_admin_can_grant_review_permissions_but_a_moderator_cannot(): void
    {
        $moderator = $this->staffWith(['reviews.view', 'reviews.moderate']);
        $superAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $staff = User::factory()->create(['is_admin' => true]);

        $this->actingAs($moderator)
            ->putJson(route('admin.api.staff.review-permissions.update', $staff), ['permissions' => ['reviews.view']])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->putJson(route('admin.api.staff.review-permissions.update', $staff), ['permissions' => ['reviews.view', 'reviews.moderate']])
            ->assertOk()
            ->assertJsonFragment(['reviews.moderate']);

        $this->assertDatabaseHas('user_permissions', ['user_id' => $staff->id, 'permission' => 'reviews.moderate']);
    }

    public function test_moderator_can_publish_a_pending_review_and_an_audit_event_is_appended(): void
    {
        $moderator = $this->staffWith(['reviews.view', 'reviews.moderate']);
        $review = $this->pendingReview();

        $this->actingAs($moderator)
            ->postJson(route('admin.api.reviews.moderate', $review), ['action' => 'publish'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseHas('product_review_events', ['product_review_id' => $review->id, 'event_type' => 'published']);
    }

    public function test_bulk_moderator_receives_per_review_results_and_shared_batch_audit_ids(): void
    {
        $moderator = $this->staffWith(['reviews.view', 'reviews.moderate', 'reviews.bulk_moderate']);
        $first = $this->pendingReview();
        $second = $this->pendingReview('second');

        $this->actingAs($moderator)
            ->postJson(route('admin.api.reviews.bulk-moderate'), ['reviewIds' => [$first->id, $second->id], 'action' => 'publish'])
            ->assertOk()
            ->assertJsonCount(2, 'data.results');

        $batchIds = \App\Models\ProductReviewEvent::query()->whereIn('product_review_id', [$first->id, $second->id])->where('event_type', 'published')->pluck('batch_id')->unique();
        $this->assertCount(1, $batchIds);
        $this->assertNotNull($batchIds->first());
    }

    public function test_review_viewer_can_read_a_real_moderation_row_and_its_audit_history(): void
    {
        $viewer = $this->staffWith(['reviews.view', 'reviews.audit.view']);
        $unauthorised = User::factory()->create(['is_admin' => true]);
        $review = $this->pendingReview();
        $review->product->images()->create([
            'path' => '/images/catalog/review-auth-product-first-cover.jpg',
            'alt' => 'Review auth product cover',
            'position' => 0,
        ]);
        $review->events()->create(['event_type' => 'submitted', 'to_status' => 'pending', 'metadata' => ['attemptNumber' => 1], 'occurred_at' => now()]);

        $this->actingAs($viewer)
            ->getJson(route('admin.api.reviews.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $review->id)
            ->assertJsonPath('data.0.orderNumber', $review->order_number_snapshot)
            ->assertJsonPath('data.0.productThumbnail', '/images/catalog/review-auth-product-first-cover.jpg')
            ->assertJsonPath('data.0.productHandle', 'review-auth-product-first')
            ->assertJsonPath('data.0.customerId', $review->customer_id)
            ->assertJsonPath('data.0.customerEmail', $review->customer->email)
            ->assertJsonPath('data.0.moderatedBy', null);

        $this->actingAs($viewer)
            ->getJson(route('admin.api.reviews.audit', $review))
            ->assertOk()
            ->assertJsonPath('data.0.eventType', 'submitted');

        $this->actingAs($unauthorised)
            ->getJson(route('admin.api.reviews.index'))
            ->assertForbidden();
    }

    public function test_review_row_exposes_an_authenticated_private_media_url_to_authorized_staff(): void
    {
        $viewer = $this->staffWith(['reviews.view']);
        $review = $this->pendingReview();
        $media = $review->media()->create([
            'original_path' => 'reviews/test/originals/proof.jpg',
            'original_filename' => 'proof.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 42,
            'width' => 1,
            'height' => 1,
            'checksum_sha256' => str_repeat('a', 64),
            'position' => 1,
        ]);

        $this->actingAs($viewer)
            ->getJson(route('admin.api.reviews.index'))
            ->assertOk()
            ->assertJsonPath('data.0.photos.0.url', route('admin.api.reviews.media.show', $media));
    }

    public function test_admin_app_bootstrap_exposes_protected_review_urls_and_permissions(): void
    {
        $moderator = $this->staffWith(['reviews.view', 'reviews.bulk_moderate']);

        $response = $this->actingAs($moderator)->get(route('admin.dashboard'))->assertOk();
        $bootstrap = $this->extractAdminBootstrap($response->getContent());

        $this->assertSame(route('admin.api.reviews.index'), $bootstrap['reviewsUrl'] ?? null);
        $this->assertSame(route('admin.api.reviews.bulk-moderate'), $bootstrap['reviewBulkModerateUrl'] ?? null);
        $this->assertSame(['reviews.view', 'reviews.bulk_moderate'], $bootstrap['user']['reviewPermissions'] ?? null);
    }

    private function staffWith(array $permissions): User
    {
        $staff = User::factory()->create(['is_admin' => true]);

        foreach ($permissions as $permission) {
            $staff->permissions()->create(['permission' => $permission]);
        }

        return $staff;
    }

    private function pendingReview(string $suffix = 'first'): ProductReview
    {
        $customerUser = User::factory()->create();
        $customer = Customer::query()->create(['user_id' => $customerUser->id, 'email' => $customerUser->email]);
        $category = Category::query()->create(['name' => 'Review category '.$suffix, 'slug' => 'review-auth-category-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Review product '.$suffix, 'handle' => 'review-auth-product-'.$suffix, 'base_price' => 12000, 'status' => 'active']);
        $variant = $product->variants()->create(['sku' => 'REVIEW-AUTH-'.$suffix, 'title' => 'Default', 'price' => 12000, 'stock' => 4]);
        $order = Order::query()->create(['number' => 'JW-REVIEW-AUTH-'.$suffix, 'customer_id' => $customer->id, 'email' => $customer->email, 'currency' => 'MYR', 'status' => 'delivered', 'subtotal' => 12000, 'discount_amount' => 0, 'shipping_amount' => 0, 'tax_amount' => 0, 'total' => 12000]);
        $item = $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'title' => $product->title, 'variant_title' => $variant->title, 'unit_price' => $variant->price, 'quantity' => 1, 'line_total' => $variant->price]);

        return ProductReview::query()->create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'active_lock' => 'active',
            'rating' => 5,
            'title' => 'Excellent',
            'comment' => 'A pending customer review.',
            'order_number_snapshot' => $order->number,
            'product_title_snapshot' => $product->title,
            'variant_title_snapshot' => $variant->title,
            'customer_name_snapshot' => $customerUser->name,
            'submitted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAdminBootstrap(string $html): array
    {
        $matched = preg_match('/window\.__JW_ADMIN__\s*=\s*(\{.*\});/sU', $html, $matches);

        $this->assertSame(1, $matched, 'Admin bootstrap payload was not found.');

        $bootstrap = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($bootstrap);

        return $bootstrap;
    }
}
