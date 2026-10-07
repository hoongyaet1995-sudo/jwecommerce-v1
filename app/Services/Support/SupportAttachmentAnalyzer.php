<?php
namespace App\Services\Support;

use App\Models\{AiConnection,SupportAttachment};
use App\Services\AI\{OpenAiSupportMediaClient,ProviderFailure};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SupportAttachmentAnalyzer
{
    public function connection(): ?AiConnection
    {
        return AiConnection::where('enabled',true)->where('support_enabled',true)->whereNotNull('verified_at')->first();
    }
    public function readiness(array $settings): array
    {
        $record=$this->connection(); $policy=app(SupportAttachmentAnalysisPolicy::class)->settings($settings);
        $client=app(OpenAiSupportMediaClient::class);
        return ['provider'=>$record?->provider,'model'=>$record?->text_model,'image'=>$record?->provider==='openai'&&$client->capabilities($record->text_model??'')['image'],
            'document'=>$record?->provider==='openai'&&$client->capabilities($record->text_model??'')['document'],'audio'=>$record?->provider==='openai'&&$client->capabilities($policy['transcriptionModel'])['audio']];
    }
    public function analyze(SupportAttachment $attachment,array $settings): array
    {
        $policy=app(SupportAttachmentAnalysisPolicy::class)->settings($settings);
        $record=$this->connection();
        if(!$record || !$this->readiness($settings)[$attachment->kind]) throw new ProviderFailure('unsupported');
        if(!$attachment->path || !Storage::disk($attachment->disk)->exists($attachment->path)) throw new ProviderFailure('media_missing');
        if($attachment->kind==='audio' && (!$attachment->duration_seconds || $attachment->duration_seconds>$policy['audioSeconds'])) throw new ProviderFailure('analysis_limit');
        if($attachment->kind==='document') {
            $process=new Process([config('support_media.qpdf'),'--show-npages',Storage::disk($attachment->disk)->path($attachment->path)]);
            $process->setTimeout(10); try {$process->run();} catch(\Throwable) {throw new ProviderFailure('inspection');}
            $pages=trim($process->getOutput());
            if(!$process->isSuccessful()||!ctype_digit($pages)) throw new ProviderFailure('inspection');
            if((int)$pages<1 || (int)$pages>$policy['pdfPages']) throw new ProviderFailure('analysis_limit');
        }
        $bytes=Storage::disk($attachment->disk)->get($attachment->path);
        $max=match($attachment->kind){'image'=>SupportAttachmentService::MAX_BYTES,'document'=>SupportPdfProcessor::MAX_BYTES,'audio'=>SupportAudioProcessor::MAX_BYTES,default=>0};
        if(strlen($bytes)>$max||!hash_equals($attachment->sha256??'',hash('sha256',$bytes))) throw new ProviderFailure('media_changed');
        $model=$attachment->kind==='audio'?$policy['transcriptionModel']:$record->text_model;
        return app(OpenAiSupportMediaClient::class)->analyze($record->api_key,null,$model,$attachment->kind,$attachment->mime,$bytes)
            + ['provenance'=>['attachmentId'=>$attachment->id,'sourceHash'=>$attachment->sha256,'kind'=>$attachment->kind],'provider'=>'openai','model'=>$model];
    }
}
