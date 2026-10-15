<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{WebsiteDesignService, WebsiteThemeCustomization, WebsiteThemeDemoData};
use Illuminate\Http\Request;

final class WebsiteThemeAcceptanceController extends Controller
{
    public function index(Request $request){$theme=$this->theme($request);return view('admin.theme-acceptance',['theme'=>$theme,'themeName'=>match($theme){'home_deco'=>'Home & Deco','fashion'=>'Fashion & Design','jewelry'=>'Jewelry & Gifts','baby'=>'Baby & Kids','tech'=>'Technology & Electronics','beauty'=>'Beauty & Skincare',default=>'Food & Beverage'},'scenarios'=>array_merge(WebsiteThemeDemoData::SCENARIOS,in_array($theme,['home_deco','beauty','tech','baby','jewelry','fashion'],true)?['order-tracking-verify-code']:[])]);}
    private function theme(Request $request):string { $theme=$request->input('theme','food'); abort_unless(in_array($theme,['food','home_deco','beauty','tech','baby','jewelry','fashion'],true),422); return $theme; }
    public function reference(Request $request,string $scenario)
    {
        abort_unless(in_array($scenario,array_merge(WebsiteThemeDemoData::SCENARIOS,['order-tracking-verify-code']),true),404);
        $theme=$this->theme($request);
        if($theme==='fashion'){
 $file=match($scenario){'all-products','plp'=>'plp-v1','shopping-cart'=>'cart-v1','my-account'=>'account-overview-v1','account-saved-items'=>'account-saved-v1','order-tracking'=>'tracking-lookup-v1','order-tracking-verify-code'=>'tracking-verify-v1','order-tracking-verified'=>'tracking-result-v1','review-dialog'=>'review-v1','return-dialog'=>'return-v1','forgot-password'=>'forgot-v1','reset-password'=>'reset-v1','payment-pending'=>'checkout-pending-v1',default=>$scenario.'-v1'};
 $path=base_path('output/fashion-design-theme-samples/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
 }
 if($theme==='jewelry'){
          $file=match($scenario){'all-products','plp'=>'plp-v1','my-account'=>'account-overview-v1','account-orders'=>'account-orders-v1','account-addresses'=>'account-addresses-v1','account-profile'=>'account-profile-v1','account-vouchers'=>'account-vouchers-v1','account-saved-items'=>'account-saved-v1','order-tracking'=>'tracking-lookup-v1','order-tracking-verify-code'=>'tracking-verify-v1','order-tracking-verified'=>'tracking-result-v1','review-dialog'=>'review-v1','return-dialog'=>'return-v1','checkout-success'=>'success-v2',default=>$scenario.'-v1'};
          $path=base_path('output/jewelry-gifts-theme-samples/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
        }
        if($theme==='baby'){
          $file=match($scenario){'all-products','plp'=>'plp-v1','my-account'=>'account-overview-v1','account-orders'=>'account-orders-v1','account-addresses'=>'account-addresses-v1','account-profile'=>'account-profile-v1','account-vouchers'=>'account-vouchers-v1','account-saved-items'=>'account-saved-v1','order-tracking'=>'tracking-lookup-v1','order-tracking-verify-code'=>'tracking-verify-v1','order-tracking-verified'=>'tracking-result-v1','review-dialog'=>'review-dialog-v2','return-dialog'=>'return-dialog-v2','login'=>'login-v2',default=>$scenario.'-v1'};
          $path=base_path('output/baby-kids-theme-samples/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
        }
        if($theme==='tech'){
 $file=match($scenario){'all-products','plp'=>'plp','my-account'=>'account-overview-v4','account-vouchers'=>'vouchers','account-saved-items'=>'account-saved','order-tracking'=>'tracking-lookup','order-tracking-verify-code'=>'tracking-verify','order-tracking-verified'=>'tracking-result',default=>$scenario};
 $path=base_path('output/technology-theme-samples/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
 }
 if($theme==='tech'){
 $file=match($scenario){'all-products','plp'=>'plp','my-account'=>'account-overview-v4','account-vouchers'=>'vouchers','account-saved-items'=>'account-saved','order-tracking'=>'tracking-lookup','order-tracking-verify-code'=>'tracking-verify','order-tracking-verified'=>'tracking-result',default=>$scenario};
 $path=base_path('output/technology-theme-samples/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
 }
 if($theme==='beauty'){
          $file=match($scenario){'my-account'=>'account-overview','account-orders'=>'account-orders-editorial-v2',default=>$scenario};
          $old=['plp'=>'beauty-skincare-catalog-pdp-concept','pdp'=>'beauty-skincare-catalog-pdp-concept','login'=>'beauty-skincare-auth-account-concept','register'=>'beauty-skincare-auth-account-concept','forgot-password'=>'beauty-skincare-auth-account-concept','cart-drawer'=>'beauty-skincare-overlays-concept','filter'=>'beauty-skincare-overlays-concept'];
          $path=base_path(isset($old[$scenario])?'output/industry-template-concepts/'.$old[$scenario].'.png':'output/beauty-theme-samples-v2/'.$file.'.png');abort_unless(is_file($path),404);return response()->file($path);
        }
        if($theme==='home_deco'){
          $old=['pdp'=>'output/industry-template-concepts/home-deco-pdp-editorial-concept.png','login'=>'output/industry-template-concepts/home-deco-login-concept.png','register'=>'output/industry-template-concepts/home-deco-register-concept.png','forgot-password'=>'output/industry-template-concepts/home-deco-forgot-password-concept.png','cart-drawer'=>'output/industry-template-concepts/home-deco-cart-drawer-concept.png','filter'=>'output/home-deco-filter-desktop.png'];
          $file=$scenario==='checkout-success'?'checkout-success-editorial-v2':$scenario;
          $path=base_path($old[$scenario]??('output/home-deco-theme-samples-v2/'.$file.'.png'));
          abort_unless(is_file($path),404);return response()->file($path);
        }
        $path=base_path('output/food-beverage-theme-samples-v2/'.$scenario.'.png');
        abort_unless(is_file($path),404);
        return response()->file($path);
    }
    public function sample(Request $request, string $scenario)
    {
        $width=$request->validate(['width'=>['sometimes',\Illuminate\Validation\Rule::in(['standard','wide','full'])]])['width']??null;
        if($width)$request->attributes->set('websiteThemeShellWidth',$width);
        $theme=$this->theme($request);$prefix=match($theme){'home_deco'=>'home-deco','beauty'=>'beauty','fashion'=>'fashion','jewelry'=>'jewelry','baby'=>'baby','tech'=>'tech',default=>'food'};
        abort_unless(in_array($scenario,array_merge(WebsiteThemeDemoData::SCENARIOS,in_array($theme,['home_deco','beauty','tech','baby','jewelry','fashion'],true)?['order-tracking-verify-code']:[]),true),404);
        $request->merge(['theme'=>$theme]);
        $request->attributes->set('websiteDesignPreview',$theme);
        $request->attributes->set('websiteThemeSurface',match($scenario){'shopping-cart'=>'cart','shopping-cart'=>'cart','all-products'=>'allProducts','checkout'=>'checkout','checkout-success','payment-pending'=>'checkoutSuccess','order-tracking','order-tracking-verified','order-tracking-verify-code'=>'tracking','login'=>'login','register'=>'register','forgot-password'=>'forgot','reset-password'=>'reset','pdp'=>'pdp','plp','filter','quick-buy'=>'plp',default=>'account'});
        app(WebsiteThemeCustomization::class)->preview($request);
        $demo=app(WebsiteThemeDemoData::class); $catalog=$demo->catalog($request);
        $common=['websiteDesignPreview'=>true,'websiteThemeDemo'=>true];
        if(in_array($scenario,['all-products','plp','filter','quick-buy','pdp','login','register','forgot-password','reset-password'],true)) {
            $request->merge(['surface'=>['all-products'=>'allProducts','filter'=>'plp','quick-buy'=>'plp','forgot-password'=>'forgot','reset-password'=>'reset'][$scenario]??$scenario]);
            $view=app(WebsiteDesignController::class)->preview($request,app(WebsiteDesignService::class));
        }
        elseif(in_array($scenario,['order-tracking','order-tracking-verified','order-tracking-verify-code'],true)) {
            $payload=['number'=>'DEMO-JW100328','currency'=>'MYR','currencyExchangeRate'=>1,'totals'=>['subtotal'=>6800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>7600],'address'=>null,'statusLabel'=>'Delivered','tracking'=>['number'=>'DEMO-12345','carrier'=>'Sample courier'],'timeline'=>[['title'=>'Order confirmed','description'=>'Your pantry favourites are being prepared.','timestamp'=>'2026-10-01T10:00:00','location'=>null],['title'=>'Delivered','description'=>'Your parcel has arrived.','timestamp'=>'2026-10-03T14:00:00','location'=>'Kuala Lumpur']],'items'=>$demo->account($catalog)['orders'][0]['items']];
            if($theme==='home_deco')$payload=array_replace($payload,['statusLabel'=>'Processing','totals'=>['subtotal'=>164800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>165600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order confirmed','description'=>'Your order has been received and is being processed.','timestamp'=>'2026-10-13T10:00:00','location'=>null]]]);
            if($theme==='tech')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>139800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>140600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order created','description'=>'Your order has been received.','timestamp'=>'2026-10-13T10:00:00','location'=>null],['title'=>'Payment confirmed','description'=>'Payment received.','timestamp'=>'2026-10-13T10:01:00','location'=>null],['title'=>'Processing','description'=>'Your order is being processed.','timestamp'=>'2026-10-13T10:02:00','location'=>null]]]);
 if($theme==='tech')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>139800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>140600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order created','description'=>'Your order has been received.','timestamp'=>'2026-10-13T10:00:00','location'=>null],['title'=>'Payment confirmed','description'=>'Payment received.','timestamp'=>'2026-10-13T10:01:00','location'=>null]]]);
            if($theme==='jewelry')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>31800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>32600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order created','description'=>'Your order has been received.','timestamp'=>'2026-10-14T10:24:00','location'=>null],['title'=>'Payment confirmed','description'=>'Your payment has been confirmed.','timestamp'=>'2026-10-14T10:25:00','location'=>null],['title'=>'Processing','description'=>'Your order is being prepared.','timestamp'=>'2026-10-14T10:45:00','location'=>null]]]);
            if($theme==='baby')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>12800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>13600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order created','description'=>'Your order has been received.','timestamp'=>'2026-10-13T10:00:00','location'=>null],['title'=>'Payment confirmed','description'=>'Your payment has been received.','timestamp'=>'2026-10-13T10:01:00','location'=>null],['title'=>'Processing','description'=>'We are preparing your order.','timestamp'=>'2026-10-13T10:02:00','location'=>null]]]);
 if($theme==='beauty')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>22600,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>23400],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order confirmed','description'=>'Your order has been received and is being processed.','timestamp'=>'2026-10-13T10:00:00','location'=>null]]]);
            if($theme==='fashion')$payload=array_replace($payload,['number'=>'JW100328','statusLabel'=>'Processing','totals'=>['subtotal'=>33800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>34600],'address'=>['line1'=>'12 Jalan Harmoni','city'=>'Kuala Lumpur','postalCode'=>'50000'],'tracking'=>['number'=>null,'carrier'=>null],'timeline'=>[['title'=>'Order created','description'=>'Your order has been received.','timestamp'=>'2026-10-15T10:00:00','location'=>null],['title'=>'Payment confirmed','description'=>'Payment received.','timestamp'=>'2026-10-15T10:01:00','location'=>null],['title'=>'Processing','description'=>'Your order is being prepared.','timestamp'=>'2026-10-15T10:02:00','location'=>null]]]);
            $view=view('storefront.themes.'.$prefix.'-tracking',$common+['step'=>$scenario==='order-tracking-verified'?'verified_result':($scenario==='order-tracking-verify-code'?'verify':'request'),'trackingPayload'=>$scenario==='order-tracking-verified'?$payload:null]);
        }
        elseif(in_array($scenario,['shopping-cart','cart-drawer','checkout','checkout-success','payment-pending'],true)) {
            if(in_array($scenario,['checkout-success','payment-pending'],true)) $view=view('storefront.themes.'.$prefix.'-success',$common+$demo->order($catalog,$scenario==='checkout-success'));
            else $view=view('storefront.themes.'.$prefix.'-'.(['shopping-cart'=>'cart','cart-drawer'=>'cart-drawer','checkout'=>'checkout'][$scenario]),$common+$demo->cart($catalog)+['checkoutEmail'=>'sample@example.com','savedAddresses'=>$demo->account($catalog)['addresses'],'hostedReturn'=>null,'selectedDelivery'=>'standard','deliveryOptions'=>['standard'=>['shipping'=>800,'label'=>'Standard delivery','detail'=>$theme==='home_deco'?'':'3–5 working days']],'paymentMethods'=>['bank_transfer'=>'Bank transfer','cod'=>'Cash on delivery'],'paymentMethodModels'=>['bank_transfer'=>['label'=>'Bank transfer','bankTransfer'=>['allowProofUpload'=>true,'bankAccounts'=>[['id'=>'demo-bank','isPrimary'=>true,'bankName'=>'Sample bank · Demo only','accountNumber'=>'DEMO-0000','accountHolderName'=>'Sample store','accountHolder'=>'Sample store','bankCode'=>'DEMO','duitNowId'=>null,'duitNowType'=>null,'hasQr'=>false]],'paymentWindowHours'=>24,'quietDisclosure'=>'Demonstration only. Do not transfer money.','verificationInstructions'=>['Sample payment instructions. No payment will be processed.']]], 'cod'=>['label'=>'Cash on delivery']]]);
        }
        else $view=view('storefront.themes.'.$prefix.'-account',$common+['accountPayload'=>$demo->account($catalog)]);
        $html=$view->render();
        $guard=view('admin.theme-demo-guard',['scenario'=>$scenario,'demoTheme'=>$theme])->render();
        $html=str_replace('</head>',$guard.'</head>',$html);
        if($scenario==='cart-drawer')$html='<html lang="en"><head>'.app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css','resources/js/app.js']).'<meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/css/'.$prefix.'-theme.css">'.($theme==='beauty'?'<link rel="stylesheet" href="/css/beauty-complete.css">':'').$guard.'</head><body style="margin:0;background:#ddd8cc"><aside class="'.match($theme){'home_deco'=>'hd-theme','beauty'=>'bs-theme','jewelry'=>'jg-theme','baby'=>'bk-theme','tech'=>'te-theme',default=>'fd-theme'}.'" style="max-width:460px;margin-left:auto;height:100vh;background:#faf6ec">'.$html.'</aside></body></html>';
        if($scenario==='cart-drawer')$html=str_replace('</head>','<link rel="stylesheet" href="/css/cart-drawer-layout.css">'.'</head>',$html);
        if($theme==='jewelry'&&$scenario==='cart-drawer')$html=str_replace('</head>',view('storefront.partials.theme-customization')->render().'</head>',$html);
        if($theme==='fashion'&&$scenario==='cart-drawer')$html=str_replace('</head>','<link rel="stylesheet" href="/css/storefront-layout.css"><link rel="stylesheet" href="/css/fashion-layout.css"><style>body>aside{max-width:none!important;width:100%;height:min(660px,88dvh)!important;margin-top:max(12dvh,calc(100dvh - 660px));background:#fafafa!important}</style>'.view('storefront.partials.theme-customization')->render().'</head>',$html);
        return response($html)->header('Cache-Control','private, no-store')->header('X-Theme-Data','dummy');
    }
}
