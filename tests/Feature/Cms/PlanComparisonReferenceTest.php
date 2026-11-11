<?php
namespace Tests\Feature\Cms;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Cms\{PageCrudService,PageRuntimePayloadFactory,PageDocumentContract,StarterPricingReference,PlanComparisonReference};
use App\Models\PageDocument;
final class PlanComparisonReferenceTest extends TestCase {
 use RefreshDatabase;
 public function test_comparison_preserves_source_facts_and_published_isolation():void {
  $source=['title'=>'Plans','slug'=>'comparison-source','template'=>'standard','sections'=>[['id'=>'plans','type'=>'pricing_table','pricingPreset'=>'saas-packages','pricingCurrency'=>'MYR','title'=>'Plans','isVisible'=>true,'items'=>[['id'=>'s','title'=>'Starter','price'=>299,'billingLabel'=>'/ month','features'=>['Support workspace']],['id'=>'g','title'=>'Growth','price'=>399,'billingLabel'=>'/ month','description'=>'Everything in Starter, plus AI assistance.','features'=>['AI Auto Reply'],'isHighlighted'=>true,'highlightLabel'=>'AI ENABLED']]],['id'=>'terms','type'=>'feature_cards','title'=>'Terms','content'=>'Exact source terms.','isVisible'=>true]]];
  $page=app(PageCrudService::class)->create($source);
  $s=['id'=>'comparison','type'=>'pricing_table','pricingPreset'=>'plan-comparison','title'=>'Choose','isVisible'=>true,'planContentSource'=>'reference','pricingSourcePageId'=>(string)$page->id,'pricingSourceSectionId'=>'plans','pricingSourceTermsSectionId'=>'terms','buttonText'=>'Compare','buttonLink'=>'/plans','items'=>[['id'=>'one','title'=>'Option one','sourceId'=>'s','icon'=>'Package','buttonText'=>'Explore','linkUrl'=>'/contact'],['id'=>'two','title'=>'Option two','sourceId'=>'g']]];
  $doc=['title'=>'Feature','slug'=>'comparison-consumer','template'=>'standard','sections'=>[$s,['id'=>'faq','type'=>'faq_accordion','faqPreset'=>'framed-faq','title'=>'Answers','isVisible'=>true,'items'=>[['id'=>'q','title'=>'AI?','description'=>'Growth includes AI assistance.']]]]];
  $contract=app(PageDocumentContract::class);$this->assertSame($doc,$contract->validate($doc));$consumer=app(PageCrudService::class)->create($doc);$factory=app(PageRuntimePayloadFactory::class);$service=app(StarterPricingReference::class);
  $facts=$factory->forDraft($consumer)['runtime']['sections'][0]['planComparison'];$this->assertSame([299,399],array_column($facts['plans'],'price'));$this->assertSame('AI ENABLED',$facts['plans'][1]['highlightLabel']);$this->assertSame('Exact source terms.',$facts['terms']);$this->assertSame('unavailable',$service->forDocument($doc,false)[0]['planComparison']['status']);
  $published=$page->draftDocument->replicate();$published->stage=PageDocument::STAGE_PUBLISHED;$published->save();$page->update(['status'=>'published']);$source['sections'][0]['items'][1]['price']=450;$page->draftDocument->update(['document'=>$source]);$this->assertSame(450,$factory->forDraft($consumer)['runtime']['sections'][0]['planComparison']['plans'][1]['price']);$this->assertSame(399,$service->forDocument($doc,false)[0]['planComparison']['plans'][1]['price']);
  $source['sections'][0]['isVisible']=false;$this->assertSame('unavailable',app(PlanComparisonReference::class)->resolve($s,$source)['status']);$source['sections'][0]['isVisible']=true;$source['sections'][1]['isVisible']=false;$this->assertSame('unavailable',app(PlanComparisonReference::class)->resolve($s,$source)['status']);
  $doc['sections'][0]['pricingPreset']='default';$this->assertSame($doc,$contract->validate($doc));$doc['sections'][0]['pricingPreset']='plan-comparison';$this->assertSame($doc,$contract->validate($doc));
 }
}
