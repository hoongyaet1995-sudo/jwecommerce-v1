<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportFaqTest extends TestCase
{
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
        $p['personality']['brandMissionPrompt'] = 'Invent free worldwide delivery';
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
