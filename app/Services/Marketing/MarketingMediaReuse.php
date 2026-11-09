<?php

namespace App\Services\Marketing;

use App\Domain\Marketing\MarketingPermission as Permission;
use App\Models\MarketingAsset;
use App\Models\MarketingAvatar;
use App\Models\MarketingProject;
use App\Models\MarketingStoryboardWorkspace;
use App\Models\MarketingVideoClip;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Live source references; selecting never approves or publishes media. */
class MarketingMediaReuse
{
    private array $checking = [];

    public function resolve(User $actor, string $id, bool $bound = false): array
    {
        abort_unless(Permission::allows($actor), 403);
        abort_if(count($this->checking) >= 32 || isset($this->checking[$id]), 409, 'This source contains a circular reuse reference.');
        $this->checking[$id] = $bound;
        try {
            [$a,$file] = app(MarketingMediaAccess::class)->file($actor, $id);
            abort_unless(app(MarketingMediaAccess::class)->downloadable($actor, $a), 409, 'Approve this completed video in its source project before reusing it.');
            if ($a->source_type === 'marketing_video_clips') {
                abort_unless(MarketingVideoClip::whereKey($a->source_id)->where('status', 'completed')->exists(), 409, 'This video clip is not complete yet.');
            }
            if ($a->avatar_id) {
                $avatar = MarketingAvatar::findOrFail($a->avatar_id);
                abort_unless(in_array($a->source_type, ['marketing_avatar_versions', 'marketing_storyboard_presenters'], true) && ($avatar->status === 'ready' || ($bound && $avatar->status === 'archived')), 409, 'Choose a published avatar version with current usage rights.');
            }
            if ($a->project_id) {
                $profile = MarketingStoryboardWorkspace::where('project_id', $a->project_id)->value('profile');
                if (is_string($profile)) {
                    $profile = json_decode($profile, true);
                }$binding = $profile['presenter']['avatar_binding'] ?? null;
                if ($binding) {
                    app(MarketingAvatarBinding::class)->assertUsable($a->project_id, $a->owner_id, $binding);
                }
            }

            return [$a, $file];
        } catch (ModelNotFoundException $e) {
            abort(404, 'This library source is no longer available.');
        } finally {
            unset($this->checking[$id]);
        }
    }

    public function descriptor(User $actor, string $id, bool $bound = true): array
    {
        [$a] = $this->resolve($actor, $id, $bound);

        return ['id' => 'library:'.$a->id, 'title' => $a->title, 'kind' => $a->kind, 'src' => route('marketing.posts.media', ['asset' => 'library:'.$a->id]), 'seconds' => (float) ($a->seconds ?? 0), 'note' => ($a->origin === 'ai' ? 'AI generated' : 'Uploaded').' · '.$a->stage, 'selectable' => true];
    }

