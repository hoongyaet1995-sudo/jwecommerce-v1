<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
final class PrivacyTemplatesTest extends TestCase
{
 public function test_all_three_privacy_templates_round_trip(): void
 {
  $documents=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/privacy-templates.v1.json')),true,512,JSON_THROW_ON_ERROR);
  $this->assertCount(3,$documents);
  foreach($documents as $document){$this->assertSame($document,app(PageDocumentContract::class)->validate($document));}
 }
}
