<?php
namespace App\Http\Controllers\Admin;
use Illuminate\Http\Request;
use App\Services\Marketing\MarketingInstantPosts;
class MarketingInstantPostController {
 public function create(Request $r,MarketingInstantPosts $s){
  try{return response()->json($s->view($s->prepare($r->user(),$r->all())))->header('Cache-Control','private, no-store');}
  catch(\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){
   $status=$e instanceof \Illuminate\Validation\ValidationException?422:$e->getStatusCode();if($status>=500)throw $e;
   $id=$r->input('id');$exists=is_string($id)&&\Illuminate\Support\Str::isUuid($id)&&\App\Models\MarketingInstantPostRun::whereKey($id)->exists();
   return response()->json(['message'=>$e->getMessage(),'errors'=>$e instanceof \Illuminate\Validation\ValidationException?$e->errors():[],'generation_uncreated'=>!$exists],$status)->header('Cache-Control','private, no-store');
  }
 }
 public function status(Request $r,string $run,MarketingInstantPosts $s){return response()->json($s->view($s->owned($r->user(),$run)))->header('Cache-Control','private, no-store');}
 public function step(Request $r,string $run,MarketingInstantPosts $s){@set_time_limit(300);return response()->json($s->step($r->user(),$run))->header('Cache-Control','private, no-store');}
}
