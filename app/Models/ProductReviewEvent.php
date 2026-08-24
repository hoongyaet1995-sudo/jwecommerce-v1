<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewEvent extends Model
{
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = ['product_review_id', 'actor_user_id', 'event_type', 'from_status', 'to_status', 'batch_id', 'metadata', 'occurred_at'];

    protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
    public function review(): BelongsTo { return $this->belongsTo(ProductReview::class, 'product_review_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
