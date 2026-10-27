<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MarketingStoryboardUpload
{
    public function store(string $project, int $owner, UploadedFile $file, bool $authorized): array
    {
        app(MarketingStoryboardWorkspace::class)->project($project, $owner);
        abort_unless($authorized, 422, '请确认有权使用该素材。');
        Validator::make(['file' => $file], ['file' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,mov,webm|max:30720'])->validate();
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($file->getRealPath());
            abort_unless($info && $info[0] * $info[1] <= 40000000, 422, '图片尺寸不支持。');
        } else {
            $probe = app(MarketingMediaTools::class)->probe($file->getRealPath());
            abort_unless(collect($probe['streams'] ?? [])->contains('codec_type', 'video'), 422, '视频文件无效。');
        }
        return \Illuminate\Support\Facades\DB::transaction(function()use($project,$owner,$file,$mime){
            app(MarketingStoryboardWorkspace::class)->project($project,$owner,true);
            $hash=hash_file('sha256',$file->getRealPath());$path='marketing/'.$project.'/storyboard/'.$hash.'.'.$file->guessExtension();
            $old=MarketingAsset::where('project_id',$project)->where('path',$path)->where('authorized',true)->first();if($old)return $this->reference($project,$old->id);
            $file->storeAs('marketing/'.$project.'/storyboard',basename($path),'local');
            $asset=MarketingAsset::create(['project_id'=>$project,'name'=>mb_substr(basename($file->getClientOriginalName()),0,180),'path'=>$path,'mime'=>$mime,'size'=>$file->getSize(),'authorized'=>true]);
            return $this->reference($project,$asset->id);
        });
    }

    public function reference(string $project, string $id): array
    {
        $a = MarketingAsset::where('project_id', $project)->where('authorized', true)->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($a->path), 422, '原素材文件不存在。');

        return ['asset_id' => $a->id, 'sha256' => hash_file('sha256', Storage::disk('local')->path($a->path)), 'mime' => $a->mime, 'kind' => str_starts_with($a->mime,'image/')?'image':(str_starts_with($a->mime,'audio/')?'audio':'video'), 'local_preview' => route('marketing.asset',[$project, $a->id])];
    }
}
