<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\{Customer,User,ProductEnquiry,EnquiryQuotation,EnquiryQuotationVersion,Order,OrderRefund};
use App\Domain\Accounts\{CustomerQuotationPayloadFactory,OriginalAccountPayloadFactory};
use App\Services\WebsiteThemeAccountPreview;

class LogisticsAccountThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_quotations_require_account_identity_and_hide_drafts_and_email_matches(): void
    {
        $user=User::factory()->create();
        $customer=Customer::create(['user_id'=>$user->id,'email'=>$user->email]);
        foreach ([$user->id, null, User::factory()->create()->id, $user->id] as $index=>$owner) {
            $enquiry=ProductEnquiry::create(['id'=>(string)Str::uuid(),'reference'=>'Q-'.$index,'owner_hash'=>str_repeat((string)$index,64),'request_key'=>(string)Str::uuid(),'payload_hash'=>str_repeat('a',64),'customer_user_id'=>$owner,'product_title'=>'Container','product_snapshot'=>[],'name'=>'Buyer','email'=>$user->email,'requirements'=>'Container enquiry']);
            $quotation=EnquiryQuotation::create(['id'=>(string)Str::uuid(),'product_enquiry_id'=>$enquiry->id,'number'=>'QT-'.$index,'document'=>[]]);
            if ($index===3) continue;
            EnquiryQuotationVersion::create(['id'=>(string)Str::uuid(),'enquiry_quotation_id'=>$quotation->id,'revision'=>1,'document'=>['number'=>'QT-'.$index],'sent_at'=>now(),'expires_at'=>now()->addDays(2),'sent_by'=>$user->id]);
        }
        $list=app(CustomerQuotationPayloadFactory::class)->for($customer);
        $this->assertCount(1,$list);
        $this->assertSame('QT-0',$list[0]['number']);
        $request=\Illuminate\Http\Request::create($list[0]['url']);
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn ($guard=null) => $user);
        $owned=ProductEnquiry::where('customer_user_id',$user->id)->firstOrFail();
        $this->assertTrue(app(\App\Services\ProductEnquiryService::class)->canView($request,$owned));
        $this->actingAs(User::factory()->create(),'customer')->get($list[0]['url'])->assertNotFound();
    }

    public function test_paid_spend_uses_settlements_less_completed_refunds_and_keeps_currency_groups(): void
    {
        $user=User::factory()->create();
        $customer=Customer::create(['user_id'=>$user->id,'email'=>$user->email]);
        foreach (['processing','cancelled','refunded','pending'] as $index=>$status) {
            $order=Order::create(['number'=>'SPEND-'.$index,'customer_id'=>$customer->id,'email'=>$user->email,'currency'=>'MYR','status'=>$status,'subtotal'=>10000,'total'=>10000]);
            $payment=$order->payments()->create(['gateway'=>'manual','status'=>$index===0?'partially_refunded':($index===3?'pending':'paid'),'amount'=>10000,'currency'=>'MYR']);
            if($index===0) OrderRefund::create(['order_id'=>$order->id,'order_payment_id'=>$payment->id,'user_id'=>$user->id,'amount'=>2500,'reason'=>'Partial','status'=>'completed']);
        }
        $payload=app(OriginalAccountPayloadFactory::class)->for($customer);
        $this->assertSame(7500,$payload['dashboard']['paidSpend'][0]['amount']);
        $this->assertSame(4,$payload['dashboard']['orderCount']);
        $this->assertSame(2,$payload['dashboard']['activeLogisticsCount']);
    }

    public function test_auth_and_account_render_canonical_forms_and_navigation(): void
    {
        view()->share('errors',new \Illuminate\Support\ViewErrorBag);
        foreach(['login','register','forgot','reset'] as $mode) {
            $html=view('storefront.themes.logistics-auth',['mode'=>$mode,'shopBrandName'=>'Test','websiteDesignPreview'=>true,'token'=>'sample-token','email'=>'sample@example.test'])->render();
            $this->assertStringContainsString('data-logistics-auth="'.$mode.'"',$html);
            $this->assertStringContainsString('name="_token"',$html);
        }
        $html=view('storefront.themes.logistics-account',['accountPayload'=>WebsiteThemeAccountPreview::empty(),'shopBrandName'=>'Test','websiteDesignPreview'=>true])->render();
        foreach(['Overview','Orders','Quotations','Addresses','Saved Items','Profile','Vouchers','Sign out','Total Orders','Total Spent','Active Orders','No quotations have been sent'] as $label) $this->assertStringContainsString($label,$html);
        $this->assertStringContainsString('name="current_password"',$html);
    }
    public function test_populated_admin_preview_uses_container_account_data_without_persistence(): void
    {
        view()->share('errors',new \Illuminate\Support\ViewErrorBag);
        $factory=app(\App\Services\WebsiteThemeDemoData::class);
        $catalog=$factory->catalog(\Illuminate\Http\Request::create('/demo','GET',['theme'=>'logistics']));
        $data=app(\App\Services\LogisticsAccountPreview::class)->account($catalog,$factory->account($catalog));
        $html=view('storefront.themes.logistics-account',['accountPayload'=>$data,'shopBrandName'=>'Test','websiteDesignPreview'=>true,'websiteThemeDemo'=>true])->render();
        $this->assertStringContainsString('DEMO-QT-001',$html);
        $this->assertStringContainsString('lg-voucher-lines',$html);
        $this->assertStringContainsString('DEMO-JTG-001',$html);
        $this->assertStringNotContainsString('PANTRY10',$html);
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseCount('product_enquiries',0);
    }

    public function test_logistics_registry_persists_bounded_font_color_and_media_controls(): void
    {
        $service=app(\App\Services\WebsiteDesignService::class);
        $settings=$service->save(['theme'=>'logistics','overrides'=>[],'customization'=>['logistics'=>[
            'style'=>['font'=>'logistics','size'=>'big','colorMode'=>'custom','main'=>'#224466','sub'=>'#CC7744'],
            'media'=>['account'=>['desktop'=>'/images/custom-account.png','mobile'=>'/images/custom-mobile.png','focus'=>'right','focusY'=>'top','hidden'=>false]],
        ]]]);
        $this->assertSame('logistics',$service->live()['theme']);
        $this->assertSame($settings['customization'],$service->live()['customization']);
        $this->assertSame('storefront.themes.logistics-account',$service->view('account',null,'logistics'));
        $this->assertSame('storefront.themes.logistics-auth',$service->view('reset',null,'logistics'));
        $customizer=app(\App\Services\WebsiteThemeCustomization::class);
        $media=$customizer->media('logistics','account','/fallback.png');
        $this->assertSame('/images/custom-account.png',$media['desktop']);
        $this->assertSame('top',$media['focusY']);
        $html=view('storefront.partials.theme-customization')->render();
        $this->assertStringContainsString('Barlow Semi Condensed',$html);
        $this->assertStringContainsString('--theme-main:#224466',$html);
        $this->assertStringContainsString('--theme-sub:#CC7744',$html);
        $schema=json_decode(file_get_contents(resource_path('cms/page-contract.v1.json')),true,512,JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"barlow"',json_encode($schema));
        foreach(['Regular','Medium','SemiBold','Bold','ExtraBold','Black'] as $weight) $this->assertGreaterThan(1000,filesize(public_path('fonts/sample/barlow-semicondensed-'.$weight.'.ttf')));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $customizer->validate(['logistics'=>['style'=>['font'=>'street']]]);
    }

    public function test_admin_guard_maps_quotations_and_uses_logistics_dialog_fixtures(): void
    {
        $html=view('admin.theme-demo-guard',['demoTheme'=>'logistics','scenario'=>'account-quotations'])->render();
        $this->assertStringContainsString("'account-quotations':'quotations'",$html);
        $this->assertStringContainsString('Container Accessories',$html);
        $this->assertStringContainsString('[data-logistics-account]',$html);
        $this->assertStringContainsString('DEMO-ACCESSORY',$html);
        $this->assertStringContainsString('200.00 MYR',$html);
    }

    public function test_every_declared_logistics_and_wedding_media_slot_is_validated_and_round_trips(): void
    {
        $customizer=app(\App\Services\WebsiteThemeCustomization::class);
        foreach(['logistics','wedding'] as $theme){
            $slots=array_unique(array_merge(...array_values($customizer->mediaContracts()[$theme])));
            $media=[];
            foreach($slots as $slot) $media[$slot]=['desktop'=>'/images/'.$slot.'.png','mobile'=>'/images/'.$slot.'-mobile.png','focus'=>'right','focusY'=>'bottom','hidden'=>false];
            app(\App\Services\WebsiteDesignService::class)->save(['theme'=>$theme,'overrides'=>[],'customization'=>[$theme=>['media'=>$media]]]);
            $this->assertSame($media,app(\App\Services\WebsiteDesignService::class)->live()['customization'][$theme]['media']);
            foreach(['checkoutSummary','trackingResult','enquiryReceipt'] as $slot){
                $this->assertSame('/images/'.$slot.'.png',$customizer->media($theme,$slot,'/fallback.png')['desktop']);
                try{$customizer->validate([$theme=>['media'=>[$slot=>['focusY'=>'sideways']]]]);$this->fail('Invalid focus accepted for '.$slot);}catch(\Illuminate\Validation\ValidationException $exception){$this->assertArrayHasKey('profiles.'.$theme.'.media.'.$slot.'.focusY',$exception->errors());}
            }
            $this->assertSame('/images/checkout.png',$customizer->media($theme,'checkout','/fallback.png')['desktop']);
        }
    }

}