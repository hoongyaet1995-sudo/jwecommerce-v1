<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentGatewayCredential;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPdpRichContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdp_uses_persisted_rich_content_and_explicit_related_products(): void
    {
        $product = $this->product('rich-content-product', 'Legacy highlight must not render');
        $relatedFirst = $this->product('related-first', 'First related');
        $relatedSecond = $this->product('related-second', 'Second related');
        $archived = $this->product('archived-related', 'Archived related', 'archived');

        $product->pdpContent()->create([
            'highlights' => [
                ['id' => 'h-1', 'text' => 'Titanium chassis with studio-grade shielding', 'badge' => 'AEROSPACE GRADE'],
            ],
            'specifications' => [
                ['id' => 's-1', 'group' => 'Engineering', 'label' => 'Driver', 'value' => '50 mm beryllium'],
                ['id' => 's-2', 'group' => 'Materials', 'label' => 'Frame', 'value' => 'Precision titanium'],
            ],
            'use_store_default_shipping' => false,
            'shipping_policy' => [
                'shippingBadgeText' => 'Ships in a protective flight case',
                'leadTimeText' => 'Dispatches within one business day.',
                'policyTitle' => 'Studio shipping guarantee',
                'policyContentHtml' => 'Insured delivery and a 30-day return window.',
            ],
            'custom_accordions' => [
                ['id' => 'a-1', 'title' => "What's In The Box", 'content' => 'Headphones, case, and cable.', 'defaultOpen' => true],
            ],
        ]);
        $customer = $this->customer();
        $this->publishedReviewFor($customer, $product, 'Ava Chen', 'Clear and beautifully balanced', 'The fit is excellent.', 5, 'JW-PDP-001');
        $this->pendingReviewFor($customer, $product, 'Pending review must not render', 'Private moderation copy should stay hidden.', 4, 'JW-PDP-002');
        $product->pdpRelatedProducts()->createMany([
            ['related_product_id' => $relatedSecond->id, 'sort_order' => 1],
            ['related_product_id' => $archived->id, 'sort_order' => 2],
            ['related_product_id' => $relatedFirst->id, 'sort_order' => 0],
        ]);

        $html = $this->get('/products/rich-content-product')->assertOk()->getContent();

        $this->assertStringContainsString('Titanium chassis with studio-grade shielding', $html);
        $this->assertStringNotContainsString('Legacy highlight must not render', $html);
        $this->assertStringContainsString('Driver', $html);
        $this->assertStringContainsString('50 mm beryllium', $html);
        $this->assertStringContainsString('Ships in a protective flight case', $html);
        $this->assertStringContainsString('Studio shipping guarantee', $html);
        $this->assertStringContainsString('What&#039;s In The Box', $html);
        $this->assertStringContainsString('Headphones, case, and cable.', $html);
        $this->assertStringContainsString('5.0', $html);
        $this->assertStringContainsString('1 Verified Studio Reviews', $html);
        $this->assertStringContainsString('Ava Chen', $html);
        $this->assertStringContainsString('Verified Studio Buyer', $html);
        $this->assertStringNotContainsString('Pending review must not render', $html);
        $this->assertStringNotContainsString('Archived Related', $html);
        $this->assertLessThan(
            strpos($html, 'Related Second'),
            strpos($html, 'Related First'),
            'Related products must honor the operator-defined order.'
        );
    }

    public function test_pdp_hides_unconfigured_rich_content_regions_instead_of_rendering_mock_fallbacks(): void
    {
        $product = $this->product('empty-rich-content-product', 'Legacy mock highlight');
        $product->pdpContent()->create([
            'highlights' => [],
            'specifications' => [],
            'use_store_default_shipping' => true,
            'shipping_policy' => [],
            'custom_accordions' => [],
        ]);

        $html = $this->get('/products/empty-rich-content-product')->assertOk()->getContent();

        $this->assertStringNotContainsString('Legacy mock highlight', $html);
        $this->assertStringNotContainsString('Signature Craftsmanship Highlights', $html);
        $this->assertStringNotContainsString('Technical Specifications Matrix', $html);
        $this->assertStringNotContainsString('Complimentary Express Shipping Included', $html);
        $this->assertStringNotContainsString('Shipping & 30-Day Return Guarantee', $html);
        $this->assertStringNotContainsString('Verified Studio Reviews', $html);
        $this->assertStringNotContainsString('Frequently Paired With This Model', $html);
    }

    public function test_pdp_uses_real_variant_shop_and_payment_configuration_without_fixed_promises(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'shop_name'], ['value' => ['data' => 'JW Studio']]);
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [
            ['id' => 'paypal', 'name' => 'PayPal', 'isEnabled' => true, 'isSandbox' => true],
            ['id' => 'stripe', 'name' => 'Credit Card', 'isEnabled' => true, 'isSandbox' => true],
        ]]]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'paypal',
            'environment' => 'sandbox',
            'credentials' => ['clientId' => 'paypal-client', 'clientSecret' => 'paypal-secret'],
        ]);
        PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe',
            'environment' => 'sandbox',
            'credentials' => [
                'publishableKey' => 'pk_test_public',
                'secretKey' => 'sk_test_private',
                'webhookSecret' => 'whsec_test_private',
            ],
        ]);

        $product = $this->product('configured-pdp-product', 'This must not become PDP copy');
        $variant = $product->variants()->firstOrFail();
        $variant->update(['material' => 'Matte Titanium', 'stock' => 7]);
        $this->publishedReviewFor($this->customer(), $product, 'Ava Chen', 'Accurate rating', 'Rating must not render as five full stars.', 3, 'JW-PDP-CONFIG-001');

        $html = $this->get('/products/configured-pdp-product')->assertOk()->getContent();

        $this->assertStringContainsString('JW Studio', $html);
        $this->assertStringContainsString('In Stock (7 units)', $html);
        $this->assertStringContainsString('Payment methods available at checkout:', $html);
        $this->assertStringContainsString('Credit Card', $html);
        $this->assertStringContainsString('PayPal', $html);
        $this->assertSame(3, substr_count($html, 'data-pdp-rating-star="filled"'));
        $this->assertSame(2, substr_count($html, 'data-pdp-rating-star="empty"'));
        $this->assertStringNotContainsString('Configuration', $html);
        $this->assertStringNotContainsString('Dispatches Today', $html);
        $this->assertStringNotContainsString('Backorder Available', $html);
        $this->assertStringNotContainsString('interest-free', $html);
        $this->assertStringNotContainsString('Klarna', $html);
        $this->assertStringNotContainsString('Studio standard', $html);
    }

    public function test_pdp_hides_shipping_policy_when_the_operator_has_not_set_a_policy_title(): void
    {
        $product = $this->product('untitled-shipping-policy-product', 'No shipping fallback');
        $product->pdpContent()->create([
            'highlights' => [],
            'specifications' => [],
            'use_store_default_shipping' => false,
            'shipping_policy' => ['policyContentHtml' => 'Actual policy content without a configured title.'],
            'custom_accordions' => [],
        ]);

        $html = $this->get('/products/untitled-shipping-policy-product')->assertOk()->getContent();

        $this->assertStringNotContainsString('Shipping &amp; 30-Day Return Guarantee', $html);
        $this->assertStringNotContainsString('Actual policy content without a configured title.', $html);
    }

    public function test_pdp_does_not_advertise_an_unsupported_atome_gateway_or_klarna_alias(): void
    {
        StoreSetting::query()->updateOrCreate(['key' => 'payment_gateways'], ['value' => ['data' => [
            ['id' => 'atome', 'isEnabled' => true],
        ]]]);

        $html = $this->get('/products/'.$this->product('atome-pdp-product', 'No payment fallback')->handle)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Atome (3x)', $html);
        $this->assertStringNotContainsString('Klarna', $html);
    }

    private function product(string $handle, string $feature, string $status = 'active'): Product
    {
        $product = Product::query()->create([
            'handle' => $handle,
            'title' => ucwords(str_replace('-', ' ', $handle)),
            'base_price' => 29900,
            'status' => $status,
            'features' => [$feature],
        ]);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => "/images/catalog/{$handle}.jpg", 'position' => 0]);
        ProductVariant::query()->create(['product_id' => $product->id, 'sku' => strtoupper($handle), 'title' => 'Standard', 'price' => 29900, 'stock' => 6, 'enabled' => true]);

        return $product;
    }

    private function customer(): Customer
    {
        $user = User::factory()->create();

        return Customer::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'first_name' => 'Ava',
            'last_name' => 'Chen',
        ]);
    }

    private function publishedReviewFor(
        Customer $customer,
        Product $product,
        string $customerName,
        string $title,
        string $comment,
        int $rating,
        string $orderNumber
    ): ProductReview {
        $item = $this->deliveredItemFor($customer, $product, $orderNumber);

        return ProductReview::query()->create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'published',
            'active_lock' => 'active',
            'rating' => $rating,
            'title' => $title,
            'comment' => $comment,
            'order_number_snapshot' => $orderNumber,
            'product_title_snapshot' => $product->title,
            'variant_title_snapshot' => 'Standard',
            'customer_name_snapshot' => $customerName,
            'submitted_at' => now()->subHour(),
            'published_at' => now(),
        ]);
    }

    private function pendingReviewFor(
        Customer $customer,
        Product $product,
        string $title,
        string $comment,
        int $rating,
        string $orderNumber
    ): ProductReview {
        $item = $this->deliveredItemFor($customer, $product, $orderNumber);

        return ProductReview::query()->create([
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'attempt_number' => 1,
            'status' => 'pending',
            'active_lock' => 'active',
            'rating' => $rating,
            'title' => $title,
            'comment' => $comment,
            'order_number_snapshot' => $orderNumber,
            'product_title_snapshot' => $product->title,
            'variant_title_snapshot' => 'Standard',
            'customer_name_snapshot' => trim($customer->first_name.' '.$customer->last_name),
            'submitted_at' => now(),
        ]);
    }

    private function deliveredItemFor(Customer $customer, Product $product, string $orderNumber)
    {
        $variant = $product->variants()->firstOrFail();
        $order = Order::query()->create([
            'number' => $orderNumber,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'delivered',
            'subtotal' => $variant->price,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => $variant->price,
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
}
