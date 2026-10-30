<?php
namespace Tests\Feature;

use App\Services\AI\CmsAiGeneratorService;
use App\Services\AI\AiConnectionService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsAiGeneratorTest extends TestCase
{
    public function test_short_model_identifiers_map_to_real_section_and_item_ids():void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"section_1","title":"Revised","items":[{"id":"item_1","description":"Updated"}]}]}');
        $sections=[['id'=>'actual-long-id','type'=>'feature_cards','title'=>'Original','isVisible'=>true,'items'=>[['id'=>'actual-item-id','title'=>'Item','description'=>'Old']]]];
        $result=app(CmsAiGeneratorService::class)->write($sections,'Rewrite');
        $this->assertSame('actual-long-id',$result[0]['id']);$this->assertSame('actual-item-id',$result[0]['items'][0]['id']);$this->assertSame('Updated',$result[0]['items'][0]['description']);
    }
    public function test_supported_optional_fields_and_cta_labels_use_inspector_contract():void {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"one","subtitle":"EYEBROW","leadText":"Introduction","buttonText":"Explore","secondaryButtonText":"Contact"}]}');
        $result=app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'media_text_split','layoutPreset'=>'editorial-hero','title'=>'Original','buttonLink'=>'/shop','secondaryButtonLink'=>'/contact']],'Write');
        $this->assertSame('EYEBROW',$result[0]['subtitle']);$this->assertSame('Introduction',$result[0]['leadText']);$this->assertSame('/shop',$result[0]['buttonLink']);
    }
    public function test_hidden_or_unsupported_copy_is_rejected():void {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"one","subtitle":"Unused"}]}');
        $this->expectException(ValidationException::class);app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'cta_strip','ctaPreset'=>'industrial-outline','title'=>'Before']],'Write');
    }
    public function test_approved_faq_answers_cannot_be_rewritten():void {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"one","items":[{"id":"answer","description":"Invented"}]}]}');
        $this->expectException(ValidationException::class);app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'faq_accordion','title'=>'FAQ','items'=>[['id'=>'answer','title'=>'Question','description'=>'Approved']]]],'Write');
    }
    public function test_overlong_ai_heading_is_rejected_before_application():void {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn(json_encode(['sections'=>[['id'=>'one','title'=>str_repeat('x',81)]]]));
        $this->expectException(ValidationException::class);app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'hero_banner','title'=>'Before']],'Write');
    }
    public function test_generator_requires_admin_login(): void
    {
        $this->postJson('/admin/api/cms/ai-generator',[])->assertUnauthorized();
    }
    public function test_generated_seed_sections_pass_canonical_contract(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"one","title":"A practical starting point","content":"Discuss your requirements with our team."}]}');
        $document=['title'=>'AI acceptance','slug'=>'ai-acceptance','template'=>'standard','sections'=>[['id'=>'one','type'=>'hero_banner','title'=>'Before','content'=>'','subtitle'=>'','isVisible'=>true,'containerMode'=>'page','sectionBorder'=>'none','motionPreset'=>'subtle-lift','paddingY'=>'normal','backgroundColor'=>'bg-white','textColor'=>'dark','textAlignment'=>'left']]];
        $document['sections']=app(CmsAiGeneratorService::class)->write($document['sections'],'Use only supplied facts.');
        $this->assertSame($document,app(\App\Domain\Cms\PageDocumentContract::class)->validate($document));
    }
    public function test_ai_cannot_change_media_links_identity_or_other_sections(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->once()->andReturn(json_encode(['sections'=>[['id'=>'hero','title'=>'New heading','mediaUrl'=>'https://evil.test/image','buttonLink'=>'javascript:evil()','type'=>'html']]]));
        $sections=[['id'=>'hero','type'=>'hero_banner','title'=>'Before','isVisible'=>true,'mediaUrl'=>'/images/real.png','buttonLink'=>'/shop'],['id'=>'other','type'=>'cta_strip','title'=>'Keep','isVisible'=>true]];
        $this->expectException(ValidationException::class);
        $result=app(CmsAiGeneratorService::class)->write($sections,'Rewrite hero','hero');
        $this->assertSame('New heading',$result[0]['title']);
        $this->assertSame('/images/real.png',$result[0]['mediaUrl']);
        $this->assertSame('/shop',$result[0]['buttonLink']);
        $this->assertSame('hero_banner',$result[0]['type']);
        $this->assertSame($sections[1],$result[1]);
    }
    public function test_invalid_response_is_not_a_successful_candidate(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('not json');
        $this->expectException(ValidationException::class);
        app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'cta_strip','title'=>'Keep','isVisible'=>true]],'Rewrite','one');
    }
    public function test_unrelated_ids_are_rejected(): void
    {
        $this->mock(AiConnectionService::class)->shouldReceive('generateText')->andReturn('{"sections":[{"id":"wrong","title":"Bad"}]}');
        $this->expectException(ValidationException::class);
        app(CmsAiGeneratorService::class)->write([['id'=>'one','type'=>'cta_strip','title'=>'Keep','isVisible'=>true]],'Rewrite','one');
    }
}
