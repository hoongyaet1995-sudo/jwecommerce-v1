<?php

namespace App\Services\Marketing;

use App\Models\MarketingAsset;
use App\Models\MarketingAvatar;
use App\Models\MarketingAvatarVersion;
use App\Models\MarketingDailyDraftRun;
use App\Models\MarketingPost;
use App\Models\MarketingProduction;
use App\Models\MarketingProject;
use App\Models\MarketingStoryboardPresenter;
use App\Models\MarketingTemplateRun;
use App\Models\MarketingVideoClip;
use App\Models\MarketingVideoFilm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MarketingMediaSources
{
    public const MODELS = [MarketingAsset::class, MarketingVideoClip::class, MarketingVideoFilm::class, MarketingProduction::class, MarketingTemplateRun::class, MarketingDailyDraftRun::class, MarketingAvatar::class, MarketingAvatarVersion::class, MarketingStoryboardPresenter::class];

    public function safeCapture(Model $model): void
    {
        if (! Schema::hasTable('marketing_library_assets')) {
            return;
        }
        try {
            $this->capture($model);
        } catch (\Throwable $e) {
            report($e);
            logger()->warning('Marketing library capture needs reconciliation', ['source_type' => $model->getTable(), 'source_id' => $model->getKey()]);
        }
    }

    public function capture(Model $m): void
    {
        $project = $m->project_id ? MarketingProject::find($m->project_id) : null;
        $owner = $project?->owner_id ?? $m->owner_id ?? $m->user_id;
        $library = null;
        $avatar = null;
        if ($m instanceof MarketingAvatar || $m instanceof MarketingAvatarVersion) {
            $avatar = $m instanceof MarketingAvatar ? $m : MarketingAvatar::find($m->avatar_id);
            if (! $avatar || ! $avatar->library_id) {
                return;
            }$library = $avatar->library_id;
            $owner = DB::table('marketing_avatar_libraries')->where('id', $library)->value('owner_id');
        }
        if (! $owner) {
            return;
        }
        $context = ['owner_id' => $owner, 'project_id' => $project?->id, 'library_id' => $library, 'avatar_id' => $avatar?->id, 'source_type' => $m->getTable(), 'source_id' => $m->getKey(), 'title' => $m->name ?? ($project?->name ?? ($m->snapshot['title'] ?? 'Marketing asset')), 'project_name' => $project?->name, 'origin' => 'ai', 'stage' => 'Generated asset', 'slot' => 'file', 'occurred_at' => $m->created_at ?? now()];
        $register = app(MarketingMediaRegister::class);
        if ($m instanceof MarketingAsset) {
            $generated = preg_match('#/(?:storyboard-generated/|generated-|auto-|auto-review/|material-generated/)|^AI |^GPT Image#i', $m->path) || str_starts_with($m->name, 'AI ') || str_starts_with($m->name, 'GPT Image');
            $context['origin'] = $generated ? 'ai' : 'uploaded';
            $context['stage'] = str_contains($m->path, 'storyboard-generated') ? 'Storyboard image' : ($generated ? 'AI candidate' : 'Original upload');
            if (str_contains($m->path, '/avatar-references/')) {
                return;
            }
            $register->record($context, $m->path);

            return;
        }
        if ($m instanceof MarketingStoryboardPresenter) {
            $context['avatar_id'] = $m->avatar_binding['avatar_id'] ?? null;
            $context['origin'] = 'uploaded';
            $context['stage'] = 'Avatar reference';
            foreach ($m->avatar_binding['project_references'] ?? [] as $role => $ref) {
                $asset = MarketingAsset::find($ref['asset_id']);
                if ($asset) {
                    $register->record(array_replace($context, ['slot' => $role, 'title' => $m->name.' · '.$role]), $asset->path, $ref['sha256']);
                }
            }

return;
        }
        if ($avatar) {
            $context['origin'] = 'uploaded';
            $context['stage'] = 'Avatar reference';
            $context['title'] = $avatar->name;
            $refs = $m instanceof MarketingAvatar ? $m->draft['references'] ?? [] : $m->references;
            foreach ($refs as $role => $ref) {
                $register->record(array_replace($context, ['slot' => (string) $role, 'title' => $avatar->name.' · '.$role]), $ref['path'], $ref['sha256'] ?? null);
            }

return;
        }
        if ($m instanceof MarketingVideoClip) {
            $context['title'] = ($project?->name ?? 'Video').' · Scene '.$m->scene_number;
            $context['stage'] = 'Generated clip';
            foreach (['raw', 'output'] as $role) {
                $path = $m->{$role.'_path'};
                if ($path) {
                    $register->record(array_replace($context, ['slot' => $role, 'title' => $context['title'].' · '.$role]), $path, $m->{$role.'_sha256'}, ['seconds' => $m->input['seconds'] ?? null]);
                }
            }
        }
        if ($m instanceof MarketingVideoFilm || $m instanceof MarketingProduction) {
            $context['stage'] = 'Final video';
            if ($m->output_path) {
                $register->record($context, $m->output_path, $m->output_sha256);
            }
        }
        $payload = $m instanceof MarketingDailyDraftRun ? $m->snapshot : ($m->result ?? []);
        // Only output payloads are walked, never input snapshots containing catalog or arbitrary URLs.
        if ($m instanceof MarketingDailyDraftRun) {
            $payload = array_intersect_key($payload ?? [], array_flip(['images', 'background']));
        }
        if ($m instanceof MarketingDailyDraftRun && ! empty($m->snapshot['background_path'])) {
            $register->record(array_replace($context, ['slot' => 'background', 'title' => ($m->snapshot['title'] ?? 'Daily draft').' · AI background', 'stage' => 'AI background']), $m->snapshot['background_path'], $m->snapshot['background_sha256'] ?? null);
        }
        $this->walk($payload ?? [], $context, 'result');
        if ($m instanceof MarketingDailyDraftRun && $m->post_ids) {
            foreach (MarketingPost::whereIn('id', array_values($m->post_ids))->get() as $post) {
                $doc = $post->document;
                foreach ($doc['assets'] ?? [] as $key) {
                    if (str_starts_with($key, 'upload:')) {
                        $this->post(substr($key, 7), 'ai');
                    }
                }
            }
        }
    }

    private function walk(array $data, array $context, string $slot, int $depth = 0): void
    {
        if ($depth > 10) {
            return;
        }
        if (str_contains($slot, 'formats.')) {
            $context['title'] .= ' · '.ucfirst(substr($slot, strrpos($slot, '.') + 1));
        }
        if (is_string($data['path'] ?? null)) {
            app(MarketingMediaRegister::class)->record(array_replace($context, ['slot' => substr($slot, 0, 180)]), $data['path'], $data['sha256'] ?? null, $data);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->walk($value, $context, $slot.'.'.$key, $depth + 1);
            }
        }
    }

    public function safePost(string $id, string $origin = 'uploaded'): void
    {
        if (! Schema::hasTable('marketing_library_assets')) {
            return;
        }
        try {
            $this->post($id, $origin);
        } catch (\Throwable $e) {
            report($e);
            logger()->warning('Marketing upload library capture needs reconciliation', ['source_id' => $id]);
        }
    }

    public function post(string $id, string $origin = 'uploaded'): void
    {
        $row = DB::table('marketing_post_media')->find($id);
        if (! $row) {
            return;
        }
        $context = ['owner_id' => $row->user_id, 'source_type' => 'marketing_post_media', 'source_id' => $row->id, 'slot' => 'prepared', 'title' => $row->title, 'origin' => $origin, 'stage' => $origin === 'ai' ? 'AI image' : (str_ends_with($row->path, '.jpg') ? 'Prepared image' : 'Original upload'), 'occurred_at' => $row->created_at];
        app(MarketingMediaRegister::class)->record($context, $row->path, $row->sha256, ['seconds' => $row->seconds]);
        if (str_ends_with($row->path, '.jpg')) {
            app(MarketingMediaRegister::class)->record(array_replace($context, ['slot' => 'original', 'title' => $row->title.' · Original', 'stage' => $origin === 'ai' ? 'AI image original' : 'Original upload']), substr($row->path, 0, -4).'.original');
        }
    }

    public function email(int $owner, string $path, ?string $name = null): void
    {
        if (! preg_match('#^marketing/email/'.$owner.'/[^/]+$#D', $path)) {
            return;
        }
        $file = app(MarketingMediaRegister::class)->localPath($path, 'public');
        if (! $file) {
            return;
        }
        app(MarketingMediaRegister::class)->record(['owner_id' => $owner, 'disk' => 'public', 'source_type' => 'marketing_email_upload', 'source_id' => hash('sha256', $path), 'slot' => 'original', 'title' => $name ?? basename($path), 'origin' => 'uploaded', 'stage' => 'Email image', 'occurred_at' => Carbon::createFromTimestamp(filemtime($file))], $path);
    }

    public function safeEmail(int $owner, string $path, string $name): void
    {
        if (! Schema::hasTable('marketing_library_assets')) {
            return;
        }try {
            $this->email($owner, $path, $name);
        } catch (\Throwable $e) {
            report($e);
            logger()->warning('Marketing email image needs reconciliation', ['owner_id' => $owner]);
        }
    }

    public function backfill(?int $owner = null): array
    {
        $counts = [];
        foreach (self::MODELS as $class) {
            $q = $class::query();
            $table = $q->getModel()->getTable();
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($owner) {
                if (in_array($class, [MarketingAvatar::class, MarketingAvatarVersion::class], true)) {
                    $libs = DB::table('marketing_avatar_libraries')->where('owner_id', $owner)->pluck('id');
                    $q = $class === MarketingAvatar::class ? $q->whereIn('library_id', $libs) : $q->whereIn('avatar_id', MarketingAvatar::whereIn('library_id', $libs)->select('id'));
                } elseif ($class === MarketingDailyDraftRun::class) {
                    $q->where('user_id', $owner);
                } else {
                    $q->whereIn('project_id', MarketingProject::where('owner_id', $owner)->select('id'));
                }
            }
            $counts[$table] = 0;
            $q->chunkById(100, function ($rows) use (&$counts, $table) {
                foreach ($rows as $m) {
                    $this->capture($m);
                    $counts[$table]++;
                }
            });
        }
        $counts['marketing_post_media'] = 0;
        DB::table('marketing_post_media')->when($owner, fn ($q) => $q->where('user_id', $owner))->orderBy('id')->chunkById(100, function ($rows) use (&$counts) {
            foreach ($rows as $row) {
                $this->post($row->id);
                $counts['marketing_post_media']++;
            }
        });
        MarketingProject::when($owner, fn ($q) => $q->where('owner_id', $owner))->select('id')->chunkById(100, function ($rows) {
            foreach ($rows as $p) {
                app(MarketingMediaSelection::class)->project($p->id);
            }
        });
        $postOwners = MarketingPost::when($owner, fn ($q) => $q->where('user_id', $owner))->distinct()->pluck('user_id');
        foreach ($postOwners as $postOwner) {
            $sample = MarketingPost::where('user_id', $postOwner)->first();
            if ($sample) {
                app(MarketingMediaSelection::class)->post($sample);
            }
        }
        // Legacy Email uploads have no database row. Inspect only this known, flat upload sink.
        $counts['marketing_email_upload'] = 0;
        User::when($owner, fn ($q) => $q->whereKey($owner))->select('id')->chunkById(100, function ($users) use (&$counts) {
            foreach ($users as $user) {
                $dir = Storage::disk('public')->path('marketing/email/'.$user->id);
                if (! is_dir($dir)) {
                    continue;
                }foreach (new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }$this->email($user->id,'marketing/email/'.$user->id.'/'.$file->getFilename());
                    $counts['marketing_email_upload']++;
                }
            }
        });

        return $counts;
    }
}
