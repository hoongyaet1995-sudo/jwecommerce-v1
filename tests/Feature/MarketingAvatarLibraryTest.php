<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Marketing\MarketingAvatarLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class MarketingAvatarLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_library_draft_and_publication_are_versioned_and_member_scoped(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => true]);
        $outsider = User::factory()->create(['is_admin' => true]);
        $lib = app(MarketingAvatarLibrary::class);
        $scope = $lib->home($owner);
        $draft = $lib->saveDraft($owner, $scope->id, (string) Str::uuid(), ['name' => 'Presenter', 'kind' => 'synthetic', 'description' => 'Approved appearance', 'look' => 'Dark suit']);
        $this->assertSame('draft', $draft->status);
        $this->assertSame([], $lib->listAvailable($owner));
        $lib->reference($owner, $draft->id, UploadedFile::fake()->image('person.png', 360, 640), true);
        $v1 = $lib->publish($owner, $draft->id, (string) Str::uuid(), true);
        $this->assertSame(1, $v1->version);
        $lib->member($owner, $scope->id, $member->id, 'viewer');
        $this->assertCount(1, $lib->listAvailable($member));
        $this->assertSame([], $lib->listAvailable($outsider));
        $lib->saveDraft($owner, $scope->id, (string) Str::uuid(), ['id' => $draft->id, 'name' => 'Renamed', 'kind' => 'synthetic', 'description' => 'New description', 'look' => 'Same suit']);
        $v2 = $lib->publish($owner, $draft->id, (string) Str::uuid(), true);
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->references, $v1->fresh()->references);
        $lib->state($owner, $draft->id, 'archived');
        $this->assertSame([], $lib->listAvailable($owner));
        $this->assertSame($v1->identity_digest, $lib->resolveVersion($owner, $draft->id, 1, true)->identity_digest);
        Http::assertNothingSent();
        file_put_contents(Storage::disk('local')->path($v1->references['identity']['path']), 'tampered');
        $this->expectException(HttpExceptionInterface::class);
        $lib->resolveVersion($owner, $draft->id, 1, true);
    }

    public function test_description_only_cannot_be_published_as_a_ready_person(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $lib = app(MarketingAvatarLibrary::class);
        $a = $lib->saveDraft($owner, $lib->home($owner)->id, (string) Str::uuid(), ['name' => 'No photo', 'kind' => 'synthetic']);
        $this->expectException(HttpExceptionInterface::class);
        $lib->publish($owner,$a->id,(string) Str::uuid(),true);
    }
}
