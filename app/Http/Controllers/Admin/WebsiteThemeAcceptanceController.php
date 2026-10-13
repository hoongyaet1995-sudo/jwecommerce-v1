<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\{WebsiteDesignService, WebsiteThemeCustomization, WebsiteThemeDemoData};
use Illuminate\Http\Request;

final class WebsiteThemeAcceptanceController extends Controller
{
    public function index(){return view('admin.theme-acceptance',['scenarios'=>WebsiteThemeDemoData::SCENARIOS]);}
    public function reference(string $scenario)
    {
        abort_unless(in_array($scenario,WebsiteThemeDemoData::SCENARIOS,true),404);
        $path=base_path('output/food-beverage-theme-samples-v2/'.$scenario.'.png');
        abort_unless(is_file($path),404);
        return response()->file($path);
    }
    public function sample(Request $request, string $scenario)
    {
        abort_unless(in_array($scenario,WebsiteThemeDemoData::SCENARIOS,true),404);
        $request->merge(['theme'=>'food']);
        $request->attributes->set('websiteDesignPreview','food');
        app(WebsiteThemeCustomization::class)->preview($request);
        $demo=app(WebsiteThemeDemoData::class); $catalog=$demo->catalog($request);
        $common=['websiteDesignPreview'=>true,'websiteThemeDemo'=>true];
        if(in_array($scenario,['plp','filter','quick-buy','pdp','login','register','forgot-password','reset-password'],true)) {
            $request->merge(['surface'=>['filter'=>'plp','quick-buy'=>'plp','forgot-password'=>'forgot','reset-password'=>'reset'][$scenario]??$scenario]);
            $view=app(WebsiteDesignController::class)->preview($request,app(WebsiteDesignService::class));
        }
        elseif(in_array($scenario,['order-tracking','order-tracking-verified'],true)) {
            $payload=['number'=>'DEMO-JW100328','currency'=>'MYR','currencyExchangeRate'=>1,'totals'=>['subtotal'=>6800,'shipping'=>800,'discount'=>0,'tax'=>0,'total'=>7600],'address'=>null,'statusLabel'=>'Delivered','tracking'=>['number'=>'DEMO-12345','carrier'=>'Sample courier'],'timeline'=>[['title'=>'Order confirmed','description'=>'Your pantry favourites are being prepared.','timestamp'=>'2026-10-01T10:00:00','location'=>null],['title'=>'Delivered','description'=>'Your parcel has arrived.','timestamp'=>'2026-10-03T14:00:00','location'=>'Kuala Lumpur']],'items'=>$demo->account($catalog)['orders'][0]['items']];
            $view=view('storefront.themes.food-tracking',$common+['step'=>$scenario==='order-tracking-verified'?'verified_result':'request','trackingPayload'=>$scenario==='order-tracking-verified'?$payload:null]);
        }
        elseif(in_array($scenario,['shopping-cart','cart-drawer','checkout','checkout-success','payment-pending'],true)) {
            if(in_array($scenario,['checkout-success','payment-pending'],true)) $view=view('storefront.themes.food-success',$common+$demo->order($catalog,$scenario==='checkout-success'));
            else $view=view('storefront.themes.food-'.(['shopping-cart'=>'cart','cart-drawer'=>'cart-drawer','checkout'=>'checkout'][$scenario]),$common+$demo->cart($catalog)+['checkoutEmail'=>'sample@example.com','savedAddresses'=>$demo->account($catalog)['addresses'],'hostedReturn'=>null,'selectedDelivery'=>'standard','deliveryOptions'=>['standard'=>['shipping'=>800,'label'=>'Standard delivery','detail'=>'3–5 working days']],'paymentMethods'=>['bank_transfer'=>'Bank transfer','cod'=>'Cash on delivery'],'paymentMethodModels'=>['bank_transfer'=>['label'=>'Bank transfer','bankTransfer'=>['allowProofUpload'=>true,'bankAccounts'=>[['id'=>'demo-bank','isPrimary'=>true,'bankName'=>'Sample bank · Demo only','accountNumber'=>'DEMO-0000','accountHolderName'=>'Sample store','accountHolder'=>'Sample store','bankCode'=>'DEMO','duitNowId'=>null,'duitNowType'=>null,'hasQr'=>false]],'paymentWindowHours'=>24,'quietDisclosure'=>'Demonstration only. Do not transfer money.','verificationInstructions'=>['Sample payment instructions. No payment will be processed.']]], 'cod'=>['label'=>'Cash on delivery']]]);
        }
        else $view=view('storefront.themes.food-account',$common+['accountPayload'=>$demo->account($catalog)]);
        $html=$view->render();
        $guard=view('admin.theme-demo-guard',['scenario'=>$scenario])->render();
        $html=str_replace('</head>',$guard.'</head>',$html);
        if($scenario==='cart-drawer')$html='<html lang="en"><head>'.app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css','resources/js/app.js']).'<meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/css/food-theme.css">'.$guard.'</head><body style="margin:0;background:#ddd8cc"><aside class="fd-theme" style="max-width:460px;margin-left:auto;height:100vh;background:#faf6ec">'.$html.'</aside></body></html>';
        return response($html)->header('Cache-Control','private, no-store')->header('X-Theme-Data','dummy');
    }
}
