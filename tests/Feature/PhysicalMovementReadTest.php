<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryBalanceProvisioner;
use App\Services\Inventory\PhysicalCountReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalMovementReadTest extends TestCase
{
    use RefreshDatabase;
    public function test_physical_ledger_reads_reviewed_counts_with_pool_identity(): void
    {
        $actor=User::factory()->create(['is_admin'=>true]);
        $variant=Product::create(['title'=>'Ledger','handle'=>'ledger','base_price'=>100])
            ->variants()->create(['sku'=>'LEDGER','title'=>'One','price'=>100,'stock'=>7]);
        $balance=app(InventoryBalanceProvisioner::class)->provision($variant->id,'shared',null);
        $service=app(PhysicalCountReviewService::class);
        $review=$service->review($balance['balance_id'],10,'Explicit initial count',$actor);
        $service->confirm($review['id'],$actor,true);
        $url='/admin/api/inventory/physical-movements?variantId='.$variant->id;
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($actor)->getJson($url)->assertOk()->assertJsonCount(1,'data')
            ->assertJsonPath('data.0.physical_before',null)->assertJsonPath('data.0.physical_delta',null)
            ->assertJsonPath('data.0.physical_after',10)->assertJsonPath('data.0.pool_kind','shared')
            ->assertJsonPath('data.0.actor_id',$actor->id);
        $this->assertDatabaseCount('inventory_movements',0);
    }
}
