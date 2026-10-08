<?php

namespace Database\Seeders;

use App\Models\CmsMediaAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Install the template's bundled photographs using canonical managed URLs. */
class AboutEditorialMediaSeeder extends Seeder
{
    public function run(): void
    {
        $ids = ['6c753385-2f31-4a9a-8cfb-9f33b7d65923', 'eb51bde3-ddd3-4b20-9268-85c4efcda3e3', 'fb690366-d0bb-49ff-9d32-bfea5408b4bc'];
        $assets = array_combine($ids, $ids);
        foreach ($assets as $name => $id) {
            $source = public_path('page-template-sample/editorial/'.$name.'.png');
            if (!is_file($source)) throw new RuntimeException('Missing About Editorial template photograph: '.$name);
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
