<?php

namespace Database\Seeders;

use App\Models\CmsMediaAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Install the template's bundled photographs using canonical managed URLs. */
class ContactTemplateMediaSeeder extends Seeder
{
    public function run(): void
    {
        $assets = ['contact-studio' => 'e97c0b5f-5463-4314-a5c3-99ce6cd08a7f'];
        foreach ($assets as $name => $id) {
            $source = public_path('images/cms-samples/'.$name.'.png');
            if (!is_file($source)) throw new RuntimeException('Missing Contact template photograph: '.$name);
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

