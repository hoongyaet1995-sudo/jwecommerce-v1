<?php
namespace Tests\Feature;
use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\Marketing\{MarketingEditPlanService,MarketingWorkspaceFilm};
class MarketingEditedFilmTest extends TestCase {
 use RefreshDatabase,MarketingEditingFixtures;
 public function test_derivative_has_current_frame_recipe_and_independent_approval():void {
  [$p,$base,$clips]=$this->editingFilm();$base->update(['approved_at'=>now(),'approved_sha256'=>$base->output_sha256]);$service=app(MarketingEditPlanService::class);$plan=$service->propose($p,$base,$this->editingCandidate($clips),$this->editingAnalyses($clips),$this->editingMessage($p)->id);$f=$service->adopt($p->id,$p->owner_id,$plan->id,(string)Str::uuid(),$base->id,$base->output_sha256);
  $this->assertSame($p->id,app(MarketingWorkspaceFilm::class)->assertCurrent($f)->id);$this->assertNull($f->approved_at);$this->assertSame(108,$f->input['total_frames']);$this->assertNotSame($base->fingerprint,$f->fingerprint);$this->assertNotNull($base->fresh()->approved_at);Http::assertNothingSent();
 }
 public function test_tampered_derivative_cannot_render_or_approve():void {
  [$p,$base,$clips]=$this->editingFilm();$service=app(MarketingEditPlanService::class);$plan=$service->propose($p,$base,$this->editingCandidate($clips),$this->editingAnalyses($clips),$this->editingMessage($p)->id);$f=$service->adopt($p->id,$p->owner_id,$plan->id,(string)Str::uuid(),$base->id,$base->output_sha256);$input=$f->input;$input['scenes'][0]['out_frame']=90;$f->update(['input'=>$input]);$this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);app(MarketingWorkspaceFilm::class)->assertCurrent($f);
 }
}
