<?php

namespace App\Console\Commands;

use App\Models\CmsMediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class InstallShopNavigationAssets extends Command
{
    protected $signature = 'cms:install-shop-navigation-assets';
    protected $description = 'Install the bundled Shop Navigation starter photographs as managed CMS media';

    public function handle(): int
    {
        foreach (json_decode(file_get_contents(resource_path('cms/shop-navigation-assets.json')),true,512,JSON_THROW_ON_ERROR) as $photo) {
        $id=$photo['id'];
        $source=public_path('images/cms-samples/'.$photo['file']);
        if (!is_file($source)) { $this->error('Bundled hero photo is missing.'); return self::FAILURE; }
        $checksum=hash_file('sha256',$source);
        $existing=CmsMediaAsset::find($id);
        if ($existing && $existing->checksum!==$checksum) {
            $this->error('Asset identity conflicts with existing media; no changes made.');
            return self::FAILURE;
        }
        $path='cms/media/'.$id.'.png';
        $disk=Storage::disk('public');
        if (!$disk->exists($path) && !$disk->put($path,file_get_contents($source),'public')) {
            $this->error('Unable to store the bundled photo.'); return self::FAILURE;
        }
        [$width,$height]=getimagesize($source);
        CmsMediaAsset::firstOrCreate(['id'=>$id],[
            'disk'=>'public','path'=>$path,'url'=>'/storage/'.$path,
            'original_name'=>$photo['file'],'mime_type'=>'image/png',
            'size'=>filesize($source),'width'=>$width,'height'=>$height,'checksum'=>$checksum,
            'uploader_id'=>null,'orphaned_at'=>null,
        ]);
        }
        $this->info('Shop Navigation starter photographs are available.');
        return self::SUCCESS;
    }
}
