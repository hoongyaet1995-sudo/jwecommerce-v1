<?php

namespace Tests\Feature\Payment;

use App\Models\PaymentGatewayCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistenceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_credentials_are_encrypted_and_round_trip_without_living_in_gateway_metadata(): void
    {
        $credential = PaymentGatewayCredential::query()->create([
            'gateway_id' => 'stripe',
            'environment' => 'sandbox',
            'credentials' => ['secretKey' => 'sk_test_private'],
        ]);

        $this->assertSame('sk_test_private', $credential->fresh()->credentials['secretKey']);
        $this->assertDatabaseHas('payment_gateway_credentials', [
            'gateway_id' => 'stripe',
            'environment' => 'sandbox',
        ]);
    }
}
