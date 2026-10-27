<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{MarketingVideoFilm,MarketingVideoClip};
use App\Services\Marketing\{MarketingClipSource,MarketingSubtitleService,MarketingVideoFiles};
use Illuminate\Http\Request;
class MarketingSubtitleController extends Controller {
 private function film(Request $r,string $project,string $film):MarketingVideoFilm{app(MarketingClipSource::class)->project($project,(int)$r->user()?->id);return MarketingVideoFilm::where('project_id',$project)->where('owner_id',$r->user()->id)->where('status','completed')->findOrFail($film);}
 public function config(Request $r,string $project,string $film,MarketingSubtitleService $s){return response()->json($s->config($this->film($r,$project,$film)))->header('Cache-Control','private, no-store');}
 public function prepare(Request $r,string $project,string $film,MarketingSubtitleService $s){$s->prepare($this->film($r,$project,$film));return response()->json(['status'=>'queued']);}
 public function adopt(Request $r,string $project,string $film,MarketingSubtitleService $s){$f=$this->film($r,$project,$film);$d=$r->validate(['request_id'=>'required|uuid','base_sha256'=>'required|string|size:64','settings'=>'required|array','consent'=>'required|accepted']);$new=$s->adopt($f,$d['request_id'],$d['base_sha256'],$d['settings']);return response()->json(['film_id'=>$new->id,'url'=>route('marketing.workspace.show',['project'=>$project,'screen'=>'finished','film'=>$new->id])]);}
 public function raw(Request $r,string $project,string $clip){$p=app(MarketingClipSource::class)->project($project,(int)$r->user()?->id);$c=MarketingVideoClip::where('project_id',$project)->where('owner_id',$r->user()->id)->where('status','completed')->findOrFail($clip);app(MarketingClipSource::class)->path($p,$c->input['image']['asset_id'],$c->input['image']['sha256']);return response()->file(app(MarketingVideoFiles::class)->file($c->raw_path,$c->raw_sha256),['Content-Type'=>'video/mp4','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
}
