<?php
namespace App\Services;
use App\Models\{StoreSetting,Category,Collection};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class WebsiteDesignService
{
    public const THEMES=['default','home_deco','beauty','food','tech','baby','jewelry','fashion','universal'];
    public function live():array
    {
        $value=StoreSetting::where('key','website_design')->value('value');
        $data=is_array($value)?($value['data']??[]):[];
        $data=is_array($data)?$data:[];
        return ['theme'=>in_array($data['theme']??null,self::THEMES,true)?$data['theme']:'default','overrides'=>is_array($data['overrides']??null)?$data['overrides']:[], 'customization'=>is_array($data['customization']??null)?$data['customization']:[]];
    }
    public function save(array $input):array
    {
        $data=validator($input,['theme'=>['required',Rule::in(self::THEMES)],'overrides'=>['present','array','max:500'],'overrides.*'=>['required',Rule::in(self::THEMES)]])->validate();
        foreach($data['overrides'] as $scope=>$theme){
            if(!$this->exists($scope))throw ValidationException::withMessages(['overrides'=>'Choose an existing category or collection.']);
        }
        $data['customization']=app(WebsiteThemeCustomization::class)->validate($input['customization']??$this->live()['customization']);
        \Illuminate\Support\Facades\DB::transaction(function() use($data){
            $setting=StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>$data]]);
            app(\App\Domain\Catalog\CatalogMediaReferenceSynchronizer::class)->syncWebsiteTheme($setting);
        });
        return $data;
    }
    public function resolve(?string $scope=null):string
    {
        $settings=$this->live();$theme=$scope?($settings['overrides'][$scope]??$settings['theme']):$settings['theme'];
        return in_array($theme,self::THEMES,true)?$theme:'default';
    }
    public function surface(?string $scope=null): string
    {
        $preview=request()->attributes->get('websiteDesignPreview');
        return in_array($preview,self::THEMES,true)?$preview:$this->resolve($scope);
    }
    public function view(string $surface, ?string $scope=null, ?string $theme=null): string
    {
        $theme=$theme ?? $this->surface($scope);
        request()->attributes->set('websiteRenderedTheme',$theme);
        $default=['listing'=>'storefront.index','pdp'=>'storefront.show','auth'=>'storefront.auth','reset'=>'storefront.password-reset','account'=>'storefront.account'];
        if (!array_key_exists($surface,$default)) throw new \InvalidArgumentException('Unsupported theme surface.');
        if (!in_array($theme,['home_deco','beauty','food','tech','baby','jewelry','fashion','universal'],true)) return $default[$surface];
        return 'storefront.themes.'.(['universal'=>'universal','fashion'=>'fashion','jewelry'=>'jewelry','baby'=>'baby','tech'=>'tech','beauty'=>'beauty','food'=>'food','home_deco'=>'home-deco'][$theme]).'-'.($surface==='reset'?'auth':$surface);
    }
    public function exists(string $scope):bool
    {
        if(!preg_match('/^(category|collection):([1-9][0-9]*)$/D',$scope,$matches))return false;
        return ($matches[1]==='category'?Category::query():Collection::query())->whereKey($matches[2])->exists();
    }
    public function inventory():array
    {
        return ['contentContracts'=>app(WebsiteThemeCustomization::class)->contentContracts(),'mediaContracts'=>app(WebsiteThemeCustomization::class)->mediaContracts(),'settings'=>$this->live(),'palettes'=>app(CmsPaletteLibrary::class)->all(),'media'=>\App\Models\CmsMediaAsset::where('mime_type','like','image/%')->latest()->limit(60)->get(['id','url','original_name'])->toArray(),'pages'=>array_merge(
            [['scope'=>'shop','name'=>'All products','url'=>route('storefront.index')]],
            Category::where('is_active',true)->orderBy('name')->get()->map(fn($c)=>['scope'=>'category:'.$c->id,'name'=>'Category · '.$c->name,'url'=>route('storefront.categories.show',$c)])->all(),
            Collection::where('is_active',true)->orderBy('title')->get()->map(fn($c)=>['scope'=>'collection:'.$c->id,'name'=>'Collection · '.$c->title,'url'=>route('storefront.collections.show',$c)])->all()
        )];
    }
}
