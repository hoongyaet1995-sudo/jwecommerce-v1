<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewMedia extends Model
{
    protected $fillable = ['product_review_id', 'original_path', 'public_path', 'original_filename', 'mime_type', 'byte_size', 'width', 'height', 'checksum_sha256', 'position', 'published_at'];

    protected function casts(): array { return ['published_at' => 'datetime']; }
    public function review(): BelongsTo { return $this->belongsTo(ProductReview::class, 'product_review_id'); }
}
