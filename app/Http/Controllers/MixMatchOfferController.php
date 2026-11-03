<?php
namespace App\Http\Controllers;
use App\Models\{Category,Collection};
use App\Services\{CatalogFilterService,CatalogListingSettingsService,WebsiteDesignService};
use App\Services\Promotions\StorefrontOfferService;
use Illuminate\Http\Request;
class MixMatchOfferController extends Controller {
 public function __invoke(Request $request,int $offer,StorefrontOfferService $offers){
  $offer=$offers->active()->findOrFail($offer);
  $query=$offer->products()->where('status','active')->where('purchase_mode','buy');
  $data=app(CatalogFilterService::class)->for($request,$query, null,20)->toViewData();
  // Presentation metadata only: reuse the existing collection PLP without creating a catalog collection.
  $collection=new Collection(['title'=>$offer->name,'subtitle'=>'Pick any '.$offer->quantity.' participating items for '.app(\App\Support\StorefrontCurrencyContext::class)->format($offer->price).'. '.($offer->description??''),'description'=>$offer->description,'badge'=>'Mix & Match','cover_image'=>$offer->cover_image,'image_settings'=>$offer->image_settings??[]]);
  $theme=app(WebsiteDesignService::class)->resolve();
  return view(app(WebsiteDesignService::class)->view('listing',null,$theme),$data+[
   'offer'=>$offer,'collection'=>$collection,'listingCategory'=>null,
   'categories'=>Category::where('is_active',true)->get(),
   'catalogListingSettings'=>app(CatalogListingSettingsService::class)->live(),
   'listingRouteName'=>'storefront.offers.mix-match','listingRouteParameters'=>['offer'=>$offer->id],
   'websiteDesignTheme'=>$theme,
  ]);
 }
}
