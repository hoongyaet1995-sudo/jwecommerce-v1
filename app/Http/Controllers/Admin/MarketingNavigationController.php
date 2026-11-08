<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Domain\Marketing\MarketingPermission;
use Illuminate\Http\Request;
class MarketingNavigationController extends Controller {
 public function show(Request $request,string $page='overview'){
  abort_unless(MarketingPermission::allows($request->user()),403);
  abort_unless(in_array($page,['overview','campaigns','posts','calendar','analytics','content','channels','templates'],true),404);
  return response()->view('marketing.navigation.preview',['studioNavActive'=>$page==='templates'?'content':$page,'navigationBoot'=>self::boot($page)])->header('Cache-Control','private, no-store');
 }
 public static function boot(string $page):array{
  $urls=['video-studio'=>route('marketing.video-studio'),'settings'=>route('marketing.settings.index'),'images'=>route('marketing.library.images'),'video-assets'=>route('marketing.library.videos'),'automation'=>route('marketing.automation.index'),'overview'=>route('marketing.home'),'videos'=>route('marketing.index'),'assets'=>route('marketing.assets'),'video-new'=>route('marketing.workspace.show'),'template-entry'=>route('marketing.templates.entry')];
  foreach(['campaigns','posts','calendar','analytics','content','channels','templates'] as $name)$urls[$name]=route('marketing.preview.'.$name);
  return ['page'=>$page,'preview'=>true,'urls'=>$urls];
 }
}
