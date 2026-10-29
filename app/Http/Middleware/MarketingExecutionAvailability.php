<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class MarketingExecutionAvailability {
 public function handle(Request $request,Closure $next){
  if(config('marketing.execution_enabled',false)||$request->isMethod('GET')||$request->isMethod('HEAD'))return $next($request);
  $safe=['marketing.campaigns.link','marketing.campaigns.write','marketing.campaigns.update','marketing.campaigns.assign','marketing.posts.ai','marketing.posts.upload','marketing.posts.creator','marketing.posts.write','marketing.channels.authorize','marketing.channels.select','marketing.channels.cancel','marketing.channels.check','marketing.channels.disconnect','marketing.store','marketing.workspace.store','marketing.workspace.source','marketing.workspace.preferences','marketing.workspace.answer','marketing.brief','marketing.source','marketing.templates.create'];$name=$request->route()?->getName();
  if(in_array($name,$safe,true)||($name==='marketing.templates.write'&&$request->route('action')==='brief'))return $next($request);
  $message='制作执行暂未开放：当前共享主机尚未配置渲染工具与任务执行器。可浏览及整理资料；迁移 VPS 并完成环境验收后再开放制作，不会产生生成费用。';
  if($request->expectsJson())return response()->json(['code'=>'marketing_execution_unavailable','message'=>$message],423);
  return back()->withErrors(['hosting'=>$message]);
 }
}
