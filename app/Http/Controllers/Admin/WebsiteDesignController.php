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
        $request->attributes->set('websiteDesignPreview',$validator->validated()['theme']);
        return $cart->drawer($request,app(\App\Services\CartViewData::class));
    }
    public function preview(Request $request,WebsiteDesignService $design,StorefrontController $storefront)
    {
        $validator=validator($request->all(),['theme'=>['required',Rule::in(WebsiteDesignService::THEMES)],'scope'=>['nullable','string','max:80'],'surface'=>['nullable',Rule::in(['plp','pdp','login','register','forgot','account'])],'handle'=>['nullable','string','max:255']]);
        abort_if($validator->fails(),422,'Invalid preview theme or scope.');$data=$validator->validated();
        $scope=$data['scope']??'shop';$request->attributes->set('websiteDesignPreview',$data['theme']);
        $surface=$data['surface']??'plp';
        if ($surface==='pdp') {
            $product=\App\Models\Product::where('status','active')->when($data['handle']??null,fn($q,$handle)=>$q->where('handle',$handle))->orderBy('id')->firstOrFail();
            return $storefront->show($request,$product->handle)->with('websiteDesignPreview',true);
        }
        if (in_array($surface,['login','register','forgot'],true)) return view($data['theme']==='home_deco'?'storefront.themes.home-deco-auth':'storefront.auth',['mode'=>$surface,'websiteDesignPreview'=>true]);
        if ($surface==='account') return view($data['theme']==='home_deco'?'storefront.themes.home-deco-account':'storefront.account',['accountPayload'=>\App\Services\WebsiteThemeAccountPreview::empty(),'websiteDesignPreview'=>true]);
        if($scope==='shop')$view=$storefront->index($request);
        else{abort_unless($design->exists($scope),404);[$kind,$id]=explode(':',$scope);$view=$kind==='category'?$storefront->category($request,Category::findOrFail($id)):$storefront->collection($request,Collection::findOrFail($id));}
        return $view->with(['listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>$data['theme'],'scope'=>$scope],'websiteDesignPreview'=>true]);
    }
}
