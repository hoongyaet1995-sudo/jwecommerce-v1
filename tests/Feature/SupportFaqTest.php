<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportFaqTest extends TestCase
{
    public function test_escalation_settings_are_persisted_validated_and_private(): void
    {
        $this->owner(); $p=$this->payload();
        $p['personality']=app(\App\Services\SupportFaqService::class)->defaultPersonality();
        $p['personality']['humanHandoffTriggers']['consecutiveFailureCount']=3;
        $p['personality']['humanHandoffTriggers']['onFrustrationDetected']=false;
        $p['personality']['fallbackHandoffMessage']['en']='Please wait for our team.';
        $this->putJson('/admin/api/support/faq',$p)->assertOk()->assertJsonPath('personality.humanHandoffTriggers.consecutiveFailureCount',3);
        $this->getJson('/admin/api/support/faq')->assertJsonPath('personality.humanHandoffTriggers.onFrustrationDetected',false)->assertJsonPath('personality.fallbackHandoffMessage.en','Please wait for our team.');
        $this->getJson('/support/faq')->assertJsonMissingPath('personality');
        $p['version']=1;$p['personality']['humanHandoffTriggers']['consecutiveFailureCount']=6;
        $this->putJson('/admin/api/support/faq',$p)->assertUnprocessable();
    }

    public function test_order_fields_persist_privately_and_unsafe_verification_is_rejected():void
    {
        $this->owner();$p=$this->payload();$p['orderLookup']=['isEnabled'=>true,'allowedFields'=>['orderStatus'=>true,'paymentStatus'=>false]];
        $this->putJson('/admin/api/support/faq',$p)->assertOk()->assertJsonPath('orderLookup.allowedFields.paymentStatus',false);
        $this->assertArrayNotHasKey('orderLookup',$this->getJson('/support/faq')->json());
        $p['version']=1;$p['orderLookup']['verificationMethod']='phone_last4';$this->putJson('/admin/api/support/faq',$p)->assertUnprocessable();
    }

    public function test_discount_permissions_persist_without_public_exposure(): void
    {
        $this->owner();
        Schema::create('coupons', function(Blueprint $t){$t->id();});
        DB::table('coupons')->insert(['id'=>1]);
        $p=$this->payload();$p['discountKnowledge']=['isEnabled'=>true,'activeIds'=>['1']];
        $this->putJson('/admin/api/support/faq',$p)->assertOk()->assertJsonPath('discountKnowledge.activeIds.0','1');
        $this->assertArrayNotHasKey('discountKnowledge',$this->getJson('/support/faq')->json());
        $p['version']=1;$p['discountKnowledge']['activeIds']=['999'];
        $this->putJson('/admin/api/support/faq',$p)->assertUnprocessable();
        $this->assertSame(['1'],app(\App\Services\SupportFaqService::class)->read()['discountKnowledge']['activeIds']);
        $p['discountKnowledge']=['isEnabled'=>false,'activeIds'=>[]];
        $this->putJson('/admin/api/support/faq',$p)->assertOk()->assertJsonPath('discountKnowledge.isEnabled',false);
    }

    public function test_product_controls_are_saved_validated_and_private(): void
    {
        $this->owner(); $p=$this->payload();
        $p['productCatalog']=['isEnabled'=>false,'canCheckLiveStock'=>false,'canExplainSizingAndMaterials'=>true,'canRecommendUpsells'=>false,'maxRecommendations'=>2];
        $this->putJson('/admin/api/support/faq',$p)->assertOk()->assertJsonPath('productCatalog.isEnabled',false);
        $this->getJson('/admin/api/support/faq')->assertJsonPath('productCatalog.maxRecommendations',2);
        $this->assertArrayNotHasKey('productCatalog',$this->getJson('/support/faq')->json());
        $p['version']=1;$p['productCatalog']['maxRecommendations']=6;
        $this->putJson('/admin/api/support/faq',$p)->assertUnprocessable();
    }

    public function test_existing_faq_edits_keep_identity_and_update_public_answers(): void
    {
        $this->owner();
        $this->putJson('/admin/api/support/faq', $this->payload())->assertOk();
        $changed = $this->payload();
        $changed['version'] = 1;
        $changed['faq']['items'][0]['question'] = 'Updated washing question';
        $changed['faq']['items'][0]['answer'] = 'Updated washing answer';
        $changed['faq']['items'][0]['keywords'] = ['laundry'];
        $changed['faq']['items'][0]['category'] = 'others';
        $this->putJson('/admin/api/support/faq', $changed)->assertOk()->assertJsonCount(2, 'faq.items');
        $this->getJson('/admin/api/support/faq')->assertJsonPath('faq.items.0.id', 'one')->assertJsonPath('faq.items.0.keywords.0', 'laundry');
        $this->getJson('/support/faq')->assertJsonPath('categories.0.items.0.question', 'Updated washing question')->assertJsonPath('categories.0.items.0.answer', 'Updated washing answer');
    }

    public function test_bot_identity_persists_and_remains_public_when_faq_is_disabled(): void
    {
        $this->owner();
        $p = $this->payload();
        $p['primaryLanguage'] = 'ms';
        $p['identity'] = ['botName' => 'JW Ecom Specialist', 'roleTitle' => 'Customer Care Specialist'];
        $this->putJson('/admin/api/support/faq', $p)->assertOk();
        $this->getJson('/admin/api/support/faq')->assertJsonPath('identity.botName', 'JW Ecom Specialist');
        unset($p['identity']);
        $p['version'] = 1;
        $p['faq']['isEnabled'] = false;
        $this->putJson('/admin/api/support/faq', $p)->assertOk();
        $this->getJson('/support/faq')->assertJsonPath('primaryLanguage', 'ms')->assertJsonPath('identity.roleTitle', 'Customer Care Specialist')->assertJsonCount(0, 'categories');
        $p['version'] = 2;
        $p['identity'] = ['botName' => '', 'roleTitle' => 'Role'];
        $this->putJson('/admin/api/support/faq', $p)->assertUnprocessable();
        $this->getJson('/admin/api/support/faq')->assertJsonPath('version', 2);
    }

    public function test_reply_style_persists_privately_and_rejects_extra_policy_fields(): void
    {
        $this->owner();
        $p = $this->payload();
        $p['personality'] = ['toneOfVoice' => 'concise_efficient', 'customToneInstructions' => 'Be brief', 'maxOutputTokens' => 240];
        $this->putJson('/admin/api/support/faq', $p)->assertOk()->assertJsonPath('personality.maxOutputTokens', 240);
        $this->getJson('/admin/api/support/faq')->assertJsonPath('personality.customToneInstructions', 'Be brief');
        $this->assertArrayNotHasKey('personality', $this->getJson('/support/faq')->json());
        $p['version'] = 1;
        $p['personality']['offlineMessage'] = ['en' => 'Claim offline state'];
        $this->putJson('/admin/api/support/faq', $p)->assertUnprocessable();
    }

    public function test_multilingual_greeting_is_public_even_without_faqs_and_persists(): void
    {
        $this->getJson('/support/faq')->assertOk()->assertJsonPath('greetingMessage.en', 'Hello! Welcome to JW Studio. How can we help you today?');
        $this->owner();
        $p = $this->payload();
        $p['greetingMessage'] = ['en' => 'Hello there!', 'ms' => 'Selamat datang!', 'zh' => '欢迎！'];
        $p['faq']['isEnabled'] = false;
        $this->putJson('/admin/api/support/faq', $p)->assertOk();
        $this->getJson('/support/faq')->assertJsonPath('greetingMessage.zh', '欢迎！')->assertJsonCount(0, 'categories');
        unset($p['greetingMessage']);
        $p['version'] = 1;
        $this->putJson('/admin/api/support/faq', $p)->assertOk()->assertJsonPath('greetingMessage.ms', 'Selamat datang!');
        $p['version'] = 2;
        $p['greetingMessage'] = ['en' => 'Only English'];
        $this->putJson('/admin/api/support/faq', $p)->assertUnprocessable();
    }

    public function test_brand_instructions_and_fallbacks_are_saved_privately(): void
    {
        $this->owner();
        $p = $this->payload();
        $p['personality'] = ['toneOfVoice' => 'friendly_helpful', 'maxOutputTokens' => 250, 'brandMissionPrompt' => 'Be our helpful brand concierge.', 'creativityTemperature' => 0.25, 'unknownAnswerMessage' => ['en' => 'Ask support.', 'ms' => 'Tanya sokongan.', 'zh' => '请询问客服。'], 'serviceUnavailableMessage' => ['en' => 'Try later.', 'ms' => 'Cuba lagi.', 'zh' => '请稍后再试。']];
        $this->putJson('/admin/api/support/faq', $p)->assertOk()->assertJsonPath('personality.brandMissionPrompt', 'Be our helpful brand concierge.')->assertJsonPath('personality.creativityTemperature', 0.25);
        $this->getJson('/support/faq')->assertJsonMissingPath('personality');
        $p['version'] = 1;
        $p['personality']['unknownAnswerMessage'] = ['en' => 'Incomplete'];
        $this->putJson('/admin/api/support/faq', $p)->assertUnprocessable();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('is_admin')->default(false);
            $t->boolean('is_super_admin')->default(false);
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('store_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
    }

    private function payload(): array
    {
        return ['version' => 0, 'faq' => ['isEnabled' => true, 'retrievalDepth' => 'strict', 'categories' => [['id' => 'care', 'label' => 'Care']], 'deletedCategoryIds' => [], 'items' => [['id' => 'one', 'category' => 'care', 'question' => 'Wash?', 'answer' => 'Cold wash.', 'keywords' => ['wash'], 'isActive' => true, 'lastUpdatedAt' => '2026-10-03'], ['id' => 'two', 'category' => 'care', 'question' => 'Hidden', 'answer' => 'Private answer', 'keywords' => [], 'isActive' => false, 'lastUpdatedAt' => '2026-10-03']]]];
    }

    private function owner(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => true]));
    }

    public function test_publishes_active_faqs_with_safe_public_fields_and_rejects_stale_writes(): void
    {
        $this->owner();
        $this->putJson('/admin/api/support/faq', $this->payload())->assertOk()->assertJsonPath('version', 1);
        $this->getJson('/support/faq')->assertOk()->assertJsonCount(1, 'categories.1.items')->assertJsonPath('categories.1.items.0.answer', 'Cold wash.')->assertJsonMissing(['answer' => 'Private answer']);
        $this->putJson('/admin/api/support/faq', $this->payload())->assertStatus(409);
        $this->getJson('/admin/api/support/faq')->assertJsonPath('faq.items.0.question', 'Wash?');
    }

    public function test_deleted_category_moves_faqs_to_others_and_disabled_scope_is_private(): void
    {
        $this->owner();
        $p = $this->payload();
        $p['faq']['deletedCategoryIds'] = ['care'];
        $this->putJson('/admin/api/support/faq', $p)->assertOk()->assertJsonPath('faq.items.0.category', 'others');
        $this->getJson('/support/faq')->assertJsonPath('categories.0.label', 'Others')->assertJsonCount(1, 'categories');
        $p['version'] = 1;
        $p['faq']['isEnabled'] = false;
        $this->putJson('/admin/api/support/faq', $p)->assertOk();
        $this->getJson('/support/faq')->assertJsonCount(0, 'categories');
    }

    public function test_only_owner_can_publish_and_bad_payload_cannot_replace_settings(): void
    {
        $this->getJson('/admin/api/support/faq')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'is_super_admin' => false]));
        $this->putJson('/admin/api/support/faq', $this->payload())->assertForbidden();
        $this->owner();
        $p = $this->payload();
        $p['faq']['items'][0]['answer'] = '';
        $this->putJson('/admin/api/support/faq', $p)->assertUnprocessable();
        $this->getJson('/support/faq')->assertJsonCount(0, 'categories');
    }
}
