<?php
namespace Tests\Feature\Admin;
use App\Models\StoreSetting;
use App\Models\PaymentGatewayCredential;
use App\Services\PaymentInformationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
final class CmsPaymentInformationTest extends TestCase
{
 use RefreshDatabase;
 public function test_projection_endpoint_is_admin_only_and_validates_currency():void {
  $this->getJson('/admin/api/cms/payment-methods')->assertUnauthorized();
  $admin=\App\Models\User::factory()->create(['is_admin'=>true]);
  $this->actingAs($admin)->getJson('/admin/api/cms/payment-methods?currency=MYR')->assertOk()->assertJsonStructure(['methods','bankTransfer']);
  $this->getJson('/admin/api/cms/payment-methods?currency=invalid')->assertUnprocessable();
 }
 public function test_payment_section_owns_layout_and_uses_safe_public_runtime_facts():void {
  $document=['title'=>'Payment','slug'=>'payment','template'=>'standard','sections'=>[['id'=>'methods','type'=>'payment_methods','title'=>'Ways to pay','isVisible'=>true,'paymentLayout'=>'list','paymentNote'=>'Confirmed at checkout','textAlignment'=>'left']]];
  $this->assertSame($document,app(\App\Domain\Cms\PageDocumentContract::class)->validate($document));
  $facts=app(\App\Domain\Cms\RuntimeAdapterRegistry::class)->factsFor($document);
  $this->assertSame(['methods'=>[],'bankTransfer'=>false],$facts['sections'][0]['payments']);
 }
 public function test_public_filters_setup_sandbox_and_disabled_and_projects_no_secrets():void {
  StoreSetting::query()->updateOrCreate(['key'=>'payment_gateways'],['value'=>['data'=>[
   ['id'=>'stripe','name'=>'Credit Card','category'=>'card','isEnabled'=>true,'isSandbox'=>false,'limits'=>['minAmount'=>1],'supportedCurrencies'=>['MYR']],
   ['id'=>'paypal','name'=>'PayPal','category'=>'card','isEnabled'=>true,'isSandbox'=>true],
   ['id'=>'atome','name'=>'Atome','category'=>'bnpl','isEnabled'=>true,'isSandbox'=>true],
   ['id'=>'future_fpx','name'=>'Future local bank','category'=>'online_banking_fpx','isEnabled'=>true],
   ['id'=>'billplz','name'=>'Billplz','isEnabled'=>false],
  ]]]);
  PaymentGatewayCredential::query()->create(['gateway_id'=>'stripe','environment'=>'live','credentials'=>['publishableKey'=>'pk_fixture','secretKey'=>'secret_fixture','webhookSecret'=>'hook_fixture']]);
  $service=app(PaymentInformationService::class);
  $public=$service->facts('MYR');$this->assertSame(['stripe'],array_column($public['methods'],'id'));
  $admin=$service->facts('MYR',true);$this->assertCount(4,$admin['methods']);
  $this->assertSame('integration_pending',$admin['methods'][3]['status']);
  $this->assertStringNotContainsString('secret_fixture',json_encode($admin));
  $this->assertStringNotContainsString('credentials',json_encode($admin));
  $this->assertSame([], $service->facts('USD')['methods']);
 }
 public function test_offline_methods_require_enabled_valid_configuration_and_never_expose_accounts():void {
  StoreSetting::query()->updateOrCreate(['key'=>'bank_transfer'],['value'=>['data'=>['isEnabled'=>true,'bankAccounts'=>[['isActive'=>true,'accountNumber'=>'private-account','bankName'=>'Bank']]]]]);
  StoreSetting::query()->updateOrCreate(['key'=>'cod_config'],['value'=>['data'=>['isEnabled'=>true,'allowedRegions'=>'peninsular_only']]]);
  $facts=app(PaymentInformationService::class)->facts('MYR');$this->assertSame(['bank_transfer','cod'],array_column($facts['methods'],'id'));
  $this->assertTrue($facts['bankTransfer']);$this->assertStringNotContainsString('private-account',json_encode($facts));
 }
}
