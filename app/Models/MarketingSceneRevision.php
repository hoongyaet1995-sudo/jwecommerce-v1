<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingSceneRevision extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Scene revisions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Scene history must be preserved.'));
    }

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
