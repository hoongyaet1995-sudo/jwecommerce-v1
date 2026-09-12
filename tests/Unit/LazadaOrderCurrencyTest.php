<?php
namespace Tests\Unit;
use App\Services\Marketplace\LazadaOrderCurrency;
use PHPUnit\Framework\TestCase;
class LazadaOrderCurrencyTest extends TestCase
{
    public function test_currency_requires_explicit_consistent_platform_data():void
    {
        $this->assertSame('MYR',LazadaOrderCurrency::fromPayload(['order_items'=>[['currency'=>'MYR'],['currency'=>'MYR']]]));
        $this->assertNull(LazadaOrderCurrency::fromPayload(['order_items'=>[['currency'=>'MYR'],['currency'=>'SGD']]]));
        $this->assertNull(LazadaOrderCurrency::fromPayload(['order_items'=>[['currency'=>'MYR'],[]]]));
        $this->assertNull(LazadaOrderCurrency::fromPayload(['region'=>'MY']));
    }
}
