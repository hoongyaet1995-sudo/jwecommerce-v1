<?php
namespace App\Services;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class CmsPaletteLibrary
{
    public function all(): array {return data_get(StoreSetting::where('key','cms_palette_library')->value('value'),'data',[]);}
    public function save(array $palette): array {
        return DB::transaction(function() use($palette) {
            StoreSetting::query()->insertOrIgnore(['key'=>'cms_palette_library','value'=>json_encode(['data'=>[]]),'created_at'=>now(),'updated_at'=>now()]);
            $setting=StoreSetting::where('key','cms_palette_library')->lockForUpdate()->firstOrFail();
            $records=data_get($setting->value,'data',[]);
            foreach($records as $record) if($record['id']!==$palette['id'] && mb_strtolower($record['name'])===mb_strtolower($palette['name'])) throw ValidationException::withMessages(['name'=>'This palette name is already used.']);
            $existing=array_filter($records,fn($p)=>$p['id']===$palette['id']);
            if(!$existing && count($records)>=20) throw ValidationException::withMessages(['name'=>'The palette library supports up to 20 palettes.']);
            $records=array_values(array_filter($records,fn($p)=>$p['id']!==$palette['id']));$records[]=$palette;
            $setting->update(['value'=>['data'=>$records]]);return $records;
        });
    }
}
