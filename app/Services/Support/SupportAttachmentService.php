<?php
namespace App\Services\Support;

use App\Models\SupportAttachment;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportAttachmentService
{
    public const MAX_BYTES=5242880;

    public function encode(string $bytes): array
    {
        abort_unless(function_exists('imagecreatefromstring'),503,'Image processing is unavailable. Please contact your administrator.');
        $info=@getimagesizefromstring($bytes);
        if(strlen($bytes)>self::MAX_BYTES || !$info || !in_array($info['mime']??null,['image/jpeg','image/png','image/webp'],true)
            || $info[0]>8192 || $info[1]>8192 || $info[0]*$info[1]>16000000) {
            throw ValidationException::withMessages(['image'=>'Use a JPEG, PNG or WebP image up to 5 MB and 16 million pixels.']);
        }
        $image=@imagecreatefromstring($bytes);
        if(!$image)throw ValidationException::withMessages(['image'=>'This image could not be decoded.']);
        imagealphablending($image,false);imagesavealpha($image,true);
        // WhatsApp image messages accept JPEG/PNG; normalize browser WebP to PNG.
        if($info['mime']==='image/webp')$info['mime']='image/png';
        ob_start();
        try {
            match($info['mime']) {'image/jpeg'=>imagejpeg($image,null,90),'image/webp'=>imagewebp($image,null,90),default=>imagepng($image)};
            $clean=ob_get_contents();
        } finally {ob_end_clean();imagedestroy($image);}
        if(!is_string($clean)||strlen($clean)>self::MAX_BYTES)throw ValidationException::withMessages(['image'=>'Processed image exceeds 5 MB.']);
        return [$clean,$info['mime'],$info[0],$info[1]];
    }

    public function upload(SupportConversation $c, UploadedFile $file, ?int $staffId): SupportAttachment
    {
        [$bytes,$mime,$width,$height]=$this->encode(file_get_contents($file->getRealPath()));
        $id=(string)Str::uuid();$path='images/'.$id.'.'.match($mime){'image/jpeg'=>'jpg','image/webp'=>'webp',default=>'png'};
        Storage::disk('support-private')->put($path,$bytes);
        try {return SupportAttachment::create(['id'=>$id,'conversation_id'=>$c->id,'owner_hash'=>$c->owner_hash,'staff_user_id'=>$staffId,'merchant_key'=>$c->merchant_key,
            'path'=>$path,'name'=>mb_substr(basename($file->getClientOriginalName()),0,180),'mime'=>$mime,'width'=>$width,'height'=>$height,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)]);
        } catch(\Throwable $e) {Storage::disk('support-private')->delete($path);throw $e;}
    }

    public function uploadAudio(SupportConversation $c, UploadedFile $file, int $staffId): SupportAttachment
    {
        [$bytes,$mime,$duration]=app(SupportAudioProcessor::class)->normalize(file_get_contents($file->getRealPath()));
        $id=(string)Str::uuid();$path='audio/'.$id.'.ogg';Storage::disk('support-private')->put($path,$bytes);
        try{return SupportAttachment::create(['id'=>$id,'conversation_id'=>$c->id,'owner_hash'=>$c->owner_hash,'staff_user_id'=>$staffId,'merchant_key'=>$c->merchant_key,'kind'=>'audio','duration_seconds'=>$duration,'path'=>$path,'name'=>'Voice message.ogg','mime'=>$mime,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)]);}
        catch(\Throwable $e){Storage::disk('support-private')->delete($path);throw $e;}
    }

    public function safeName(string $name,string $fallback='Document.pdf'):string {
        $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u','_',basename($name)),0,160);
        return trim($name)!==''?$name:$fallback;
    }
    public function uploadDocument(SupportConversation $c, UploadedFile $file, ?int $staffId):SupportAttachment {
        if(strtolower($file->getClientOriginalExtension())!=='pdf')throw ValidationException::withMessages(['document'=>'Use a PDF file.']);
        $bytes=app(SupportPdfProcessor::class)->validate(file_get_contents($file->getRealPath()));
        $id=(string)Str::uuid();$path='documents/'.$id.'.pdf';Storage::disk('support-private')->put($path,$bytes);
        try{return SupportAttachment::create(['id'=>$id,'conversation_id'=>$c->id,'owner_hash'=>$c->owner_hash,'staff_user_id'=>$staffId,'merchant_key'=>$c->merchant_key,'kind'=>'document','path'=>$path,'name'=>$this->safeName($file->getClientOriginalName()),'mime'=>'application/pdf','bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes)]);}
        catch(\Throwable $e){Storage::disk('support-private')->delete($path);throw $e;}
    }

    public function bind(SupportConversation $c, SupportMessage $message, ?string $id, ?int $staffId): void
    {
        $existing=SupportAttachment::where('message_id',$message->id)->value('id');
        if($existing!==null && $existing!==$id)throw ValidationException::withMessages(['attachment_id'=>'This message identifier has already been used with another attachment.']);
        if(!$id) return;
        $a=SupportAttachment::whereKey($id)->where('conversation_id',$c->id)->where('owner_hash',$c->owner_hash)->where('staff_user_id',$staffId)->lockForUpdate()->first();
        abort_unless($a && $a->status==='ready' && ($a->message_id===$message->id || (!$a->message_id && $a->created_at->gt(now()->subDay()))),404,'Image upload unavailable.');
        abort_if($a->message_id && $a->message_id!==$message->id,422,'This image belongs to another message.');
        abort_if($a->kind==='audio' && ($c->channel!=='whatsapp' || $message->content!==''),422,'Send WhatsApp audio and text as separate messages.');
        abort_if($a->kind==='document'&&!app(SupportMediaCapabilities::class)->forChannel($c->channel??'web_guest')['documents'],422,'Documents are unavailable for this channel.');
        $a->update(['message_id'=>$message->id]);
    }

    public function incoming(SupportConversation $c, SupportMessage $message, \App\Models\ManagedSocialAccount $account, string $reference, string $kind='image', ?string $name=null): void
    {
        $a=SupportAttachment::create(['id'=>(string)Str::uuid(),'conversation_id'=>$c->id,'message_id'=>$message->id,'owner_hash'=>$c->owner_hash,'merchant_key'=>$c->merchant_key,'account_id'=>$account->id,'provider_reference'=>$reference,'name'=>$kind==='document'?$this->safeName($name??'Customer document.pdf'):($kind==='audio'?'Customer voice message':'Customer image'),'kind'=>$kind,'status'=>'pending']);
        \App\Jobs\DownloadSupportImage::dispatch($a->id)->onConnection('support')->onQueue('support-ai')->afterCommit();
    }

    public function view(SupportMessage $message, bool $admin=false): array
    {
        return $message->attachments->map(fn($a)=>['id'=>$a->id,'type'=>$a->kind ?? 'image','durationSeconds'=>$a->duration_seconds,'name'=>$a->name,'mimeType'=>$a->mime,'sizeBytes'=>$a->bytes,'status'=>$a->status,
            'url'=>$a->status==='ready'?($admin?'/admin/api/support/attachments/':'/support/chat/attachments/').$a->id:null,'error'=>$a->error,'retryUrl'=>$admin&&$a->status==='failed'&&$a->account_id&&$a->provider_reference?'/admin/api/support/attachments/'.$a->id.'/retry':null]
            +($admin&&\Illuminate\Support\Facades\Schema::hasTable('support_attachment_analyses')?['analysis'=>app(SupportAttachmentAnalysisService::class)->view($a)]:[]))->all();
    }
}
