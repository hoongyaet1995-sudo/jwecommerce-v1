<?php
namespace App\Services\Support;
use App\Models\ManagedSocialAccount;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class SupportImageTransport
{
    public function payload(SupportMessage $m, ManagedSocialAccount $account): array
    {
        $a=$m->attachments()->first();
        if(!$a)return $account->platform==='whatsapp'?['type'=>'text','text'=>['body'=>$m->content]]:['text'=>$m->content];
        if($a->status!=='ready' || !$a->path)throw new \RuntimeException('Image not ready.');
        if($account->platform==='whatsapp'){
            // Upload cannot deliver a customer message; an upload failure is safe to retry.
            $result=Http::withToken($account->access_token)->connectTimeout(5)->timeout(20)->attach('file',Storage::disk($a->disk)->get($a->path),$a->name,['Content-Type'=>$a->mime])->post(
                'https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$account->provider_account_id.'/media',['messaging_product'=>'whatsapp','type'=>$a->mime]);
            $id=$result->json('id');
            if(!$result->successful() || !is_string($id) || !preg_match('/^\d{1,100}$/D',$id))throw new \RuntimeException('Image upload rejected.');
            if($a->kind==='document')return ['type'=>'document','document'=>['id'=>$id,'filename'=>$a->name,...($m->content!==''?['caption'=>$m->content]:[])]];
            if($a->kind==='audio')return ['type'=>'audio','audio'=>['id'=>$id]];
            return ['type'=>'image','image'=>['id'=>$id,...($m->content!==''?['caption'=>$m->content]:[])]];
        }
        return ['attachment'=>['type'=>$a->kind==='document'?'file':'image','payload'=>['url'=>URL::temporarySignedRoute('support.provider-image',now()->addMinutes(15),['attachment'=>$a->id])]]];
    }
}
