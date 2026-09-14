<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsMediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CmsMediaAssetController extends Controller
{
    private const VIDEO_MAX_KIB = 30 * 1024;
    public function store(Request $request): JsonResponse
    {
        try {
            $asset=app(\App\Services\ManagedMediaIngest::class)->ingest($this->validatedFile($request),$request->user()?->getKey());
        } catch (ValidationException $exception) {
            return response()->json(['message'=>'The media asset upload is invalid.','code'=>'cms_media_upload_invalid','errors'=>$exception->errors()],422);
        }
        return response()->json(['data'=>['id'=>(string)$asset->getKey(),'url'=>$asset->url,'path'=>$asset->path,'mimeType'=>$asset->mime_type,'size'=>$asset->size]],201);
    }

    private function validatedFile(Request $request): UploadedFile
    {
        $input = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:'.self::VIDEO_MAX_KIB],
        ])->validate();
        $file = $input['file'] ?? null;

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['A valid uploaded file is required.'],
            ]);
        }

        return $file;
    }

}
