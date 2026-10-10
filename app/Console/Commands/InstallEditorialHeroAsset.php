<?php

namespace App\Console\Commands;

use App\Models\CmsMediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class InstallEditorialHeroAsset extends Command
{
    protected $signature = 'cms:install-editorial-hero-asset';
    protected $description = 'Install the bundled Editorial Overlay starter photo as managed CMS media';

    public function handle(): int
    {
        $id='b773291f-3f89-42ec-b036-efb180c23494';
        $source=public_path('images/cms-samples/home-editorial-hero.png');
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
            'original_name'=>'home-editorial-hero.png','mime_type'=>'image/png',
            'size'=>filesize($source),'width'=>$width,'height'=>$height,'checksum'=>$checksum,
            'uploader_id'=>null,'orphaned_at'=>null,
        ]);
        $this->info('Editorial hero starter photo is available.');
        return self::SUCCESS;
    }
}
