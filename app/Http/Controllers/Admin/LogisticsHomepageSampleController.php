<?php
namespace App\Http\Controllers\Admin;

use App\Domain\Cms\{PageDocumentContract,RuntimeAdapterRegistry,ShellDesign};
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ViteManifestEntry;
use Illuminate\Http\Request;

/** Read-only protected acceptance surface: no Page or catalog record is created. */
final class LogisticsHomepageSampleController extends Controller
{
 public function __invoke(Request $request)
 {
  $width=$request->query('width','standard');abort_unless(in_array($width,['standard','wide','full','site'],true),422);
  // The normal currency reader initializes missing installation defaults. This
  // protected acceptance request must remain read-only even on an empty DB.
  app()->instance(\App\Support\CurrencySettings::class,new class extends \App\Support\CurrencySettings {
   public function all(): array {
    $stored=data_get(\App\Models\StoreSetting::query()->where('key',self::KEY)->value('value'),'data');
    return $this->normalize(is_array($stored)&&$stored!==[]?$stored:self::defaults());
   }
  });
  $request->attributes->set('websiteDesignPreview','logistics');
  $request->attributes->set('websiteRenderedTheme','logistics');
  $request->attributes->set('websiteThemeShellWidth',$width==='standard'?'standard':'wide');
  $snapshot=json_decode(file_get_contents(resource_path('admin/src/cms/logisticsTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $ids=Product::query()->where('status','active')->where(fn($query)=>$query->where('title','like','%container%')->orWhere('title','like','%logistics%')->orWhere('title','like','%relocation%')->orWhereHas('category',fn($category)=>$category->where('name','like','%container%')->orWhere('name','like','%logistics%')))->orderBy('id')->limit(3)->pluck('id')->map(fn($id)=>(string)$id)->all();
  foreach($snapshot['sections'] as &$section){if($section['type']==='product_listing')$section['productIds']=$ids;}unset($section);
  $document=app(PageDocumentContract::class)->validate(['title'=>'Logistics & Container Services — Sample','slug'=>'logistics-homepage-sample','template'=>'standard','containerWidth'=>$width,'style'=>$snapshot['style'],'sections'=>$snapshot['sections']]);
  $facts=app(RuntimeAdapterRegistry::class)->factsFor($document);
  $payload=['page'=>['id'=>'logistics-homepage-sample','status'=>'draft','stage'=>'draft','schemaVersion'=>1,'lockVersion'=>1,'contentHash'=>hash('sha256',json_encode($document)),'document'=>$document],'catalog'=>$facts['catalog'],'forms'=>['contact'=>new \stdClass,'newsletter'=>new \stdClass],'links'=>new \stdClass,'runtime'=>['sections'=>$facts['sections'],'errors'=>new \stdClass],'context'=>['mode'=>'preview','currency'=>(string)app(\App\Support\CurrencySettings::class)->default()['code']]];
  $entry=app(ViteManifestEntry::class)->resolve('src/public-page.tsx');
  return response()->view('storefront.cms-page-react',['entry'=>$entry,'payload'=>$payload,'title'=>$document['title'],'description'=>'Illustrative editable CMS starter. Catalog cards use current active container catalog only.','quickAddProducts'=>[],'previewShellDesign'=>array_replace(ShellDesign::defaults('service'),['appearance'=>'light','ctaLabel'=>'Request a Quote','ctaUrl'=>'#storefront-support-widget']),'shellContentWidth'=>$width==='standard'?'standard':'wide'], $entry===null?503:200)->header('Cache-Control','private, no-store')->header('X-CMS-Sample','logistics-containers');
 }
}
