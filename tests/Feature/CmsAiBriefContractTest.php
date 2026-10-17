<?php
namespace Tests\Feature;

use App\Services\AI\CmsAiBriefContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsAiBriefContractTest extends TestCase
{
    public function test_question_first_response_keeps_a_provisional_outline_until_answers():void
    {
        $result=(new CmsAiBriefContract)->normalize(['summary'=>'Need direction','questions'=>[['id'=>'audience','type'=>'text','question'=>'Audience?']],'sections'=>[]],['one','two']);
        $this->assertTrue($result['provisional']);
        $this->assertSame(['one','two'],array_column($result['sections'],'id'));
    }
    public function test_four_question_types_and_optional_unknown_answers_are_supported(): void
    {
        $contract=new CmsAiBriefContract;
        $brief=$contract->normalize(['summary'=>'Plan','questions'=>[
            ['id'=>'business','type'=>'text','question'=>'Business name?','required'=>true],
            ['id'=>'direction','type'=>'select','question'=>'Direction?','options'=>['Editorial','Clean']],
            ['id'=>'show','type'=>'radio','question'=>'Show products?','options'=>['Yes','No']],
            ['id'=>'audience','type'=>'multiple_select','question'=>'Audience?','options'=>['Business','Consumer']],
        ],'sections'=>[['id'=>'one','purpose'=>'Introduce']]],['one']);
        $answers=$contract->answers($brief['questions'],['business'=>'JTG','direction'=>'Clean','show'=>'Yes','audience'=>['Business','Consumer']]);
        $this->assertSame(['Business','Consumer'],$answers['audience']);
        $this->assertSame('text',$brief['questions'][0]['type']);
    }
    public function test_required_question_cannot_be_skipped(): void
    {
        $this->expectException(ValidationException::class);
        (new CmsAiBriefContract)->answers([['id'=>'one','type'=>'text','question'=>'Name?','required'=>true,'options'=>[]]],[]);
    }
    public function test_options_cannot_be_injected_by_client(): void
    {
        $this->expectException(ValidationException::class);
        (new CmsAiBriefContract)->answers([['id'=>'one','type'=>'radio','question'=>'Choice?','required'=>true,'options'=>['Yes','No']]],['one'=>'Other']);
    }
    public function test_legacy_question_strings_are_upgraded_but_invalid_types_rejected(): void
    {
        $c=new CmsAiBriefContract;
        $this->assertSame('text',$c->normalize(['summary'=>'Plan','questions'=>['Name?'],'sections'=>[['id'=>'one','purpose'=>'Intro']]],['one'])['questions'][0]['type']);
        $this->expectException(ValidationException::class);
        $c->normalize(['summary'=>'Plan','questions'=>[['id'=>'one','type'=>'execute','question'=>'Run?']],'sections'=>[['id'=>'one','purpose'=>'Intro']]],['one']);
    }
}
