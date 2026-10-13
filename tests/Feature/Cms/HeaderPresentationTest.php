<?php
namespace Tests\Feature\Cms;
use App\Domain\Cms\HeaderPresentation;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class HeaderPresentationTest extends TestCase
{
 use \Illuminate\Foundation\Testing\RefreshDatabase;
 public function test_global_settings_normalize_overlay_preferences_and_reject_unsafe_logos():void {
  $service=app(\App\Services\StorefrontShellSettingsService::class);
  $input=$service->live();
  $input['headerPresentation']=['mode'=>'translucent','tone'=>'light'];
  $input['overlayLogos']=['light'=>'javascript:alert(1)','dark'=>null];
  try {$service->save($input);$this->fail('Unsafe logo accepted');}catch(ValidationException $e){$this->assertArrayHasKey('overlayLogos.light',$e->errors());}
 }
 public function test_global_preferences_persist():void {
  $service=app(\App\Services\StorefrontShellSettingsService::class);
  $input=$service->live();
  $input['headerPresentation']=['mode'=>'transparent','tone'=>'light'];
  $input['overlayLogos']=['light'=>'/storage/cms/media/11111111-1111-4111-8111-111111111111.png','dark'=>null];
  $service->save($input);
  $this->assertSame($input['headerPresentation'],$service->live()['headerPresentation']);
  $this->assertSame($input['overlayLogos'],$service->live()['overlayLogos']);
 }
 public function test_page_header_contract_and_resolution():void {
  $doc=['title'=>'Overlay','slug'=>'overlay','template'=>'standard','headerPresentation'=>['mode'=>'transparent','tone'=>'light'],'sections'=>[['id'=>'hero','type'=>'hero_slider','title'=>'Hero','isVisible'=>true]]];
  $this->assertSame($doc,app(PageDocumentContract::class)->validate($doc));
  $this->assertSame(['mode'=>'transparent','tone'=>'light'],HeaderPresentation::resolve($doc,[]));
  $doc['sections'][0]['isVisible']=false;
  $this->assertSame('normal',HeaderPresentation::resolve($doc,[])['mode']);
  $doc['headerPresentation']['mode']='invalid';
  $this->expectException(ValidationException::class);
  app(PageDocumentContract::class)->validate($doc);
 }
 public function test_global_header_inheritance():void {
  $doc=['sections'=>[['type'=>'hero_slider','isVisible'=>true]],'headerPresentation'=>['mode'=>'inherit','tone'=>'dark']];
  $this->assertSame(['mode'=>'translucent','tone'=>'light'],HeaderPresentation::resolve($doc,['headerPresentation'=>['mode'=>'translucent','tone'=>'light']]));
  $doc['headerPresentation']['mode']='normal';
  $this->assertSame('transparent',HeaderPresentation::resolve($doc,['headerPresentation'=>['mode'=>'transparent','tone'=>'light']])['mode']);
 }
}
