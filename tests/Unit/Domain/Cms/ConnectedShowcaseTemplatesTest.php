<?php
namespace Tests\Unit\Domain\Cms;
use Tests\TestCase;
use App\Domain\Cms\PageDocumentContract;
final class ConnectedShowcaseTemplatesTest extends TestCase {
 public function test_bundled_assets_do_not_expand_arbitrary_local_media_access():void {foreach(['/images/cms/templates/connected/../secret.webp','/images/cms/templates/connected/%2e%2e/secret.webp','/images/cms/templates/connected/aaaaaaaaaaaaaaaaaaaaaaaa.webp?x=1','/images/cms/templates/connected/aaaaaaaaaaaaaaaaaaaaaaaa.php','/images/cms/templates/other/aaaaaaaaaaaaaaaaaaaaaaaa.webp'] as $url)$this->assertFalse(\App\Domain\Cms\MediaUrlPolicy::isSafe($url));}
 public function test_all_five_portable_snapshots_are_canonical_and_have_no_database_price_reference():void {
  $snapshots=json_decode(file_get_contents(resource_path('admin/src/cms/connectedShowcaseTemplates.json')),true,512,JSON_THROW_ON_ERROR);$this->assertCount(5,$snapshots);
  foreach($snapshots as $key=>$snapshot){$doc=['title'=>'Template','slug'=>$key,'template'=>'standard',...$snapshot];$this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));foreach($doc['sections'] as $s){$this->assertArrayNotHasKey('pricingSourcePageId',$s);foreach(['mediaUrl',...[]] as $field)if(!empty($s[$field]))$this->assertFileExists(public_path(ltrim($s[$field],'/')));}}
 }
}
