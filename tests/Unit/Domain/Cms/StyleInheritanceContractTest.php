<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
class StyleInheritanceContractTest extends TestCase
{
 public function test_explicit_page_inheritance_and_local_buttons_round_trip():void
 {
  $document=['title'=>'Style QA','slug'=>'style-qa','template'=>'standard','sections'=>[['id'=>'s','type'=>'rich_text','title'=>'Keep title','subtitle'=>'Keep label','isVisible'=>true,'typography'=>['title'=>['inheritPage'=>true,'fontSize'=>36]],'surface'=>'page','buttonRadius'=>6,'buttonSize'=>'large','customColors'=>['primaryBtnBg'=>'#123456'],'colorOverrides'=>['primaryBtnBg']]]];
  $document['style']=['palette'=>['paper'=>'#ffffff','ink'=>'#111111','muted'=>'#666666','accent'=>'#454e37','light'=>'#ffffff','secondary'=>'#eeeeee','secondaryInk'=>'#111111','hover'=>'#333333'],'unifiedButtons'=>false];
  $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
 }
}
