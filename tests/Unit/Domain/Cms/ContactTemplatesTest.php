<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class ContactTemplatesTest extends TestCase
{
 public function test_all_three_templates_and_location_changes_round_trip(): void
 {
  $docs=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/contact-templates.v1.json')),true,512,JSON_THROW_ON_ERROR);
  $this->assertCount(3,$docs);
  foreach($docs as $doc){$this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));$this->assertCount(7,$doc['sections']);}
  $doc=$docs[2];$doc['sections'][2]['contactAddress']='Example visitor address';$doc['sections'][2]['mapVisible']=false;$doc['sections'][2]['contactAddressIcon']='Home';$doc['sections'][2]['mapEmbedUrl']='https://www.google.com/maps/embed?pb=example';
  $this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));
 }
 public function test_location_fields_cannot_be_added_to_unrelated_sections(): void
 {
  $doc=['title'=>'Contact','slug'=>'contact','template'=>'standard','sections'=>[['id'=>'qa','type'=>'rich_text','title'=>'Contact','isVisible'=>true,'mapVisible'=>true]]];
  $this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate($doc);
 }
}

