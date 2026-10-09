<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class ReturnsTemplatesTest extends TestCase
{
    public function test_all_registered_returns_templates_and_image_controls_round_trip(): void
    {
        $documents=json_decode(file_get_contents(base_path('tests/Fixtures/Cms/returns-templates.v1.json')),true,512,JSON_THROW_ON_ERROR);
        $this->assertCount(3,$documents);
        foreach ($documents as $document) {
            $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
            $this->assertCount(8,$document['sections']);
        }
        $chapter=$documents[2];
        $photo=$chapter['sections'][3]['items'][3];
        $chapter['sections'][3]['imageSlots']=[['target'=>'item:'.$photo['id'].':imageUrl','imagePosition'=>'top-left','imageRatio'=>'4:3']];
        $chapter['sections'][0]['typography']=['lead'=>['fontSize'=>20,'lineHeight'=>2,'letterSpacing'=>2]];
        $this->assertSame($chapter,app(PageDocumentContract::class)->validate($chapter));
    }
    public function test_process_flow_round_trips_without_changing_items(): void
    {
        $document=['title'=>'Process','slug'=>'process','template'=>'standard','sections'=>[['id'=>'process','type'=>'policy_content','title'=>'Process','isVisible'=>true,'policyPreset'=>'process-flow','items'=>[['id'=>'one','title'=>'First','description'=>'Prepare'],['id'=>'two','title'=>'Second','description'=>'Complete']]]]];
        $this->assertSame($document,app(PageDocumentContract::class)->validate($document));
    }
    public function test_policy_presets_reject_unknown_values_and_wrong_section_owners(): void
    {
        foreach ([['rich_text','guidelines'],['policy_content','unknown']] as [$type,$preset]) {
            $document=['title'=>'Policy','slug'=>'policy','template'=>'standard','sections'=>[['id'=>'policy','type'=>$type,'title'=>'Policy','isVisible'=>true,'policyPreset'=>$preset]]];
            try {app(PageDocumentContract::class)->validate($document);$this->fail('Invalid policy must be rejected.');}
            catch (ValidationException $e) {$this->assertArrayHasKey('sections.0.policyPreset',$e->errors());}
        }
    }
}
