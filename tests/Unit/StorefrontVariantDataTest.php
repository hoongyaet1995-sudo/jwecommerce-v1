<?php
namespace Tests\Unit;
use App\Models\ProductVariant;
use App\Services\StorefrontVariantData;
use App\Services\Inventory\WebsiteInventoryAvailability;
use PHPUnit\Framework\TestCase;
class StorefrontVariantDataTest extends TestCase
{
 public function test_selection_uses_authoritative_stock_and_preserves_requested_variant(): void
 {
  $a = (new ProductVariant())->forceFill(['id'=>1,'stock'=>99,'enabled'=>false]);
  $b = (new ProductVariant())->forceFill(['id'=>2,'stock'=>0,'enabled'=>true]);
  $inventory = \Mockery::mock(WebsiteInventoryAvailability::class);
  $inventory->shouldReceive('quantity')->with($b)->andReturn(3);
  $data = new StorefrontVariantData($inventory);
  $this->assertSame($b, $data->select(collect([$a,$b]), null));
  $this->assertSame($a, $data->select(collect([$a,$b]), 1));
  $this->assertFalse($data->canAdd($a));
  \Mockery::close();
 }
 public function test_payload_excludes_internal_fields_and_keeps_unknown_inventory(): void
 {
  $variant = new ProductVariant(['id'=>7,'price'=>4900,'cost_price'=>2000,'enabled'=>true,'options'=>[]]);
  $inventory = \Mockery::mock(WebsiteInventoryAvailability::class);
  $inventory->shouldReceive('quantity')->with($variant)->andReturn(null);
  $data = new StorefrontVariantData($inventory);
  $payload = $data->payload(collect([$variant]), collect())->first();
  $this->assertNull($payload['stock']);
  $this->assertSame(4900,$payload['price']);
  $this->assertArrayNotHasKey('cost_price',$payload);
  $this->assertFalse($data->canAdd($variant));
  \Mockery::close();
 }
}
