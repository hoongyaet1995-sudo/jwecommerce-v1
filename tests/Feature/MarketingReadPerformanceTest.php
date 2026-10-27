<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\MarketingEditingFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Marketing\{MarketingClipSource,MarketingMaterialRepair,MarketingTransitionPlanning};

class MarketingReadPerformanceTest extends TestCase
{
    use RefreshDatabase, MarketingEditingFixtures;

    public function test_graph_services_share_the_read_scope(): void
    {
        $this->assertSame(app(MarketingClipSource::class),app(MarketingClipSource::class));
        $this->assertSame(app(MarketingMaterialRepair::class),app(MarketingMaterialRepair::class));
    }

    public function test_repair_evidence_is_reused_only_inside_a_read_snapshot_and_cleared_on_exception(): void
    {
        [$p]=$this->editingFilm();
        $this->partialMock(MarketingTransitionPlanning::class,function($mock){
            $mock->shouldReceive('referenceEvidence')->times(3)->andReturn([]);
            $mock->shouldReceive('inventory')->times(3)->andReturn([]);
        });
        $source=app(MarketingClipSource::class);
        try{
            $source->readSnapshot(function()use($p){
                $first=app(MarketingMaterialRepair::class)->digest($p);
                $this->assertSame($first,app(MarketingMaterialRepair::class)->digest($p));
                throw new \RuntimeException('end read');
            });
        }catch(\RuntimeException $e){$this->assertSame('end read',$e->getMessage());}
        app(MarketingMaterialRepair::class)->digest($p);
        app(MarketingMaterialRepair::class)->digest($p);
    }
}
