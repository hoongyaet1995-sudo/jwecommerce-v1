<?php

namespace Database\Seeders;

use App\Models\CmsMediaAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Install the template's bundled photographs using canonical managed URLs. */
class AboutChaptersMediaSeeder extends Seeder
{
    public function run(): void
    {
        $assets = json_decode(file_get_contents(resource_path('cms/about-chapters-media.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($assets as $name => $id) {
            $source = public_path('page-template-sample/chapters/'.$name.'.png');
            if (!is_file($source)) throw new RuntimeException('Missing About Chapters template photograph: '.$name);
            $path = 'cms/media/'.$id.'.png';
            $dimensions = getimagesize($source);
            $stream = fopen($source, 'rb');
            try { Storage::disk('public')->put($path, $stream); }
            finally { fclose($stream); }
            CmsMediaAsset::updateOrCreate(['id' => $id], [
                'disk' => 'public', 'path' => $path, 'url' => '/storage/'.$path,
                'original_name' => $name.'.png', 'mime_type' => 'image/png',
                'size' => filesize($source), 'width' => $dimensions[0], 'height' => $dimensions[1],
                'checksum' => hash_file('sha256', $source),
            ]);
        }
    }
}
