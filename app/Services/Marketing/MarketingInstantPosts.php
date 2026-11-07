<?php
namespace App\Services\Marketing;
use App\Models\{MarketingInstantPostRun as Run,User};

final class MarketingInstantPosts
{
 public function owned(User $u,string $id):Run {app(MarketingFreePosts::class)->authorize($u);return Run::where('user_id',$u->id)->where('merchant_key',app(MarketingPosts::class)->merchant())->findOrFail($id);}
 public function prepare(User $u,array $input):Run {
  // Ignore scheduling/publishing fields even if a client injects them.
  $settings=app(MarketingPostAutomations::class)->validateSettings($u,array_replace($input,['version'=>0,'preset'=>'blank','schedule_enabled'=>false,'auto_publish'=>false,'publish_targets'=>[],'publish_options'=>[],'days'=>[],'time'=>'10:00','timezone'=>'Asia/Kuala_Lumpur']));
  $digest=hash('sha256',json_encode($settings,JSON_THROW_ON_ERROR));
  if(Run::find($settings['id'])){$r=$this->owned($u,$settings['id']);abort_unless(hash_equals($r->snapshot['input_digest'],$digest),409,'This generation already has a different brief. Keep its record and start a new generation.');return $r;}
  abort_if(\App\Models\MarketingDailyDraftRun::find($settings['id'])||\App\Models\MarketingAutomationRun::find($settings['id']),409,'This generation identifier is already in use.');
  $merchant=app(MarketingPosts::class)->merchant();$connection=app(MarketingFreePostProvider::class)->connection();$intelligence=app(MarketingCreativeIntelligence::class)->context($merchant,$settings);$pool=app(MarketingAutomationSources::class)->pool($settings+['_recent_product_ids'=>$intelligence['recent_product_ids'],'_rotation'=>$intelligence['memory']['total']]);
  return Run::create(['id'=>$settings['id'],'user_id'=>$u->id,'merchant_key'=>$merchant,'status'=>'selection_prepared','snapshot'=>['settings'=>$settings,'input_digest'=>$digest,'pool'=>$pool,'intelligence'=>$intelligence,'connection_version'=>$connection->version]]);
 }
 public function step(User $u,string $id):array {return app(MarketingPostAutomations::class)->stepRun($u,$this->owned($u,$id));}
 public function view(Run $r):array {return app(MarketingPostAutomations::class)->view($r);}
}
