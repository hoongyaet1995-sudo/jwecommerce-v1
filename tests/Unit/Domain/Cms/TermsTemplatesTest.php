<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class TermsTemplatesTest extends TestCase
{
 public function test_all_three_terms_templates_round_trip(): void
 {
  $documents=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/terms-templates.v1.json')),true,512,JSON_THROW_ON_ERROR);
  $this->assertCount(3,$documents);
  foreach($documents as $document){$this->assertSame($document,app(PageDocumentContract::class)->validate($document));}
 }
 public function test_document_layout_cannot_be_attached_to_rich_text(): void
 {
  $document=['title'=>'Terms','slug'=>'terms','template'=>'standard','sections'=>[['id'=>'terms','type'=>'rich_text','title'=>'Terms','isVisible'=>true,'policyPreset'=>'directory']]];
  try{app(PageDocumentContract::class)->validate($document);$this->fail('Wrong owner must be rejected.');}
  catch(ValidationException $error){$this->assertArrayHasKey('sections.0.policyPreset',$error->errors());}
 }
}
