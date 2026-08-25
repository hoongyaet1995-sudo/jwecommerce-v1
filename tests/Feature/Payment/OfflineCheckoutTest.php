<?php

namespace Tests\Feature\Payment;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfflineCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_transfer_creates_a_pending_order_and_snapshots_instructions(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'allowProofUpload' => true,
            'paymentWindowHours' => 24,
            'quietDisclosure' => 'Your order is reserved and will be confirmed after payment verification.',
            'verificationInstructions' => ['Use Instant Transfer.', 'Use the order number as reference.'],
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'bankCode' => 'MBBEMYKL',
                'accountHolderName' => 'JW Studio', 'accountNumber' => '514000004432',
                'duitNowId' => '2026010088991', 'isPrimary' => true, 'isActive' => true,
            ]],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('bank_transfer'))
            ->assertRedirect('/order/success');

        $this->assertDatabaseHas('orders', ['email' => 'buyer@example.com', 'status' => 'pending_payment']);
        $this->assertDatabaseHas('order_payments', [
            'gateway' => 'bank_transfer', 'provider' => 'bank_transfer', 'status' => 'pending',
            'amount' => 10000, 'currency' => 'MYR',
        ]);
        $payment = \App\Models\OrderPayment::query()->firstOrFail();
        $this->assertSame('maybank-primary', $payment->configuration_snapshot['bankAccount']['id']);
        $this->assertTrue($payment->configuration_snapshot['allowProofUpload']);
        $this->assertNotNull($payment->expires_at);
    }

    public function test_cod_creates_a_pending_order_with_its_surcharge_snapshot(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'cod_config'], ['value' => ['data' => [
            'isEnabled' => true, 'allowedRegions' => 'peninsular_only',
            'minOrderAmount' => 0, 'maxOrderAmount' => 0,
            'surchargeType' => 'fixed', 'surchargeAmount' => 5,
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('cod'))
            ->assertRedirect('/order/success');

        $this->assertDatabaseHas('orders', ['email' => 'buyer@example.com', 'status' => 'pending_payment', 'total' => 10500]);
        $this->assertDatabaseHas('order_payments', [
            'gateway' => 'cod', 'provider' => 'cod', 'status' => 'pending', 'amount' => 10500,
        ]);
    }

    public function test_bank_transfer_receipt_is_stored_only_when_operator_enabled_uploads(): void
    {
        Storage::fake('public');
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true, 'allowProofUpload' => true, 'paymentWindowHours' => 24,
            'bankAccounts' => [[
                'id' => 'maybank-primary', 'bankName' => 'Maybank', 'accountNumber' => '514000004432', 'isActive' => true,
            ]],
        ]]]);
        $receipt = UploadedFile::fake()->create('receipt.png', 100, 'image/png');

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('bank_transfer') + ['payment_proof' => $receipt])
            ->assertRedirect('/order/success');

        $payment = \App\Models\OrderPayment::query()->firstOrFail();
        $this->assertNotNull($payment->proof_path);
        Storage::disk('public')->assertExists($payment->proof_path);
    }

    public function test_bank_transfer_snapshots_the_customer_selected_active_account(): void
    {
        $variant = $this->variant();
        StoreSetting::query()->updateOrCreate(['key' => 'bank_transfer'], ['value' => ['data' => [
            'isEnabled' => true,
            'bankAccounts' => [
                ['id' => 'maybank-primary', 'bankName' => 'Maybank', 'accountNumber' => '514000004432', 'isPrimary' => true, 'isActive' => true],
                ['id' => 'cimb-secondary', 'bankName' => 'CIMB Bank', 'accountNumber' => '800900008871', 'isPrimary' => false, 'isActive' => true],
            ],
        ]]]);

        $this->withSession(['cart' => [$variant->id => 1]])
            ->post('/checkout', $this->checkoutData('bank_transfer') + ['bank_account_id' => 'cimb-secondary'])
            ->assertRedirect('/order/success');

        $payment = \App\Models\OrderPayment::query()->firstOrFail();
        $this->assertSame('cimb-secondary', $payment->configuration_snapshot['bankAccount']['id']);
    }

    private function checkoutData(string $paymentMethod): array
    {
        return [
            'email' => 'buyer@example.com', 'first_name' => 'Alex', 'last_name' => 'Buyer',
            'line_1' => '10 Studio Road', 'city' => 'Kuala Lumpur', 'state' => 'Selangor',
            'postal_code' => '50000', 'country' => 'MY', 'delivery' => 'standard',
            'payment_method' => $paymentMethod,
        ];
    }

    private function variant()
    {
        $category = Category::query()->create(['name' => 'Audio', 'slug' => 'audio', 'status' => 'active']);
        $product = Product::query()->create(['category_id' => $category->id, 'title' => 'Studio Headphones', 'handle' => 'studio-headphones', 'brand' => 'JW Studio', 'description' => 'Test', 'base_price' => 10000, 'status' => 'active']);

        return $product->variants()->create(['sku' => 'STUDIO-1', 'title' => 'Default', 'price' => 10000, 'stock' => 10, 'enabled' => true]);
    }
}
