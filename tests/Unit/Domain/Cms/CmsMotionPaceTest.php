<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class CmsMotionPaceTest extends TestCase {
 public function test_pace_round_trips_and_legacy_documents_stay_unchanged():void {
  $base=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/page-document-all-sections.v1.json')),true,flags:JSON_THROW_ON_ERROR);$base['sections']=[['id'=>'x','type'=>'rich_text','title'=>'Original','isVisible'=>true,'motionPreset'=>'none']];
  $contract=app(PageDocumentContract::class);$this->assertSame($base,$contract->validate($base));
  foreach(['smooth','relaxed'] as $pace){$next=$base;$next['sections'][0]['motionPace']=$pace;$this->assertSame($next,$contract->validate($next));}
 }
}
