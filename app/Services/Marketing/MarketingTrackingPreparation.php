<?php
namespace App\Services\Marketing;

use App\Models\{MarketingCampaign,MarketingChannelConnection,MarketingPost};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Draft-only preparation against saved channel identities; never calls an OAuth or publishing provider. */
class MarketingTrackingPreparation
{
    public function capability(MarketingChannelConnection $account): array
    {
        if (!in_array($account->platform,['facebook','youtube'],true)) {
            return ['available'=>false,'reason'=>'Post-level website links are not available for this platform.'];
        }
        $summary=app(MarketingChannels::class)->summary($account);
        $refreshable=ExternalChannelConnections::supports($account->platform)&&filled($account->refresh_token)&&(!$account->refresh_expires_at||$account->refresh_expires_at->isFuture());
        if ($account->state!=='connected'||!($summary['capabilities']['identity_granted']??false)||!filled($account->access_token)||($account->expires_at?->isPast()&&!$refreshable)) {
            return ['available'=>false,'reason'=>'Review this account in Channels before preparing its website link.'];
        }
        return ['available'=>true,'reason'=>null];
    }

    public function prepare(int $user,string $post,string $destination,int $version): array
    {
        return DB::transaction(function () use ($user,$post,$destination,$version) {
            $merchant=app(MarketingPosts::class)->merchant();
            $p=MarketingPost::where('user_id',$user)->where('merchant_key',$merchant)->lockForUpdate()->findOrFail($post);
            abort_unless($p->campaign_id,422,'Add this draft to a campaign first.');
            abort_unless($p->status==='Draft'&&empty($p->document['delivery_lock'])&&empty($p->document['outcomes']),409,'Prepare tracking before approving publication.');
            abort_unless(in_array($destination,$p->document['targets']??[],true),422,'Select an account on this draft first.');
            $account=MarketingChannelConnection::where('merchant_key',$merchant)->whereKey($destination)->lockForUpdate()->first();
            abort_unless($account,422,'This account is unavailable in your business. Review the selected accounts.');
            $capability=$this->capability($account);
            abort_unless($capability['available'],422,$capability['reason']);
            $c=MarketingCampaign::where('user_id',$user)->where('merchant_key',$merchant)->findOrFail($p->campaign_id);
            abort_if($c->status==='Archived',409,'Restore this campaign before preparing links.');
            $target=parse_url($c->destination_url); $origin=parse_url(config('app.url'));
            abort_unless(is_array($target)&&in_array($target['scheme']??'',['http','https'],true)&&!isset($target['user'])&&!isset($target['pass'])&&strtolower($target['host']??'')===strtolower($origin['host']??'')&&($target['port']??($target['scheme']==='https'?443:80))===($origin['port']??(($origin['scheme']??'')==='https'?443:80)),422,'Choose a landing page on this store for website conversion tracking.');
            $doc=$p->document; $previous=$doc['campaign_links'][$destination]??null;
            $existing=DB::table('marketing_tracking_links')->where('merchant_key',$merchant)->where('post_id',$p->id)->where('destination_id',$destination)->where('campaign_id',$c->id)->where('destination_url',$c->destination_url)->orderByDesc('created_at')->get()->first(fn($link)=>is_string($previous)&&str_ends_with($previous,'/m/r/'.$link->id));
            $copy=$doc['copies'][$destination]??$doc['copy']??'';
            if ($existing&&is_string($previous)&&str_contains($copy,$previous)) {
                abort_unless($version===$p->version||($version===$p->version-1&&(int)$existing->post_version===$p->version),409,'This draft changed. Reload it before preparing a link.');
                return ['id'=>$existing->id,'url'=>$previous,'post_version'=>$p->version,'reused'=>true];
            }
            abort_unless($version===$p->version,409,'This draft changed. Reload it before preparing a link.');
            $id=(string)Str::uuid(); $url=url('/m/r/'.$id);
            DB::table('marketing_tracking_links')->insert(['id'=>$id,'campaign_id'=>$c->id,'post_id'=>$p->id,'destination_id'=>$destination,'merchant_key'=>$merchant,'user_id'=>$user,'post_version'=>$p->version+1,'destination_url'=>$c->destination_url,'created_at'=>now(),'updated_at'=>now()]);
            if (is_string($previous)&&$previous!=='') $copy=str_replace($previous,'',$copy);
            $doc['copies'][$destination]=trim($copy)."\n".$url;
            abort_if(mb_strlen($doc['copies'][$destination])>10000,422,'Shorten this account’s post text to leave room for its website link.');
            $doc['campaign_links'][$destination]=$url;
            $doc['campaign_link_context'][$destination]=['campaign_id'=>$c->id,'destination_url'=>$c->destination_url];
            $p->forceFill(['document'=>$doc,'version'=>$p->version+1])->save();
            return ['id'=>$id,'url'=>$url,'post_version'=>$p->version,'reused'=>false];
        });
    }
}
