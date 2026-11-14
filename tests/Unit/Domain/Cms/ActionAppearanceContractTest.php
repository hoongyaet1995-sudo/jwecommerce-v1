<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class ActionAppearanceContractTest extends TestCase
{
 public function test_owned_action_colors_preserve_legacy_colors_and_text_metadata():void {
  $s=['id'=>'s','type'=>'rich_text','title'=>'Keep','isVisible'=>true,'customColors'=>['primaryBtnBg'=>'#111111'],'buttonColors'=>['primaryBg'=>'#234567','primaryText'=>'#fedcba','primaryHover'=>'#654321'],'typography'=>['title'=>['color'=>'#123456','colorOverride'=>true]]];
  $d=['title'=>'QA','slug'=>'appearance-qa','template'=>'standard','sections'=>[$s]];
  $d['style']=['palette'=>['paper'=>'#ffffff','ink'=>'#111111','muted'=>'#666666','accent'=>'#454e37','light'=>'#ffffff','secondary'=>'#eeeeee','secondaryInk'=>'#111111','hover'=>'#333333'],'colorSource'=>'theme','buttonHover'=>true,'buttonHoverColor'=>'#654321'];
  $this->assertSame($d,app(PageDocumentContract::class)->validate($d));
 }
 public function test_action_color_rejects_css_payloads():void {
  $this->expectException(ValidationException::class);
  app(PageDocumentContract::class)->validate(['title'=>'QA','slug'=>'appearance-qa','template'=>'standard','sections'=>[['id'=>'s','type'=>'rich_text','title'=>'Keep','isVisible'=>true,'buttonColors'=>['primaryBg'=>'url(https://example.test/a)']]]]);
 }
}
