<?php
namespace App\Jobs;
use App\Models\SupportAttachment;
use App\Models\ManagedSocialAccount;
use App\Services\Support\SupportAttachmentService;
use App\Services\Support\SupportMediaDownload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadSupportImage implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public int $timeout=100;
    public function __construct(public string $attachmentId) {}
    public function backoff(): array {return [10,60,180];}
    public function handle(SupportMediaDownload $download, SupportAttachmentService $media): void
    {
        $a=SupportAttachment::find($this->attachmentId);if(!$a || $a->status==='ready')return;
        $account=ManagedSocialAccount::find($a->account_id);
        if(!$account || $account->state!=='connected' || $account->merchant_key!==$a->merchant_key) { $this->failed(new \RuntimeException());return; }
        $url=$a->provider_reference;$token=null;
        if($account->platform==='whatsapp'){
            if(!preg_match('/^\d{1,100}$/D',$url))throw new \RuntimeException('Invalid media ID.');
            $response=Http::withToken($account->access_token)->connectTimeout(5)->timeout(10)->get('https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$url);
            if(!$response->successful() || (string)$response->json('id')!==$url || !is_string($response->json('url')))throw new \RuntimeException('Media lookup unavailable.');
            $url=$response->json('url');$token=$account->access_token;
        }
        if($a->kind==='document'){
            $bytes=app(\App\Services\Support\SupportPdfProcessor::class)->validate($download->download($url,$token,\App\Services\Support\SupportPdfProcessor::MAX_BYTES));$mime='application/pdf';$width=$height=$duration=null;
        }elseif($a->kind==='audio'){
            [$bytes,$mime,$duration]=app(\App\Services\Support\SupportAudioProcessor::class)->normalize($download->download($url,$token,\App\Services\Support\SupportAudioProcessor::MAX_BYTES));$width=$height=null;
        }else{[$bytes,$mime,$width,$height]=$media->encode($download->download($url,$token));$duration=null;}
        $path=(match($a->kind){'audio'=>'audio/','document'=>'documents/',default=>'images/'}).$a->id.'.'.match($mime){'application/pdf'=>'pdf','audio/ogg; codecs=opus'=>'ogg','image/jpeg'=>'jpg','image/webp'=>'webp',default=>'png'};
        Storage::disk($a->disk)->put($path,$bytes);
        $a->update(['path'=>$path,'duration_seconds'=>$duration,'mime'=>$mime,'width'=>$width,'height'=>$height,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'status'=>'ready','error'=>null,'provider_reference'=>null]);
        $a->conversation->touch();
        if($source=\App\Models\SupportMessage::find($a->message_id)) app(\App\Services\Support\SupportAttachmentAnalysisService::class)->schedule($source);
    }
    public function failed(?\Throwable $error): void
    {
        $a=SupportAttachment::find($this->attachmentId);
        if($a && $a->status!=='ready'){$a->update(['status'=>'failed','error'=>'Media unavailable. Ask the customer to resend it.']);$a->conversation->touch();if($source=\App\Models\SupportMessage::find($a->message_id))app(\App\Services\Support\SupportAttachmentAnalysisService::class)->schedule($source);}
    }
}
