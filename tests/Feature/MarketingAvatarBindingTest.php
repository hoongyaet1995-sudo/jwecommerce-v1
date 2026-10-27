<?php

namespace Tests\Feature;

use App\Models\MarketingRevision;
use App\Models\MarketingStoryboardTask;
use App\Models\User;
use App\Services\Marketing\MarketingAvatarBinding;
use App\Services\Marketing\MarketingAvatarLibrary;
use App\Services\Marketing\MarketingCreativeProfiles;
use App\Services\Marketing\MarketingPreparationBudget;
use App\Services\Marketing\MarketingPresenterLibrary;
use App\Services\Marketing\MarketingStoryboardPreparation;
use App\Services\Marketing\MarketingStoryboardUpload;
use App\Services\Marketing\MarketingStoryboardWorkspace;
use App\Services\Marketing\MarketingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Concerns\MarketingResearchFixtures;
use Tests\TestCase;

class MarketingAvatarBindingTest extends TestCase
{
    use MarketingResearchFixtures,RefreshDatabase;

    public function test_manual_copy_edit_preserves_per_role_person_presence(): void
    {
        $p = $this->researchProject();
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $p->owner_id, 'number' => 1, 'document' => ['scenes' => [['title' => 'Mixed scene', 'visual' => 'Presenter then product', 'duration' => 5, 'voiceover' => '', 'subtitle' => '', 'presenter_required' => true, 'frames' => ['start_frame' => ['required' => true, 'person_presence' => 'visible', 'instruction' => 'Host opens'], 'end_frame' => ['required' => true, 'person_presence' => 'absent', 'instruction' => 'Product only']]]]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $w = app(MarketingStoryboardWorkspace::class);
        $s = $w->initialize($p->id, $p->owner_id, (string) Str::uuid(), $p->version);
        $s = $w->apply($p->id, $p->owner_id, (string) Str::uuid(), 1, ['type' => 'edit', 'scene_id' => $s['scenes'][0]['scene_id'], 'content' => ['seconds' => 5, 'message' => 'Copy edit', 'visual' => 'Presenter then product', 'voiceover' => '', 'subtitle' => '']]);
        $this->assertSame('absent', $s['scenes'][0]['frames']['end_frame']['person_presence']);
    }

    public function test_project_pins_version_and_repeat_selection_does_not_change_story_or_voice(): void
    {
        $p = $this->researchProject();
        $owner = User::findOrFail($p->owner_id);
        $r = MarketingRevision::create(['project_id' => $p->id, 'actor_id' => $owner->id, 'number' => 1, 'document' => ['scenes' => [['visual' => 'Host points at visible product', 'title' => 'Introduction', 'duration' => 5, 'voiceover' => '', 'subtitle' => '', 'presenter_required' => true]]], 'context' => []]);
        $p->update(['current_revision_id' => $r->id]);
        $w = app(MarketingStoryboardWorkspace::class);
        $s = $w->initialize($p->id, $owner->id, (string) Str::uuid(), $p->version);
        $lib = app(MarketingAvatarLibrary::class);
        $a = $lib->saveDraft($owner, $lib->home($owner)->id, (string) Str::uuid(), ['name' => 'Host A', 'kind' => 'synthetic', 'look' => 'Suit']);
        $lib->reference($owner, $a->id, UploadedFile::fake()->image('host.png', 360, 640), true);
        $lib->publish($owner, $a->id, (string) Str::uuid(), true);
        $profiles = app(MarketingCreativeProfiles::class);
        $response = $this->postJson(route('marketing.workspace.storyboard.write', [$p->id, 'profile']), ['request_id' => (string) Str::uuid(), 'version' => 1, 'profile' => ['presenter' => ['mode' => 'preset', 'id' => 'avatar:'.$a->id, 'version' => 1], 'voice' => ['mode' => 'auto']]])->assertOk()->assertJsonStructure(['snapshot', 'options' => ['presenters']]);
        $s = $response->json('snapshot');
        $this->assertContains($s['profile']['presenter']['id'], array_column($response->json('options.presenters'), 'id'));

        $this->assertSame(1, $s['profile']['presenter']['avatar_binding']['avatar_version']);
        $member = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
        $lib->member($owner, $a->library_id, $member->id, 'viewer');
        $other = app(MarketingWorkflow::class)->create($member, ['source_type' => 'theme', 'theme' => 'Separate member project']);
        $otherBinding = app(MarketingAvatarBinding::class)->bind($other->id, $member->id, $a->id, 1);
        $this->assertNotSame($s['profile']['presenter']['reference']['asset_id'], $otherBinding['reference']['asset_id']);
        $this->assertSame($s['profile']['presenter']['reference']['sha256'], $otherBinding['reference']['sha256']);
        $this->assertCount(1, array_filter(app(MarketingPresenterLibrary::class)->options($p->id, $owner->id), fn ($item) => ($item['avatar_binding']['avatar_id'] ?? $item['avatar_id'] ?? null) === $a->id));
        $version = $s['story_version'];
        $presenter = $s['profile']['presenter'];
        $again = $profiles->save($p->id, $owner->id, $version, ['presenter' => ['mode' => 'preset', 'id' => 'avatar:'.$a->id, 'version' => 1], 'voice' => ['mode' => 'auto']]);
        $this->assertSame($version, $again['story_version']);
        $lib->saveDraft($owner, $a->library_id, (string) Str::uuid(), ['id' => $a->id, 'name' => 'Host new', 'kind' => 'synthetic', 'look' => 'New suit']);
        $lib->publish($owner, $a->id, (string) Str::uuid(), true);
        $entry = app(MarketingPresenterLibrary::class)->resolve($p->id, $owner->id, $presenter['id'], 1);
        $this->assertSame(1, $entry['avatar_binding']['avatar_version']);
        $lib->state($owner, $a->id, 'archived');
        $this->assertSame($entry, app(MarketingPresenterLibrary::class)->resolve($p->id, $owner->id, $presenter['id'], 1));
        $current = $w->snapshot($p->id, $owner->id);
        $scene = $current['scenes'][0];
        $auth = app(MarketingPreparationBudget::class)->authorize($p->id, $owner->id, (string) Str::uuid(), $current['story_version'], [$scene['revision_id']], 1000);
        $task = MarketingStoryboardTask::create(['project_id' => $p->id, 'authorization_id' => $auth['id'], 'revision_id' => $scene['revision_id'], 'signature' => $scene['image_signature'], 'status' => 'requesting', 'result' => []]);
        $late = app(MarketingStoryboardUpload::class)->store($p->id, $owner->id, UploadedFile::fake()->image('late.png', 360, 640), true);
        $lib->state($owner, $a->id, 'revoked');
        app(MarketingStoryboardPreparation::class)->finalize($task->id, ['asset' => $late]);
        $this->assertSame('historical', $task->fresh()->status);
        $this->assertNull($w->snapshot($p->id, $owner->id)['scenes'][0]['asset']);
        Http::assertNothingSent();

        $this->expectException(HttpExceptionInterface::class);
        app(MarketingPresenterLibrary::class)->resolve($p->id,$owner->id,$presenter['id'],1);
    }
}
