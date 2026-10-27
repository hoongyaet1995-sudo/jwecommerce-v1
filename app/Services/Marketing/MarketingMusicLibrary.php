<?php

namespace App\Services\Marketing;

/** Versioned, locally hosted CC0 catalogue. Import and licensing review happen offline. */
class MarketingMusicLibrary
{
    public function tracks(): array
    {
        $manifest=resource_path('marketing-audio/library/catalogue.json');
        if(!is_file($manifest))return [];
        $data=json_decode(file_get_contents($manifest),true,32,JSON_THROW_ON_ERROR);
        return array_values(array_filter($data['tracks']??[],fn($t)=>($t['license']['license']??'')==='CC0-1.0'&&($t['active']??true)));
    }

    public function audio(string $id): array
    {
        $track=collect($this->tracks())->firstWhere('id',$id);abort_unless($track,404);
        abort_unless(preg_match('/^[a-z0-9-]+\.(mp3|ogg)$/',$track['filename']),409,'音乐文件记录无效。');
        $file=resource_path('marketing-audio/library/'.$track['filename']);
        abort_unless(is_file($file)&&hash_equals($track['sha256'],hash_file('sha256',$file)),409,'音乐文件已变化，请重新选择。');
        return ['id'=>$id,'path'=>$file,'sha256'=>$track['sha256'],'duration'=>$track['duration'],'name'=>$track['name'],'license_scope'=>'cc0','license'=>$track['license'],'extension'=>$track['extension'],'mime'=>$track['mime']];
    }

    public function catalogue(string $project): array
    {
        return array_map(fn($t)=>['id'=>$t['id'],'name'=>$t['name'],'author'=>$t['author'],'duration'=>$t['duration'],'internal'=>false,'kind'=>'library','license'=>'CC0-1.0','source_url'=>$t['license']['source_url'],'license_url'=>$t['license']['license_url'],'url'=>route('marketing.workspace.packaging.audio',[$project,$t['id']]),'mood'=>$t['mood'],'tags'=>$t['tags'],'recommended'=>$t['recommended']??false,'sha256'=>$t['sha256']],$this->tracks());
    }
}
