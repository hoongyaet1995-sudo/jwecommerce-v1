<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class LogisticsTemplateTest extends TestCase
{
 public function test_logistics_template_round_trips_through_canonical_ownership_contract(): void
 {
  $snapshot=json_decode(file_get_contents(base_path('resources/admin/src/cms/logisticsTemplate.json')),true,512,JSON_THROW_ON_ERROR);
  $document=['title'=>'Logistics','slug'=>'logistics-starter','template'=>'standard','style'=>$snapshot['style'],'sections'=>$snapshot['sections']];
  try {$validated=app(PageDocumentContract::class)->validate($document);} catch(ValidationException $e){$this->fail(json_encode($e->errors()));}
  $this->assertSame($document,$validated);
  $this->assertCount(8,$document['sections']);
 }
 public function test_industrial_presets_reject_wrong_section_owners(): void
 {
  foreach ([['type'=>'rich_text','featureCardsPreset'=>'industrial-cards'],['type'=>'feature_cards','faqPreset'=>'industrial-split']] as $invalid) {
   $document=['title'=>'Invalid','slug'=>'invalid','template'=>'standard','sections'=>[array_merge(['id'=>'one','title'=>'Invalid','isVisible'=>true],$invalid)]];
   try {app(PageDocumentContract::class)->validate($document);$this->fail('Wrong owner accepted');}
   catch(ValidationException $exception){$this->assertNotEmpty($exception->errors());}
  }
 }
}
