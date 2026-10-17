<?php
namespace App\Services;

/** Runtime-only brand facts. Never rewrites authored CMS documents. */
final class CmsThemePalette
{
    public function current(): array
    {
        $settings=app(WebsiteDesignService::class)->live();
        $theme=$settings['theme'];
        $style=$settings['customization']['profiles'][$theme]['style']??[];
        [$main,$sub]=match($theme){
            'logistics'=>['#173247','#ef7b32'],'wedding'=>['#465348','#e5e9e1'],
            'universal'=>['#2457e6','#edf2ff'],'fashion'=>['#c6f432','#d9dce0'],
            'jewelry'=>['#92744f','#eee1d8'],default=>['#502b40','#e8d2d5'],
        };
        if(($style['colorMode']??'theme')==='brand'){
            $brand=collect(app(CmsPaletteLibrary::class)->all())->firstWhere('id',$style['paletteId']??'');
            if($brand){$main=$brand['primary'];$sub=$brand['secondary']??$brand['colors']['secondary'];}
        }elseif(($style['colorMode']??'theme')==='custom'){
            $main=$style['main']??$main;$sub=$style['sub']??$sub;
        }
        return ['theme'=>$theme,'main'=>$main,'sub'=>$sub,'palette'=>$this->generate($main,$sub)];
    }
    public function generate(string $main,string $sub): array
    {
        $blend=static function($a,$b,$t){$result='#';for($i=1;$i<7;$i+=2)$result.=str_pad(dechex((int)round(hexdec(substr($a,$i,2))*(1-$t)+hexdec(substr($b,$i,2))*$t)),2,'0',STR_PAD_LEFT);return $result;};
        $luma=static function($h){$n=0;foreach([.2126,.7152,.0722] as $i=>$w){$v=hexdec(substr($h,1+$i*2,2))/255;$n+=($v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4)*$w;}return $n;};
        $contrast=static function($a,$b)use($luma){$a=$luma($a);$b=$luma($b);return(max($a,$b)+.05)/(min($a,$b)+.05);};
        $on=static fn($bg)=>$contrast('#ffffff',$bg)>=$contrast('#151515',$bg)?'#ffffff':'#151515';
        $paper=$blend($main,'#ffffff',.96);$ink=$blend($main,'#000000',.84);$muted=$blend($ink,$paper,.38);
        while($contrast($muted,$paper)<4.5)$muted=$blend($muted,$ink,.12);
        return ['paper'=>$paper,'ink'=>$ink,'muted'=>$muted,'accent'=>$main,'light'=>$on($main),'secondary'=>$sub,'secondaryInk'=>$on($sub),'hover'=>$blend($main,$on($main)==='#ffffff'?'#000000':'#ffffff',.12)];
    }
}