    public function listing(User $actor, string $search = '', int $page = 1, string $kind = 'all'): array
    {
        abort_unless(Permission::allows($actor), 403);
        $q = app(MarketingMediaAccess::class)->query($actor)->when($kind !== 'all', fn ($q) => $q->where('kind', $kind));
        if (trim($search) !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)).'%';
            $q->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("project_name LIKE ? ESCAPE '!'", [$like]));
        }
        $rows = $q->orderByDesc('occurred_at')->orderByDesc('id')->offset(($page - 1) * 24)->limit(25)->get();
        $more = $rows->count() > 24;
        $out = [];
        foreach ($rows->take(24) as $a) {
            try {
                $out[] = $this->descriptor($actor, $a->id, false);
            } catch (HttpExceptionInterface $e) {
                $out[] = ['id' => 'library:'.$a->id, 'title' => $a->title, 'kind' => $a->kind, 'src' => route('marketing.library.file', $a->id), 'seconds' => (float) ($a->seconds ?? 0), 'note' => 'Unavailable for reuse: review source approval, permissions or file integrity.', 'selectable' => false];
            }
        }

        return ['assets' => $out, 'page' => $page, 'more' => $more];
    }

    public function import(User $actor, string $project, string $id, bool $authorized): array
    {
        abort_unless(Permission::allows($actor, Permission::EDIT), 403);
        abort_unless($authorized, 422, 'Confirm your right to use this media.');
        MarketingProject::where('owner_id', $actor->id)->findOrFail($project);
        [$source,$file] = $this->resolve($actor, $id);

        return DB::transaction(function () use ($actor, $project, $id, $source, $file) {
            MarketingProject::where('owner_id', $actor->id)->lockForUpdate()->findOrFail($project);
            $old = DB::table('marketing_media_reuses')->where('project_id', $project)->where('library_asset_id', $id)->first();
            if ($old) {
                return app(MarketingStoryboardUpload::class)->reference($project, $old->project_asset_id);
            }
            $ext = match ($source->mime) {
                'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp','video/mp4' => 'mp4','video/quicktime' => 'mov','video/webm' => 'webm',default => abort(422, 'This format cannot be used in a storyboard.')
            };
            $upload = new UploadedFile($file, 'Library-'.Str::slug($source->title).'.'.$ext, $source->mime, null, true);
            if ($source->mime === 'video/mp4') {
                // Existing MP4 reuse is a private file copy, not a render or supplier operation.
                $metadata = app(\App\Services\ManagedMediaIngest::class)->inspect($upload);
                abort_unless($metadata['mime'] === 'video/mp4' && hash_equals($source->sha256, $metadata['checksum']), 409, 'The selected video changed.');
                app(MarketingPostMedia::class)->duration(file_get_contents($file));
            } else {
                if ($source->kind === 'video') {
                    abort_unless(config('marketing.production.ffprobe_binary'), 422, 'This video format requires configured media tools. Select an MP4 for manual reuse on this host.');
                }
                app(MarketingStoryboardUpload::class)->validateFile($upload);
            }
            $path = 'marketing/'.$project.'/storyboard/library-'.$id.'-'.$source->sha256.'.'.$ext;
            $disk = Storage::disk('local');
            if (! $disk->exists($path)) {
                $stream = fopen($file, 'rb');
                try {
                    abort_unless($disk->put($path, $stream), 409, 'The project material could not be saved.');
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }
            abort_unless(hash_equals($source->sha256, hash_file('sha256', $disk->path($path))), 409, 'The selected file changed.');
            $asset = MarketingAsset::withoutEvents(fn () => MarketingAsset::create(['project_id' => $project, 'name' => mb_substr($source->title, 0, 180), 'path' => $path, 'mime' => $source->mime, 'size' => $source->bytes, 'authorized' => true]));
            DB::table('marketing_media_reuses')->insert(['id' => (string) Str::uuid(), 'owner_id' => $actor->id, 'project_id' => $project, 'library_asset_id' => $id, 'project_asset_id' => $asset->id, 'source_sha256' => $source->sha256, 'created_at' => now(), 'updated_at' => now()]);
            abort_unless(hash_equals($source->sha256, hash_file('sha256', Storage::disk('local')->path($asset->path))), 409, 'The selected file changed. Select it again.');
            app(MarketingMediaSources::class)->safeCapture($asset);

            return app(MarketingStoryboardUpload::class)->reference($project, $asset->id);
        });
    }

    public function assertPostSources(int $user, array $keys): void
    {
        foreach ($keys as $key) {
            if (is_string($key) && str_starts_with($key, 'library:')) {
                $this->resolve(User::findOrFail($user), substr($key, 8), true);
            }
        }
    }

    public function assertLinked(MarketingAsset $asset): void
    {
        $link = DB::table('marketing_media_reuses')->where('project_asset_id', $asset->id)->first();
        if (! $link) {
            return;
        }
        // A new selection must retain that restriction through every project copy.
        $bound = $this->checking === [] || end($this->checking);
        [$source] = $this->resolve(User::findOrFail($link->owner_id), $link->library_asset_id, $bound);
        abort_unless($asset->project_id === $link->project_id && hash_equals($link->source_sha256, $source->sha256), 409, 'This material no longer matches its library source.');
        $path = app(MarketingMediaRegister::class)->localPath($asset->path);
        abort_unless($path && hash_equals($link->source_sha256, hash_file('sha256', $path)), 409, 'The reused project file changed.');
    }
}
