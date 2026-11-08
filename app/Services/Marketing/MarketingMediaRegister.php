<?php

namespace App\Services\Marketing;

use App\Models\MarketingLibraryAsset;
use App\Models\MarketingProduction;
use App\Models\MarketingProject;
use App\Models\MarketingTemplateRun;
use App\Models\MarketingVideoClip;
use App\Models\MarketingVideoFilm;
use Illuminate\Support\Facades\Storage;

/** A private index; never moves, adopts, approves or publishes its source. */
class MarketingMediaRegister
{
    public function localPath(string $path, string $disk = 'local'): ?string
    {
        if (! in_array($disk, ['local', 'public'], true) || ($disk === 'public' && ! preg_match('#^marketing/email/[0-9]+/[^/]+$#D', $path))) {
            return null;
        }
        if (! preg_match('#^(marketing/|marketing-[a-z-]+/)#D', $path) || str_contains($path, '..') || str_contains($path, ':') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return null;
        }
        $root = realpath(Storage::disk($disk)->path(''));
        $file = realpath(Storage::disk($disk)->path($path));

        return $root && $file && is_file($file) && str_starts_with(strtolower($file), strtolower($root.DIRECTORY_SEPARATOR)) ? $file : null;
    }

    public function ownsPath(array $context, string $path, string $disk = 'local'): bool
    {
        $owner = $context['owner_id'];
        $project = $context['project_id'] ?? null;
        $source = $context['source_type'];
        $id = $context['source_id'];
        if ($disk === 'public') {
            return $source === 'marketing_email_upload' && str_starts_with($path, 'marketing/email/'.$owner.'/');
        }
        if ($source === 'marketing_post_media') {
            return str_starts_with($path, 'marketing/post-media/'.$owner.'/'.$id.'.');
        }
        if ($source === 'marketing_daily_draft_runs') {
            return str_starts_with($path, 'marketing/daily-drafts/'.$id.'/') || str_starts_with($path, 'marketing/free-posts/'.$id.'/');
        }
        if (! empty($context['library_id'])) {
            return str_starts_with($path, 'marketing-avatar/'.$context['library_id'].'/');
        }
        if (! $project) {
            return false;
        }
        if (str_starts_with($path, 'marketing/'.$project.'/')) {
            return MarketingProject::whereKey($project)->where('owner_id', $owner)->exists();
        }
        if (! preg_match('#^(marketing-clips|marketing-films|marketing-productions|marketing-template-runs)/([^/]+)/#D', $path, $m)) {
            return false;
        }
        $class = match ($m[1]) {
            'marketing-clips' => MarketingVideoClip::class,'marketing-films' => MarketingVideoFilm::class,'marketing-productions' => MarketingProduction::class,'marketing-template-runs' => MarketingTemplateRun::class
        };

        return $class::whereKey($m[2])->where('project_id', $project)->exists() && MarketingProject::whereKey($project)->where('owner_id', $owner)->exists();
    }

    public function record(array $context, string $path, ?string $expected = null, array $meta = []): ?MarketingLibraryAsset
    {
        $disk = $context['disk'] ?? 'local';
        if (! $this->ownsPath($context, $path, $disk)) {
            return null;
        }$file = $this->localPath($path, $disk);
        if (! $file) {
            return null;
        }
        $hash = hash_file('sha256', $file);
        if ($expected && (! preg_match('/^[a-f0-9]{64}$/D', $expected) || ! hash_equals($expected, $hash))) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $kind = match ($mime) {
            'image/png','image/jpeg','image/webp','image/gif' => 'image','video/mp4','video/webm','video/quicktime' => 'video',default => null
        };
        if (! $kind) {
            return null;
        }
        $dimensions = $kind === 'image' ? @getimagesize($file) : null;
        $identity = hash('sha256', json_encode([$context['owner_id'], $context['project_id'] ?? null, $context['library_id'] ?? null, $context['source_type'], $context['source_id'], $disk, $path, $hash], JSON_THROW_ON_ERROR));
        $legacy = MarketingLibraryAsset::where('owner_id', $context['owner_id'])->where('source_type', $context['source_type'])->where('source_id', $context['source_id'])->where('path', $path)->where('sha256', $hash)->first();
        if ($legacy && $legacy->identity !== $identity) {
            $legacy->update(['identity' => $identity]);
        }
        $row = MarketingLibraryAsset::firstOrCreate(['identity' => $identity], $context + ['disk' => $disk, 'path' => $path, 'sha256' => $hash, 'mime' => $mime, 'kind' => $kind, 'bytes' => filesize($file), 'width' => $dimensions[0] ?? ($meta['width'] ?? null), 'height' => $dimensions[1] ?? ($meta['height'] ?? null), 'seconds' => $meta['seconds'] ?? ($meta['duration'] ?? null)]);
        $details = array_filter(['width' => $dimensions[0] ?? ($meta['width'] ?? null), 'height' => $dimensions[1] ?? ($meta['height'] ?? null), 'seconds' => $meta['seconds'] ?? ($meta['duration'] ?? null)], fn ($v) => is_numeric($v) && $v > 0);
        $missing = array_filter($details, fn ($value, $key) => ! $row->$key, ARRAY_FILTER_USE_BOTH);
        if ($missing) {
            $row->update($missing);
        }
        // Generated post derivatives can first be captured by the shared upload sink.
        if (($context['origin'] ?? '') === 'ai' && $row->origin !== 'ai') {
            $row->update(['origin' => 'ai', 'stage' => $context['stage']]);
        }

        return $row;
    }
}
