<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingWriter;
class MarketingDirectorWriterTest extends TestCase
{
 public function test_director_output_uses_separate_contract_not_storyboard_rewrite():void{
  $result=app(MarketingWriter::class)->validateOutput(MarketingDirectorContractTest::directorOutput(),'director',['director_facts'=>MarketingDirectorContractTest::facts()]);
  $this->assertSame('director-v1',$result['schema_version']);$this->assertArrayNotHasKey('production',$result);
 }
}
