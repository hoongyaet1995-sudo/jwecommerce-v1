<?php
namespace Tests\Feature;

use App\Services\WebsiteThemeDemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LogisticsThemeDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_demo_is_unsaved_and_preserves_mixed_purchase_modes_and_faqs(): void
    {
        $request=Request::create('/admin/website-design/preview','GET',['theme'=>'logistics']);
        $catalog=app(WebsiteThemeDemoData::class)->catalog($request);
        $this->assertSame('logistics',$catalog['demoTheme']);
        $this->assertCount(6,$catalog['demoProducts']);
        $this->assertSame(3,$catalog['demoProducts']->where('purchase_mode','quote')->count());
        $this->assertSame(3,$catalog['demoProducts']->filter(fn($p)=>$p->isBuyMode())->count());
        foreach($catalog['demoProducts'] as $product){$this->assertFalse($product->exists);$this->assertNotEmpty($product->pdpContent->faqs);$this->assertNotEmpty($product->tags);}
        $this->assertDatabaseCount('products',0);
        $this->assertCount(2,app(WebsiteThemeDemoData::class)->cart($catalog)['items']);
        $request=Request::create('/admin/website-design/preview','GET',['theme'=>'logistics','purchaseMode'=>'display','priceDisplay'=>'hide']);
        $explicit=app(WebsiteThemeDemoData::class)->catalog($request);
        $this->assertSame(6,$explicit['demoProducts']->where('purchase_mode','display')->count());
    }

    public function test_all_logistics_acceptance_surfaces_render_through_protected_official_renderers(): void
    {
        $this->withoutVite();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        foreach(array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']) as $scenario){
            $response=$this->get('/admin/website-design/acceptance/sample/'.$scenario.'?theme=logistics');
            $this->assertSame(200,$response->getStatusCode(),'Failed Logistics surface: '.$scenario.' '.substr($response->getContent(),0,400));
            $response->assertHeader('X-Theme-Data','dummy');
        }
        $this->assertDatabaseCount('products',0);
    }

    public function test_demo_shell_tracking_and_approved_references_do_not_fall_back_to_food(): void
    {
        $this->withoutVite();
        $this->actingAs(\App\Models\User::factory()->create(['is_admin'=>true]));
        $this->get('/admin/website-design/acceptance/sample/plp?theme=logistics')->assertOk()->assertSee('Containers. Logistics. A more connected tomorrow.')->assertDontSee('Pantry staples')->assertDontSee('Good food. Brighter days.');
        $this->get('/admin/website-design/acceptance/sample/order-tracking-verified?theme=logistics')->assertOk()->assertSee('demonstration accessory order')->assertDontSee('pantry favourites')->assertDontSee('data-stage-completed="delivered"',false)->assertSee('data-stage-completed="payment"',false);
        foreach(['all-products','plp','pdp','filter','quick-buy','cart-drawer','shopping-cart','checkout','quotation-checkout','payment-pending','login','register','forgot-password','reset-password','my-account','account-orders','account-addresses','account-profile','account-saved-items','account-vouchers','order-tracking','order-tracking-verify-code','order-tracking-verified','review-dialog','return-dialog','quotation-document'] as $scenario){
            $this->get('/admin/website-design/acceptance/reference/'.$scenario.'?theme=logistics')->assertOk();
        }
    }
}
