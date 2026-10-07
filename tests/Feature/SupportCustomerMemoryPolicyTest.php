<?php
namespace Tests\Feature;

use App\Models\{SupportConversation,SupportCustomerMemory};
use App\Services\Support\SupportCustomerMemoryPolicy;
use Tests\TestCase;

class SupportCustomerMemoryPolicyTest extends TestCase
{
    public function test_defaults_limits_and_anonymous_isolation(): void
    {
        $policy=new SupportCustomerMemoryPolicy();
        $this->assertSame(['enabled'=>false,'retentionDays'=>30],$policy->settings([]));
        $this->assertSame(90,$policy->settings(['customerMemory'=>['retentionDays'=>999]])['retentionDays']);
        $this->assertSame(7,$policy->settings(['customerMemory'=>['retentionDays'=>0]])['retentionDays']);
        $this->assertNull($policy->scope(new SupportConversation(['channel'=>'storefront','owner_hash'=>'anonymous'])));
    }
    public function test_content_encrypted_and_hidden(): void
    {
        $record=new SupportCustomerMemory();$record->content='Prefers oak furniture';
        $this->assertStringNotContainsString('Prefers oak furniture',$record->getAttributes()['content']);
        $this->assertSame('Prefers oak furniture',$record->content);
        $this->assertArrayNotHasKey('content',$record->toArray());
    }
}
