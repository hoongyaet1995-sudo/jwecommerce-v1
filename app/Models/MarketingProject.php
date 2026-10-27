<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketingProject extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'preferences'=>'array', 'planning_state'=>'array', 'brief' => 'array', 'concepts' => 'array', 'version' => 'integer', 'selected_concept' => 'integer', 'template_snapshot'=>'array'];
    }

    public function assets()
    {
        return $this->hasMany(MarketingAsset::class, 'project_id');
    }

    public function currentResearch() { return $this->belongsTo(MarketingResearchRun::class, 'current_research_run_id'); }
    public function activeResearch() { return $this->belongsTo(MarketingResearchRun::class, 'active_research_run_id'); }

    public function revisions()
    {
        return $this->hasMany(MarketingRevision::class, 'project_id')->orderByDesc('number');
    }

    public function currentRevision()
    {
        return $this->belongsTo(MarketingRevision::class, 'current_revision_id');
    }
}
