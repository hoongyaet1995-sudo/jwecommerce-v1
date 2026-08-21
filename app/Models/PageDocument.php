<?php

namespace App\Models;

use App\Domain\Cms\PageDocumentJsonCodec;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class PageDocument extends Model
{
    public const STAGE_DRAFT = 'draft';

    public const STAGE_PUBLISHED = 'published';

    protected $fillable = [
        'page_id',
        'stage',
        'slug',
        'schema_version',
        'lock_version',
        'content_hash',
        'document',
    ];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    /**
     * All canonical document reads and model writes cross the same token-aware
     * boundary. The database value remains JSON; callers receive the contract's
     * canonical PHP representation, including stdClass for empty objects.
     */
    protected function document(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): array {
                if (! is_string($value)) {
                    throw new InvalidArgumentException('Stored page document JSON must be a string.');
                }

                return app(PageDocumentJsonCodec::class)->decodeDocument($value);
            },
            set: static function (mixed $value): string {
                if (! is_array($value)) {
                    throw new InvalidArgumentException('Page document must use the canonical array API.');
                }

                return app(PageDocumentJsonCodec::class)->encodeDocument($value);
            },
        );
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
