<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderTaxLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TaxQuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_tax_line_cannot_be_updated_after_its_creation(): void
    {
        $line = OrderTaxLine::factory()->create([
            'order_id' => $this->order()->id,
            'tax_amount' => 600,
        ]);

        $this->expectException(LogicException::class);

        $line->update(['tax_amount' => 700]);
    }

    public function test_an_order_tax_line_rejects_bulk_query_updates_at_the_database_boundary(): void
    {
        $line = OrderTaxLine::factory()->create([
            'order_id' => $this->order()->id,
            'tax_amount' => 600,
        ]);

        $this->expectException(QueryException::class);

        OrderTaxLine::query()->whereKey($line)->update(['tax_amount' => 700]);
    }

    private function order(): Order
    {
        return Order::query()->create([
            'number' => 'TAX-'.str()->upper(str()->random(12)),
            'email' => 'tax-history@example.com',
            'currency' => 'MYR',
            'currency_exchange_rate' => '1.00000000',
            'status' => 'pending_payment',
            'shipping_method' => 'standard',
            'subtotal' => 0,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
        ]);
    }
}
