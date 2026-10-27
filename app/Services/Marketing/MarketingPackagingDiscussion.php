<?php
namespace App\Services\Marketing;
use App\Models\{MarketingProject,MarketingVideoFilm};
class MarketingPackagingDiscussion {
 public function requested(string $text):bool{return (bool)preg_match('/logo|company\s*name|品牌|公司名|片尾|配乐|音乐|背景音|music|outro/i',$text);}
 public function context(MarketingProject $p,MarketingVideoFilm $f):array {
  $a=app(MarketingPackagingAssets::class);try{$brand=$a->brand($p);$brand=['name'=>$brand['name'],'sha256'=>$brand['sha256'],'available'=>true];}catch(\Throwable){$brand=['available'=>false,'name'=>app(\App\Support\ShopBrand::class)->name()];}
  return ['brand'=>$brand,'tracks'=>$a->catalogue($p),'product_background_available'=>(bool)$a->product($p),'current_settings'=>$f->input['settings']??null,'current_film_packaged'=>($f->input['version']??'')===MarketingFilmPackaging::VERSION,'content_film_id'=>app(MarketingFilmPackaging::class)->content($f)->id,'scope'=>'Choose preset minimal/cta/product end card2..5seconds; existing authorized audio0..100percent, actual-audio ducking and fades. Never new voice/sound effects. User-facing replies use track display names and UI terms, not internal IDs, hashes or field names. Discussion only; user reviews settings in modal then adopts.' ];
 }
 public function suggestion(MarketingProject $p,MarketingVideoFilm $f,array $candidate):array {
  $assets=app(MarketingPackagingAssets::class);try{$brand=$assets->brand($p);}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){$brand=['name'=>app(\App\Support\ShopBrand::class)->name(),'sha256'=>null];}$settings=$f->input['settings']??['enabled'=>(bool)$brand['sha256'],'template'=>'minimal','duration'=>3,'company'=>$brand['name'],'cta'=>$f->input['cta'],'track'=>null,'volume'=>18,'duck'=>true,'fade'=>true,'logo_sha256'=>$brand['sha256']];
  $allowed=['enabled','template','duration','company','cta','track','volume','duck','fade'];abort_if(array_diff(array_keys($candidate),$allowed),422,'未知的包装建议字段。');$settings=app(MarketingFilmPackaging::class)->settings(array_replace($settings,$candidate));
  abort_if($settings['enabled']&&!$brand['sha256'],422,'Store logo is required for a brand outro.');abort_unless($settings['company']===$brand['name'],422,'品牌名称请使用店铺已保存名称，或由用户在设置中明确修改。');if($settings['track'])app(MarketingPackagingAssets::class)->audio($p,$settings['track']);if($settings['enabled']&&$settings['template']==='product')abort_unless(app(MarketingPackagingAssets::class)->product($p),422);
  return ['base_film_id'=>$f->id,'base_sha256'=>$f->output_sha256,'settings'=>$settings];
 }
}
