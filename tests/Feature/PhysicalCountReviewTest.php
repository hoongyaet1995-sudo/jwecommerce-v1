<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\PhysicalCountReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhysicalCountReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $actor = User::factory()->create(['is_admin'=>true]);
        $variant = Product::create(['title'=>'Count','handle'=>'count','base_price'=>100])
            ->variants()->create(['sku'=>'COUNT','title'=>'One','price'=>100,'stock'=>97]);
        $pool = DB::table('inventory_pools')->insertGetId(['pool_key'=>'shared','kind'=>'shared']);
        $balance = DB::table('inventory_balances')->insertGetId(['inventory_pool_id'=>$pool,'product_variant_id'=>$variant->id]);
        return [$actor,$variant,$balance];
    }

    public function test_review_does_not_mutate_and_confirmation_records_zero_once_without_inventing_atp(): void
    {
        [$actor,$variant,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $review = $service->review($balance, 0, 'Shelf counted empty', $actor);
        $this->assertNull(DB::table('inventory_balances')->where('id',$balance)->value('physical_stock'));
        $this->assertDatabaseCount('inventory_physical_movements',0);
        $first = $service->confirm($review['id'], $actor, true);
        $again = $service->confirm($review['id'], $actor, true);
        $this->assertSame($first,$again);
        $this->assertSame(0,$first['physical_after']);
        $this->assertNull($first['physical_before']);
        $this->assertNull($first['physical_delta']);
        $this->assertSame($actor->id,$first['actor_id']);
        $this->assertDatabaseCount('inventory_physical_movements',1);
        $row = DB::table('inventory_balances')->find($balance);
        $this->assertSame(1,(int)$row->version);
        $this->assertNull($row->reserved_stock);
        $this->assertNull($row->obligations_reconciled_at);
        $this->assertSame(97,(int)$variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements',0);
    }

    public function test_another_operator_cannot_confirm_the_review(): void
    {
        [$actor,,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $review = $service->review($balance,10,'Count',$actor);
        $this->expectException(ValidationException::class);
        $service->confirm($review['id'],User::factory()->create(['is_admin'=>true]),true);
    }

    public function test_legacy_availability_change_invalidates_review(): void
    {
        [$actor,$variant,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $review = $service->review($balance,10,'Count',$actor);
        DB::table('product_variants')->where('id',$variant->id)->increment('stock_version');
        try { $service->confirm($review['id'],$actor,true); $this->fail('Stale review accepted'); }
        catch (ValidationException $exception) { $this->assertStringContainsString('changed',$exception->getMessage()); }
        $this->assertDatabaseCount('inventory_physical_movements',0);
    }

    public function test_second_review_becomes_stale_after_first_count(): void
    {
        [$actor,,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $first = $service->review($balance,10,'First',$actor);
        $second = $service->review($balance,20,'Second',$actor);
        $service->confirm($first['id'],$actor,true);
        $this->expectException(ValidationException::class);
        $service->confirm($second['id'],$actor,true);
    }

    public function test_expired_review_is_rejected(): void
    {
        [$actor,,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $review = $service->review($balance,10,'Count',$actor);
        $this->travel(16)->minutes();
        $this->expectException(ValidationException::class);
        $service->confirm($review['id'],$actor,true);
    }

    public function test_negative_count_is_rejected(): void
    {
        [$actor,,$balance] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(PhysicalCountReviewService::class)->review($balance,-1,'Count',$actor);
    }

    public function test_acknowledgement_is_required_and_revoked_admin_cannot_confirm(): void
    {
        [$actor,,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $review = $service->review($balance,10,'Count',$actor);
        try { $service->confirm($review['id'],$actor,false); $this->fail('Missing acknowledgement accepted'); }
        catch (ValidationException $exception) { $this->assertStringContainsString('Acknowledge',$exception->getMessage()); }
        DB::table('users')->where('id',$actor->id)->update(['is_admin'=>false]);
        try { $service->confirm($review['id'],$actor,true); $this->fail('Revoked privilege accepted'); }
        catch (ValidationException $exception) { $this->assertStringContainsString('administrator',$exception->getMessage()); }
        $this->assertDatabaseCount('inventory_physical_movements',0);
    }

    public function test_subsequent_count_keeps_the_previous_receipt_and_records_real_delta(): void
    {
        [$actor,,$balance] = $this->fixture();
        $service = app(PhysicalCountReviewService::class);
        $first = $service->review($balance,10,'First',$actor);
        $receipt = $service->confirm($first['id'],$actor,true);
        $second = $service->review($balance,8,'Recount',$actor);
        $recount = $service->confirm($second['id'],$actor,true);
        $this->assertSame(-2,$recount['physical_delta']);
        $this->assertSame(10,$recount['physical_before']);
        $this->assertSame(2,$recount['balance_version']);
        $this->assertSame($receipt,$service->confirm($first['id'],$actor,true));
        $this->assertDatabaseCount('inventory_physical_movements',2);
    }
}
