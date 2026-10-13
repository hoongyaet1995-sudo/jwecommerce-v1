<?php
namespace App\Services;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Presentation only. Catalog media, shell and commerce producers retain ownership. */
final class WebsiteThemeCustomization
{
    public const SURFACES=['plp','pdp','login','register','forgot','reset','account','tracking'];
    public const FONTS=['default','editorial','modern','classic'];

    /** Only declare slots backed by an approved renderer. Unknown themes expose nothing. */
    public function mediaContracts(): array
    {
        $account=['account'=>['account'],'login'=>['account','login'],'register'=>['account','register'],'forgot'=>['account','forgot'],'reset'=>['account','reset']];
        return ['default'=>$account,'home_deco'=>$account,'beauty'=>$account,'food'=>array_replace($account,['account'=>['accountHero','accountOrders','accountVouchers','accountAddresses','accountProfile'],'accountOverview'=>['accountHero'],'accountOrders'=>['accountHero','accountOrders'],'accountVouchers'=>['accountHero','accountVouchers'],'accountAddresses'=>['accountHero','accountAddresses'],'accountProfile'=>['accountHero','accountProfile'],'tracking'=>['tracking']])];
    }

    public function validate(mixed $input): array
    {
        $rules=['profiles'=>['array:default,home_deco,beauty,food']];
        foreach (WebsiteDesignService::THEMES as $theme) {
            $base='profiles.'.$theme;
            $rules[$base]=['sometimes','array:style,pages,media'];
            $rules[$base.'.pages']=['sometimes','array:'.implode(',',self::SURFACES)];
            $slots=array_values(array_unique(array_merge([], ...array_values($this->mediaContracts()[$theme]??[]))));
            $rules[$base.'.media']=['sometimes', $slots ? 'array:'.implode(',',$slots) : 'prohibited'];
            foreach (array_merge([$base.'.style'],array_map(fn($s)=>$base.'.pages.'.$s,self::SURFACES)) as $path) {
                $rules[$path]=['sometimes','array:font,size,colorMode,paletteId,main,sub'];
                $rules[$path.'.font']=['sometimes',Rule::in(self::FONTS)];
                $rules[$path.'.size']=['sometimes',Rule::in(['small','medium','big'])];
                $rules[$path.'.colorMode']=['sometimes',Rule::in(['theme','brand','custom'])];
                $rules[$path.'.paletteId']=['nullable','uuid'];
                foreach (['main','sub'] as $color) $rules[$path.'.'.$color]=['sometimes','regex:/^#[a-fA-F0-9]{6}$/D'];
            }
            foreach (['account','accountHero','accountOrders','accountVouchers','accountAddresses','accountProfile','tracking','login','register','forgot','reset'] as $page) {
                $path=$base.'.media.'.$page;
                $rules[$path]=['sometimes','array:desktop,mobile,alt,focus,focusY,hidden'];
                foreach (['desktop','mobile'] as $device) $rules[$path.'.'.$device]=['nullable','string','max:1000',function($attribute,$value,$fail){
                    if ($value!=='' && !preg_match('~^(?:/(?!/)[^<>"\x00-\x20]*|https://[^<>"\x00-\x20]+)$~D',$value)) $fail('Use a library image or an HTTPS image URL.');
                }];
                $rules[$path.'.alt']=['nullable','string','max:200'];
                $rules[$path.'.focus']=['sometimes',Rule::in(['left','center','right'])];
                $rules[$path.'.focusY']=['sometimes',Rule::in(['top','middle','bottom'])];
                $rules[$path.'.hidden']=['sometimes','boolean'];
            }
        }
        $data=validator(['profiles'=>$input],$rules)->validate()['profiles'];
        $ids=array_column(app(CmsPaletteLibrary::class)->all(),'id');
        foreach ($data as $profile) foreach (array_merge([$profile['style']??[]],array_values($profile['pages']??[])) as $style) {
            if (($style['colorMode']??null)==='brand' && !in_array($style['paletteId']??null,$ids,true)) throw ValidationException::withMessages(['customization'=>'Choose an existing company brand palette.']);
        }
        return $data;
    }

    public function profiles(): array
    {
        return request()->attributes->get('websiteThemeCustomization') ?? app(WebsiteDesignService::class)->live()['customization'];
    }

    public function page(): string
    {
        if (request()->routeIs('admin.website-design.preview')) return request('surface','plp');
        if (request()->routeIs('customer.*')) {
            $name=request()->route()?->getName()??'';
            if (str_contains($name,'register')) return 'register';
            if (str_contains($name,'login')) return 'login';
            if (str_contains($name,'password.reset')) return 'reset';
            if (str_contains($name,'password')) return 'forgot';
            return 'account';
        }
        if (request()->routeIs('order-tracking.*')) return 'tracking';
        return request()->routeIs('storefront.products.show')?'pdp':'plp';
    }

    public function style(string $theme, ?string $page=null): array
    {
        $profile=$this->profiles()[$theme]??[];
        $style=array_replace($profile['style']??[],$profile['pages'][$page??$this->page()]??[]);
        if (($style['colorMode']??'theme')==='brand') {
            $palette=collect(app(CmsPaletteLibrary::class)->all())->firstWhere('id',$style['paletteId']??'');
            if ($palette) {$style['main']=$palette['primary'];$style['sub']=$palette['secondary']??$palette['colors']['secondary'];}
            else $style['colorMode']='theme';
        }
        return $style;
    }

    public function media(string $theme,string $page,string $fallback): array
    {
        $defaults=['desktop'=>$fallback,'mobile'=>'','alt'=>'','focus'=>'center','focusY'=>'middle','hidden'=>false];
        if (empty($this->mediaContracts()[$theme][$page])) return $defaults;
        $profile=$this->profiles()[$theme]??[];
        $media=[];
        foreach ($this->mediaContracts()[$theme][$page] as $slot) $media=array_replace($media,$profile['media'][$slot]??[]);
        return array_replace(['desktop'=>$fallback,'mobile'=>'','alt'=>'','focus'=>'center','focusY'=>'middle','hidden'=>false],$media,['desktop'=>($media['desktop']??'')?:$fallback]);
    }

    public function preview(\Illuminate\Http\Request $request): void
    {
        if (!$request->filled('customization')) return;
        $raw=$request->input('customization');
        if (!is_string($raw)||strlen($raw)>16000) abort(422,'Invalid customization preview.');
        $decoded=json_decode($raw,true);
        if (!is_array($decoded)) abort(422,'Invalid customization preview.');
        $request->attributes->set('websiteThemeCustomization',$this->validate($decoded));
    }

    public function foreground(string $color): string
    {
        $rgb=array_map(fn($v)=>hexdec($v)/255,str_split(substr($color,1),2));
        $rgb=array_map(fn($v)=>$v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4,$rgb);
        $l=.2126*$rgb[0]+.7152*$rgb[1]+.0722*$rgb[2];
        return ($l+.05)/.05 >= 1.05/($l+.05)?'#000000':'#ffffff';
    }
}
