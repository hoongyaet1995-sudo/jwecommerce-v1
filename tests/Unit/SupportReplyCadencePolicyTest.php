<?php
namespace Tests\Unit;
use Tests\TestCase;
use App\Services\Support\SupportReplyCadencePolicy;
class SupportReplyCadencePolicyTest extends TestCase
{
    public function test_defaults_and_bounds_preserve_default_off():void
    {
        $p=app(SupportReplyCadencePolicy::class);
        $this->assertSame(['enabled'=>false,'collectionSeconds'=>2,'intervalSeconds'=>1,'maxSegments'=>3],$p->settings([]));
        $this->assertSame(['enabled'=>true,'collectionSeconds'=>3,'intervalSeconds'=>0,'maxSegments'=>1],$p->settings(['replyCadence'=>['enabled'=>true,'collectionSeconds'=>99,'intervalSeconds'=>-1,'maxSegments'=>0]]));
    }
}
