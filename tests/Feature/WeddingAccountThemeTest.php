<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\{WebsiteThemeDemoData,WebsiteThemeAccountPreview};
use Illuminate\Http\Request;
final class WeddingAccountThemeTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void { parent::setUp(); view()->share('errors', new \Illuminate\Support\ViewErrorBag); }
 public function test_auth_modes_preserve_canonical_fields_and_routes():void {
  foreach(['login','register','forgot','reset'] as $mode){
   $html=view('storefront.themes.wedding-auth',['mode'=>$mode,'shopBrandName'=>'Test','websiteDesignPreview'=>true,'token'=>'sample-token','email'=>'sample@example.test'])->render();
   $this->assertStringContainsString('data-wedding-auth="'.$mode.'"',$html);
   $this->assertStringContainsString('wd-auth-panel',$html);
   $this->assertStringContainsString('name="_token"',$html);
   if($mode==='register'){$this->assertStringContainsString('name="name"',$html);$this->assertStringNotContainsString('name="first_name"',$html);}
   if($mode==='reset')$this->assertStringContainsString('name="token" value="sample-token"',$html);
  }
 }
 public function test_account_renders_real_snapshots_forms_and_empty_states():void {
  $catalog=app(WebsiteThemeDemoData::class)->catalog(Request::create('/demo','GET',['theme'=>'jewelry']));
  $data=app(WebsiteThemeDemoData::class)->account($catalog);
  $html=view('storefront.themes.wedding-account',['accountPayload'=>$data,'websiteDesignPreview'=>true,'websiteThemeDemo'=>true,'shopBrandName'=>'Test'])->render();
  $this->assertStringContainsString('data-wedding-account',$html);
  $this->assertStringContainsString($data['orders'][0]['number'],$html);
  $this->assertStringContainsString('name="current_password"',$html);
  $this->assertStringContainsString('x-trap.inert.noscroll="reviewTarget"',$html);
  $this->assertStringContainsString('x-trap.inert.noscroll="returnTarget"',$html);
  $this->assertStringContainsString('data-review-endpoint="'.route('customer.reviews.store').'"',$html);
  $this->assertStringContainsString('action="'.route('customer.returns.store').'"',$html);
  $this->assertStringContainsString('wd-dialog-heading',$html);
  $this->assertStringContainsString('readonly autocomplete="email"',$html);
  $this->assertStringContainsString('name="first_name"',$html);
  $empty=WebsiteThemeAccountPreview::empty();
  $html=view('storefront.themes.wedding-account',['accountPayload'=>$empty,'websiteDesignPreview'=>true,'websiteThemeDemo'=>true,'shopBrandName'=>'Test'])->render();
  $this->assertStringContainsString('No orders yet.',$html);
  $this->assertStringContainsString('No saved items yet.',$html);
  $this->assertStringContainsString('No saved addresses.',$html);
 }
}
