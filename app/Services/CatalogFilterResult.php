<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class CatalogFilterResult
{
    /**
     * @param array{search:string,category:?string,sort:string,layout:string,minPrice:int,maxPrice:int,boundsMinPrice:int,boundsMaxPrice:int,inStock:bool,colors:list<string>,materials:list<string>,minRating:float,variantFiltered:bool} $filters
     * @param array<string, mixed> $facets
     */
    public function __construct(
        public array $filters,
        public LengthAwarePaginator $products,
        public array $facets = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toViewData(): array
    {
        return [
            'products' => $this->products,
            'filters' => $this->filters,
            'search' => $this->filters['search'],
            'category' => $this->filters['category'],
            'sort' => $this->filters['sort'],
            'layout' => $this->filters['layout'],
            'minPrice' => $this->filters['minPrice'],
            'maxPrice' => $this->filters['maxPrice'],
            'inStock' => $this->filters['inStock'],
            // Kept for the current component during its source-faithful replacement.
            'colors' => collect($this->facets['colors'] ?? [])->pluck('value'),
            'materials' => $this->filters['materials'],
            'minRating' => $this->filters['minRating'],
            'facets' => $this->facets,
            // Compatibility values remain until the source-faithful Filter Matrix replacement.
            'color' => $this->filters['colors'][0] ?? null,
        ];
    }
}
