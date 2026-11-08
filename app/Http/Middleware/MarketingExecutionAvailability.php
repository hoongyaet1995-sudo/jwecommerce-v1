<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class MarketingExecutionAvailability {
 public function handle(Request $request,Closure $next){
  if(in_array($request->route()?->getName(),['marketing.automation.save','marketing.automation.generate','marketing.automation.step'],true)&&(app()->environment('local','testing')||config('marketing.free_post_generation_enabled',false)))return $next($request);
  if((app()->environment('local','testing')||config('marketing.free_post_generation_enabled',false))&&in_array($request->route()?->getName(),['marketing.free-posts.create','marketing.free-posts.step'],true))return $next($request);
  if(config('marketing.execution_enabled',false)||$request->isMethod('GET')||$request->isMethod('HEAD'))return $next($request);
  if($request->route()?->getName()==='marketing.workspace.storyboard.write'&&app(\App\Services\Marketing\MarketingGenerationQuotes::class)->freeManual((string)$request->route('action'),$request->all()))return $next($request);
  $safe=['marketing.workspace.storyboard.library','marketing.avatars.draft','marketing.avatars.write','marketing.library.upload','marketing.automation.source-preview','marketing.whatsapp-batches.review','marketing.whatsapp-batches.confirm','marketing.whatsapp-batches.continue','marketing.email-batches.continue','marketing.settings.email.bounces','marketing.settings.email.save','marketing.settings.email.check','marketing.email-batches.review','marketing.email-batches.confirm','marketing.audience.continue-sync','marketing.audience.sync','marketing.audience.import','marketing.audience.export','marketing.audience.confirm','marketing.audience.segment-save','marketing.audience.segment-action','marketing.audience.preview','marketing.audience.subscription','marketing.campaigns.channel-draft','marketing.email-templates.media','marketing.email-sms-templates.save','marketing.email-test.send','marketing.content-messages.create','marketing.content-messages.update','marketing.content-templates.create','marketing.content-templates.update','marketing.content-templates.action','marketing.channels.whatsapp.cancel','marketing.channels.whatsapp.select','marketing.channels.whatsapp.authorize','marketing.campaigns.link','marketing.campaigns.write','marketing.campaigns.update','marketing.campaigns.assign','marketing.campaigns.duplicate','marketing.posts.ai','marketing.posts.upload','marketing.posts.creator','marketing.posts.write','marketing.channels.authorize','marketing.channels.select','marketing.channels.cancel','marketing.channels.check','marketing.channels.disconnect','marketing.store','marketing.workspace.store','marketing.workspace.source','marketing.workspace.preferences','marketing.workspace.answer','marketing.brief','marketing.source','marketing.templates.create'];$name=$request->route()?->getName();
  if(in_array($name,$safe,true)||($name==='marketing.templates.write'&&$request->route('action')==='brief'))return $next($request);
  $message='制作执行暂未开放：当前共享主机尚未配置渲染工具与任务执行器。可浏览及整理资料；迁移 VPS 并完成环境验收后再开放制作，不会产生生成费用。';
  if($request->expectsJson())return response()->json(['code'=>'marketing_execution_unavailable','message'=>$message],423);
  return back()->withErrors(['hosting'=>$message]);
 }
}
