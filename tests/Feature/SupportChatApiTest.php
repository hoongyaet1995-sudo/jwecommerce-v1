<?php
namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportChatApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::setDefaultConnection('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('password');
            $t->boolean('is_admin')->default(false); $t->boolean('is_super_admin')->default(false);
            $t->timestamp('email_verified_at')->nullable(); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable(); $t->string('phone')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_10_02_160000_create_support_chat_tables.php'))->up();
    }
    private function payload(string $content = 'Hello from storefront'): array
    {
        return ['content' => $content, 'client_message_id' => (string) Str::uuid()];
    }
    public function test_guest_roundtrip_is_real_persistent_and_private(): void
    {
        $this->withSession(['support_guest_key' => 'guest-a']);
        $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation', null);
        $payload = $this->payload();
        $id = $this->postJson('/support/chat/messages', $payload)->assertOk()->json('conversation.id');
        $this->postJson('/support/chat/messages', $payload)->assertOk();
        $this->assertDatabaseCount('support_messages', 1);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson('/admin/api/support/conversations')->assertOk()
            ->assertJsonPath('conversations.0.customer.name', 'Guest')->assertJsonPath('conversations.0.unreadCount', 1);
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload('Real staff reply'))->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'Private note'])->assertOk();
        $public = $this->getJson('/support/chat')->assertOk()->assertJsonPath('conversation.messages.1.content', 'Real staff reply')->json();
        $this->assertArrayNotHasKey('internalNotes', $public['conversation']);
        $this->assertStringNotContainsString('Private note', json_encode($public));
        $this->assertStringNotContainsString('owner_hash', json_encode($public));
        $this->assertDatabaseHas('support_staff_notes', ['content' => 'Private note']);
    }
    public function test_other_guest_cannot_choose_or_read_a_conversation(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $id = $this->postJson('/support/chat/messages', $this->payload('Secret A'))->json('conversation.id');
        $this->withSession(['support_guest_key' => 'b']);
        $this->getJson('/support/chat?conversation_id='.$id)->assertJsonPath('conversation', null);
        $this->postJson('/support/chat/messages', $this->payload('Guest B') + ['conversation_id' => $id, 'name' => 'Spoofed'])->assertOk();
        $this->assertDatabaseCount('support_conversations', 2);
        $admin = User::factory()->create(['is_admin' => true]);
        foreach ($this->actingAs($admin)->getJson('/admin/api/support/conversations')->json('conversations') as $conversation) {
            $this->assertSame('Guest', $conversation['customer']['name']);
        }
    }
    public function test_admin_authentication_and_authorization_protect_every_action(): void
    {
        $this->getJson('/admin/api/support/conversations')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->getJson('/admin/api/support/conversations')->assertUnauthorized();
        $id = (string) Str::uuid();
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload())->assertUnauthorized();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertUnauthorized();
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertUnauthorized();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'private'])->assertUnauthorized();
    }
    public function test_member_identity_is_server_owned_and_does_not_merge_guest_threads(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $this->postJson('/support/chat/messages', $this->payload('Guest message'))->assertOk();
        $member = User::factory()->create(['name' => 'Real Member']);
        $this->actingAs($member, 'customer')->getJson('/support/chat')->assertJsonPath('conversation', null);
        $this->postJson('/support/chat/messages', $this->payload('Member message'))->assertOk();
        $this->assertDatabaseHas('support_conversations', ['customer_user_id' => $member->id]);
        $other = User::factory()->create();
        $this->actingAs($other, 'customer')->getJson('/support/chat')->assertJsonPath('conversation', null);
    }
    public function test_read_watermark_resolution_reopening_and_idempotency(): void
    {
        $this->withSession(['support_guest_key' => 'a']);
        $id = $this->postJson('/support/chat/messages', $this->payload())->json('conversation.id');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertJsonPath('conversation.unreadCount', 0);
        $this->postJson('/support/chat/messages', $this->payload('New after read'))->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/read", ['through_message_id' => 1])->assertJsonPath('conversation.unreadCount', 1);
        $reply = $this->payload('Reply');
        $this->postJson("/admin/api/support/conversations/$id/messages", $reply)->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/messages", $reply)->assertOk();
        $this->assertDatabaseCount('support_messages', 3);
        $this->postJson("/admin/api/support/conversations/$id/messages", array_replace($reply, ['content' => 'Changed']))->assertUnprocessable();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertOk();
        $this->postJson("/admin/api/support/conversations/$id/messages", $this->payload())->assertUnprocessable();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'open'])->assertOk();
        $this->patchJson("/admin/api/support/conversations/$id/status", ['status' => 'resolved'])->assertOk();
        $this->postJson('/support/chat/messages', $this->payload('Reopen from guest'))->assertJsonPath('conversation.status', 'open');
    }
    public function test_validation_and_safe_text_storage(): void
    {
        $this->postJson('/support/chat/messages', $this->payload(''))->assertUnprocessable();
        $this->postJson('/support/chat/messages', $this->payload(str_repeat('x', 2001)))->assertUnprocessable();
        $this->postJson('/support/chat/messages', ['content' => 'Hi', 'client_message_id' => 'bad'])->assertUnprocessable();
        $this->postJson('/support/chat/messages', $this->payload('<img src=x onerror=alert(1)>'))->assertOk()
            ->assertJsonPath('conversation.messages.0.content', '<img src=x onerror=alert(1)>');
    }
    public function test_incremental_inbox_keeps_timestamp_boundary_and_note_changes(): void
    {
        $this->withSession(['support_guest_key' => 'incremental']);
        $id = $this->postJson('/support/chat/messages', $this->payload())->json('conversation.id');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $since = $this->getJson('/admin/api/support/conversations')->assertOk()->json('syncedAt');
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()->assertJsonCount(1, 'conversations');
        $this->travel(2)->seconds();
        $this->postJson("/admin/api/support/conversations/$id/notes", ['content' => 'Delta note'])->assertOk();
        $this->getJson('/admin/api/support/conversations?since='.urlencode($since))->assertOk()
            ->assertJsonPath('conversations.0.internalNotes.0.content', 'Delta note');
        $this->getJson('/admin/api/support/conversations?since=invalid')->assertUnprocessable();
    }
    public function test_real_csrf_gate_rejects_missing_token(): void
    {
        $this->app->instance('env', 'local');
        $this->withSession(['_token' => 'support-csrf-test']);
        $this->postJson('/support/chat/messages', $this->payload())->assertStatus(419);
        $this->postJson('/support/chat/messages', $this->payload(), ['X-CSRF-TOKEN' => 'support-csrf-test'])->assertOk();
    }
}
