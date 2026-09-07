<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reads_paginated_sku_history_without_internal_hash_or_other_sku_records(): void
    {
        $p=Product::create(['title'=>'Test','handle'=>'test','base_price'=>10,'status'=>'draft']);
        $v=$p->variants()->create(['sku'=>'TEST','title'=>'One','price'=>10,'stock'=>10]);
        $other=$p->variants()->create(['sku'=>'OTHER','title'=>'Other','price'=>10,'stock'=>10]);
        $service=app(InventoryMutationService::class);
        $service->change($v->id,-2,'order:1','Reservation');
        $service->change($v->id,1,'return:1','Return received');
        $service->change($other->id,-1,'other:1','OTHER PRIVATE REASON');
        $url='/admin/api/inventory/movements?variantId='.$v->id.'&per_page=1';
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>false]));
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $r=$this->getJson($url)->assertOk()->assertJsonPath('total',2)
            ->assertJsonPath('data.0.available_after',9)->assertJsonPath('data.0.available_delta',1);
        $this->assertStringNotContainsString('request_hash',$r->getContent());
        $this->assertStringNotContainsString('OTHER PRIVATE REASON',$r->getContent());
        $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('data.0.available_delta',-2);
        $this->getJson('/admin/api/inventory/movements')->assertUnprocessable();
        $this->getJson($url.'&per_page=101')->assertUnprocessable();
        $this->getJson('/admin/api/inventory/movements?variantId=99999')->assertNotFound();
        $this->assertSame(9,(int)$v->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',3);
    }
}
