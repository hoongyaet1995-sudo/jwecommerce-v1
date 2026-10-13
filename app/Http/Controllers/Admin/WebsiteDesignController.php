<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\{Controller,StorefrontController};
use App\Models\{Category,Collection};
use App\Services\WebsiteDesignService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
final class WebsiteDesignController extends Controller
{
    public function show(WebsiteDesignService $design){return response()->json($design->inventory());}
    public function update(Request $request,WebsiteDesignService $design){$design->save($request->all());return $this->show($design);}
    public function drawer(Request $request, \App\Http\Controllers\CartController $cart)
    {
        $validator=validator($request->all(),['theme'=>['required',Rule::in(WebsiteDesignService::THEMES)]]);
        abort_if($validator->fails(),422,'Invalid preview theme.');
        app(\App\Services\WebsiteThemeCustomization::class)->preview($request);
        $request->attributes->set('websiteDesignPreview',$validator->validated()['theme']);
        $demo=app(\App\Services\WebsiteThemeDemoData::class);
        return view('storefront.partials.cart-drawer',$demo->cart($demo->catalog($request)));
    }
    public function preview(Request $request,WebsiteDesignService $design)
    {
        $validator=validator($request->all(),['theme'=>['required',Rule::in(WebsiteDesignService::THEMES)],'scope'=>['nullable','string','max:80'],'surface'=>['nullable',Rule::in(['allProducts','plp','pdp','login','register','forgot','reset','account','tracking','checkout','checkoutSuccess'])],'handle'=>['nullable','string','max:255']]);
        abort_if($validator->fails(),422,'Invalid preview theme or scope.');$data=$validator->validated();
        $scope=$data['scope']??'shop';app(\App\Services\WebsiteThemeCustomization::class)->preview($request);
        $request->attributes->set('websiteDesignPreview',$data['theme']);
        $surface=$data['surface']??'plp';
        $fixture=app(\App\Services\WebsiteThemeDemoData::class)->catalog($request);
        if($surface==='allProducts'){
            $request->attributes->set('websiteThemeSurface','allProducts');
            $fixture=array_replace($fixture,['listingCategory'=>null,'collection'=>null,'catalogListingSettings'=>['title'=>'All products','description'=>''],'listingRouteParameters'=>['theme'=>$data['theme'],'surface'=>'allProducts']]);
            return view($design->view('listing',null,$data['theme']),$fixture+['websiteDesignPreview'=>true]);
        }
        if (in_array($surface,['plp','pdp'],true)) {
            if ($surface==='plp') return view($design->view('listing',null,$data['theme']),$fixture+['websiteDesignPreview'=>true]);
            $product=$fixture['demoProducts']->first();
            if($data['theme']==='food')$product->images->first()->path=asset('images/themes/food/olive-oil-lifestyle.png');
            return view($design->view('pdp',null,$data['theme']),[
                'product'=>$product,'selectedVariant'=>$product->variants->first(),'selectedImage'=>$product->images->first(),
                'images'=>$product->images,'pdpBrand'=>'JW STUDIO','pdpPaymentMethods'=>[],
                'companions'=>$fixture['demoProducts']->slice(1,3),'websiteDesignPreview'=>true,
            ]);
        }
        if (in_array($surface,['login','register','forgot','reset'],true)) return view(app(\App\Services\WebsiteDesignService::class)->view($surface==='reset'?'reset':'auth',null,$data['theme']),['mode'=>$surface,'token'=>'design-preview-only','email'=>'','websiteDesignPreview'=>true]);
        if ($surface==='checkoutSuccess') { abort_unless(in_array($data['theme'],['food','home_deco','beauty'],true),422); return app(WebsiteThemeAcceptanceController::class)->sample($request,'checkout-success'); }
        if ($surface==='checkout') { abort_unless(in_array($data['theme'],['food','home_deco','beauty'],true),422); return app(WebsiteThemeAcceptanceController::class)->sample($request,'checkout'); }
        if ($surface==='tracking') { abort_unless(in_array($data['theme'],['food','home_deco','beauty'],true),422); return view('storefront.themes.'.match($data['theme']){'home_deco'=>'home-deco','beauty'=>'beauty',default=>'food'}.'-tracking',['step'=>'request','trackingPayload'=>null,'websiteDesignPreview'=>true]); }
        if ($surface==='account') return view(app(\App\Services\WebsiteDesignService::class)->view('account',null,$data['theme']),['accountPayload'=>app(\App\Services\WebsiteThemeDemoData::class)->account(app(\App\Services\WebsiteThemeDemoData::class)->catalog($request)),'websiteDesignPreview'=>true]);
        abort(422, 'Unsupported demo surface.');
    }
}
