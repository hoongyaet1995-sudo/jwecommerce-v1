<?php
namespace App\Services;
use App\Models\{StoreSetting,Category,Collection};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class WebsiteDesignService
{
    public const THEMES=['default','home_deco'];
    public function live():array
    {
        $value=StoreSetting::where('key','website_design')->value('value');
        $data=is_array($value)?($value['data']??[]):[];
        $data=is_array($data)?$data:[];
        return ['theme'=>in_array($data['theme']??null,self::THEMES,true)?$data['theme']:'default','overrides'=>is_array($data['overrides']??null)?$data['overrides']:[]];
    }
    public function save(array $input):array
    {
        $data=validator($input,['theme'=>['required',Rule::in(self::THEMES)],'overrides'=>['present','array','max:500'],'overrides.*'=>['required',Rule::in(self::THEMES)]])->validate();
        foreach($data['overrides'] as $scope=>$theme){
            if(!$this->exists($scope))throw ValidationException::withMessages(['overrides'=>'Choose an existing category or collection.']);
        }
        StoreSetting::updateOrCreate(['key'=>'website_design'],['value'=>['data'=>$data]]);
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
    public function exists(string $scope):bool
    {
        if(!preg_match('/^(category|collection):([1-9][0-9]*)$/D',$scope,$matches))return false;
        return ($matches[1]==='category'?Category::query():Collection::query())->whereKey($matches[2])->exists();
    }
    public function inventory():array
    {
        return ['settings'=>$this->live(),'pages'=>array_merge(
            [['scope'=>'shop','name'=>'All products','url'=>route('storefront.index')]],
            Category::where('is_active',true)->orderBy('name')->get()->map(fn($c)=>['scope'=>'category:'.$c->id,'name'=>'Category · '.$c->name,'url'=>route('storefront.categories.show',$c)])->all(),
            Collection::where('is_active',true)->orderBy('title')->get()->map(fn($c)=>['scope'=>'collection:'.$c->id,'name'=>'Collection · '.$c->title,'url'=>route('storefront.collections.show',$c)])->all()
        )];
    }
}
