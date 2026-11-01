<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class SaasPackagesContractTest extends TestCase
{
 private function doc():array{return ['title'=>'Plans','slug'=>'plans','template'=>'landing','sections'=>[['id'=>'plans','type'=>'pricing_table','title'=>'Plans','isVisible'=>true,'pricingPreset'=>'saas-packages','pricingCurrency'=>'MYR','items'=>[['id'=>'one','title'=>'Starter','price'=>299,'billingLabel'=>'/ month'],['id'=>'two','title'=>'Custom','pricingMode'=>'contact','pricingLabel'=>"Let's talk"]]]]];}
 public function test_saas_document_round_trips():void{$d=$this->doc();$this->assertSame($d,app(PageDocumentContract::class)->validate($d));}
 public function test_pricing_fields_reject_wrong_owner():void{$d=$this->doc();$d['sections'][0]['type']='feature_cards';$this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate($d);}
 public function test_negative_pricing_is_rejected():void{$d=$this->doc();$d['sections'][0]['items'][0]['price']=-1;$this->expectException(ValidationException::class);app(PageDocumentContract::class)->validate($d);}
 public function test_incomplete_draft_is_not_publishable():void{$d=$this->doc();unset($d['sections'][0]['items'][0]['price']);app(PageDocumentContract::class)->validate($d);$this->expectException(ValidationException::class);app(PageDocumentContract::class)->assertPublishable($d,['sections'=>[],'catalog'=>[]]);}
}
