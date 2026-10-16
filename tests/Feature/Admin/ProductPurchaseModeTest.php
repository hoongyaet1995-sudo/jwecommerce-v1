<?php

namespace Tests\Feature\Admin;

use App\Models\{Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchaseModeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return array_replace(['title'=>'Mode acceptance item','slug'=>'mode-acceptance-item','categoryId'=>null,'collectionIds'=>[],
            'descriptionHtml'=>'<p>Acceptance</p>','images'=>[],'status'=>'draft','hasVariants'=>false,'basePrice'=>2000,
            'options'=>[],'tags'=>[],'featured'=>false,'variants'=>[['sku'=>'MODE-ONE','title'=>'One option','price'=>2000,
            'options'=>[],'inventoryQuantity'=>3,'enabled'=>true]]], $changes);
    }

    public function test_existing_and_omitted_settings_default_to_buy_without_changing_price(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $data=$this->postJson('/admin/api/catalog/products',$this->payload(['status'=>'active']))->assertCreated()
            ->assertJsonPath('data.purchaseMode','buy')->assertJsonPath('data.priceDisplay','exact')->json('data');
        $this->assertDatabaseHas('products',['id'=>$data['id'],'purchase_mode'=>'buy','base_price'=>200000]);
    }

    public function test_draft_settings_round_trip_and_omitted_fields_preserve_saved_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $payload=$this->payload(['purchaseMode'=>'quote','priceDisplay'=>'from']);
        $data=$this->postJson('/admin/api/catalog/products',$payload)->assertCreated()->assertJsonPath('data.priceDisplay','from')->json('data');
        $payload['variants'][0]['id']=$data['variants'][0]['id'];
        unset($payload['variants'][0]['inventoryQuantity']);
        $payload['purchaseMode']='display';
        $this->patchJson('/admin/api/catalog/products/'.$data['id'],$payload)->assertOk()->assertJsonPath('data.purchaseMode','display')->assertJsonPath('data.priceDisplay','from');
        unset($payload['purchaseMode'],$payload['priceDisplay']);
        $this->patchJson('/admin/api/catalog/products/'.$data['id'],$payload)->assertOk()->assertJsonPath('data.purchaseMode','display')->assertJsonPath('data.priceDisplay','from');
        $row=collect($this->getJson('/admin/api/catalog')->assertOk()->json('products'))->firstWhere('id',$data['id']);
        $this->assertSame('display',$row['purchaseMode']);
        $this->assertSame(2000,$row['variants'][0]['price']);
        $this->assertDatabaseHas('product_variants',['id'=>$data['variants'][0]['id'],'stock'=>3]);
        $payload['purchaseMode']='quote';$payload['priceDisplay']='hide';
        $this->patchJson('/admin/api/catalog/products/'.$data['id'],$payload)->assertOk()->assertJsonPath('data.priceDisplay','hide')->assertJsonPath('data.basePrice',2000);
    }

    public function test_invalid_settings_and_non_draft_modes_are_rejected_without_writes(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach(['quote','display'] as $mode)foreach(['active','archived'] as $status)
            $this->postJson('/admin/api/catalog/products',$this->payload(['purchaseMode'=>$mode,'status'=>$status]))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/admin/api/catalog/products',$this->payload(['purchaseMode'=>'unknown']))->assertUnprocessable()->assertJsonValidationErrors('purchaseMode');
        $this->postJson('/admin/api/catalog/products',$this->payload(['priceDisplay'=>'unknown']))->assertUnprocessable()->assertJsonValidationErrors('priceDisplay');
        $this->assertSame(0,Product::count());
    }

    public function test_legacy_model_writer_cannot_republish_non_buy_draft(): void
    {
        $item=Product::create(['title'=>'Draft service','handle'=>'draft-service','base_price'=>100,'status'=>'draft','purchase_mode'=>'quote']);
        try {$item->update(['status'=>'active']);$this->fail('Publication was permitted');}
        catch(\Illuminate\Validation\ValidationException $error){$this->assertArrayHasKey('status',$error->errors());}
        $this->assertSame('draft',$item->fresh()->status);
        $this->get('/products/draft-service')->assertNotFound();
    }

    public function test_guest_cannot_write_product_settings(): void
    {
        $this->postJson('/admin/api/catalog/products',$this->payload(['purchaseMode'=>'quote']))->assertUnauthorized();
    }
}
