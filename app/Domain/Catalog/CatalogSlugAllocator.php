<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CatalogSlugAllocator
{
    public function category(string $name): string
    {
        return $this->allocate(Category::class, $name, 'category');
    }

    public function collection(string $title): string
    {
        return $this->allocate(Collection::class, $title, 'collection');
    }

    /** @param class-string<Model> $model */
    private function allocate(string $model, string $source, string $fallback): string
    {
        $base = Str::of($source)
            ->slug()
            ->substr(0, 240)
            ->trim('-')
            ->value() ?: $fallback;

        $used = array_fill_keys(
            $model::query()
                ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
                ->lockForUpdate()
                ->pluck('slug')
                ->all(),
            true,
        );

        if (! isset($used[$base])) {
            return $base;
        }

        for ($suffix = 2; ; $suffix++) {
            $candidate = Str::of($base)
                ->substr(0, 255 - strlen((string) $suffix) - 1)
                ->rtrim('-')
                ->append('-'.$suffix)
                ->value();
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }
    }
}
