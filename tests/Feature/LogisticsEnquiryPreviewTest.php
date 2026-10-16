<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User,Product};
use App\Services\{LogisticsEnquiryPreview,WebsiteThemeDemoData};
use Illuminate\Http\Request;
class LogisticsEnquiryPreviewTest extends TestCase
{
 use RefreshDatabase;
 public function test_only_admin_fixture_identity_can_enable_preview_form():void
 {
  $request=Request::create('/admin/website-design/preview');
  $route=new \Illuminate\Routing\Route('GET','admin/website-design/preview',fn()=>null);$route->name('admin.website-design.preview');$request->setRouteResolver(fn()=>$route);
  $admin=new User(['is_admin'=>true]);$request->setUserResolver(fn($guard=null)=>$admin);
  $request->attributes->set('websiteDesignPreview','logistics');
  $fixture=new Product(['title'=>'Demo','purchase_mode'=>'quote']);$fixture->id=-100;
  $request->attributes->set('websiteThemeDemoProducts',collect([$fixture]));$this->app->instance('request',$request);$request->setUserResolver(fn($guard=null)=>$admin);
  $this->assertTrue(request()->routeIs('admin.website-design.preview'),'route');$this->assertTrue((bool)request()->user('web')?->is_admin,'admin');$this->assertSame('logistics',request()->attributes->get('websiteDesignPreview'));$this->assertTrue(collect(request()->attributes->get('websiteThemeDemoProducts'))->contains(fn($p)=>$p===$fixture),'identity');$gate=app(LogisticsEnquiryPreview::class);$this->assertTrue($gate->allows($fixture));
  $this->assertFalse($gate->allows(clone $fixture));
  $fixture->exists=true;$this->assertFalse($gate->allows($fixture));$fixture->exists=false;
  $request->setUserResolver(fn()=>new User(['is_admin'=>false]));$this->assertFalse($gate->allows($fixture));
  $request->setUserResolver(fn()=>$admin);$route->name('public-shop');$this->assertFalse($gate->allows($fixture));
 }
 public function test_protected_modal_and_receipt_are_read_only_unsaved_fixtures():void
 {
  $this->withoutVite();
  $url='/admin/website-design/acceptance/sample/';
  $this->get($url.'enquiry-modal?theme=logistics')->assertRedirect();
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->get($url.'enquiry-modal?theme=logistics')->assertOk()->assertSee('data-enquiry-dialog',false)->assertSee('data-enquiry-form',false)->assertSee('Demo only. No store changes were made.');
  $this->get($url.'enquiry-receipt?theme=logistics&support=1')->assertOk()->assertSee('DEMO-ENQUIRY-001')->assertSee('No enquiry has been submitted or saved.')->assertDontSee('data-enquiry-support-open',false);
  $this->get($url.'enquiry-modal?theme=food')->assertNotFound();
  config(['product_enquiries.enabled'=>true]);
  $this->post('/enquiries',['product_id'=>-900,'variant_id'=>-9000,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'name'=>'Demo','email'=>'sample@example.test','requirements'=>'Forged fixture submit'])->assertSessionHasErrors('product_id',null,'enquiry');
  $this->assertDatabaseCount('products',0);$this->assertDatabaseCount('product_enquiries',0);$this->assertDatabaseCount('support_conversations',0);
 }
 public function test_purchase_renderer_respects_quote_hidden_price_and_display_modes():void
 {
  $this->withoutVite();$this->actingAs(User::factory()->create(['is_admin'=>true]));
  $url='/admin/website-design/preview?theme=logistics&surface=pdp';
  $this->get($url.'&purchaseMode=quote&priceDisplay=hide')->assertOk()->assertSee('Request a quote')->assertSee('Price on request')->assertDontSee('data-pdp-add-to-cart',false);
  $this->get($url.'&purchaseMode=display&priceDisplay=hide')->assertOk()->assertDontSee('data-pdp-add-to-cart',false)->assertDontSee('data-product-enquiry class=',false)->assertDontSee('data-pdp-price',false);
  $this->get($url.'&purchaseMode=buy&priceDisplay=exact')->assertOk()->assertSee('data-pdp-add-to-cart',false)->assertDontSee('data-product-enquiry class=',false);
 }
 public function test_quotation_document_preview_is_unsaved_and_resolves_standalone_style():void
 {
  $this->withoutVite();$this->actingAs(User::factory()->create(['is_admin'=>true]));
  $url='/admin/website-design/acceptance/sample/quotation-document?theme=logistics';
  $this->get($url)->assertOk()->assertSee('DEMO-QT-002')->assertSee('Barlow Semi Condensed')->assertSee('--main:#173247',false)->assertSee('--quote-sub:#EF7B32',false)->assertDontSee('Accept &amp; proceed',false)->assertDontSee('Discuss quotation');
  $custom=['logistics'=>['style'=>['font'=>'classic','colorMode'=>'custom','main'=>'#224466','sub'=>'#CC7744']]];
  $this->get($url.'&'.http_build_query(['customization'=>json_encode($custom)]))->assertOk()->assertSee('--main:#224466',false)->assertSee('--quote-sub:#CC7744',false)->assertSee('--lg-heading-font:Georgia,serif',false);
  $this->assertDatabaseCount('product_enquiries',0);$this->assertDatabaseCount('enquiry_quotation_versions',0);$this->assertDatabaseCount('enquiry_quotations',0);$this->assertDatabaseCount('orders',0);
 }

 public function test_enquiry_surface_persists_existing_media_and_uses_protected_no_write_preview():void
 {
  $this->withoutVite();$this->actingAs(User::factory()->create(['is_admin'=>true]));
  $customizer=app(\App\Services\WebsiteThemeCustomization::class);
  $profile=['logistics'=>['pages'=>['enquiry'=>['font'=>'logistics','size'=>'big','colorMode'=>'custom','main'=>'#224466','sub'=>'#CC7744']],'media'=>['enquiryReceipt'=>['desktop'=>'/images/enquiry-hero.png','mobile'=>'/images/enquiry-mobile.png','alt'=>'Container enquiry','focus'=>'right','focusY'=>'top','hidden'=>false]]]];
  $saved=app(\App\Services\WebsiteDesignService::class)->save(['theme'=>'logistics','overrides'=>[],'customization'=>$profile]);
  $this->assertSame($profile,$saved['customization']);
  $this->assertSame(['enquiryReceipt'],$customizer->mediaContracts()['logistics']['enquiry']);
  $this->assertSame(['checkoutSuccess'],$customizer->mediaContracts()['logistics']['checkoutSuccess']);
  $this->assertSame('/images/enquiry-hero.png',$customizer->media('logistics','enquiry','/fallback.png')['desktop']);
  $before=\App\Models\StoreSetting::where('key','website_design')->value('value');
  $this->get('/admin/website-design/preview?'.http_build_query(['theme'=>'logistics','surface'=>'enquiry','customization'=>json_encode($profile)]))->assertOk()->assertSee('DEMO-ENQUIRY-001')->assertDontSee('data-enquiry-support-open',false);
  $this->assertSame('enquiry',$customizer->page());
  $this->assertSame($before,\App\Models\StoreSetting::where('key','website_design')->value('value'));
  $this->assertDatabaseCount('product_enquiries',0);$this->assertDatabaseCount('support_conversations',0);
  $route=new \Illuminate\Routing\Route('GET','enquiries/{enquiry}',fn()=>null);$route->name('enquiries.receipt');
  request()->attributes->remove('websiteThemeSurface');request()->setRouteResolver(fn()=>$route);
  $this->assertSame('enquiry',$customizer->page());
 }

}
