<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LogisticsFilterPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_result_drawer_preserves_selected_filters_absent_from_facets(): void
    {
        $data=[
            'categories'=>collect(),'search'=>'','category'=>null,'sort'=>'','layout'=>'grid',
            'filters'=>['minPrice'=>0,'maxPrice'=>0,'priceRequested'=>false,'colors'=>['Blue'],'materials'=>['Steel'],'tags'=>['40ft'],'minRating'=>4,'inStock'=>false],
            'facets'=>['hasPricedProducts'=>false,'colors'=>[],'materials'=>[],'tags'=>[],'hasRatings'=>false,'categoryCounts'=>[],'priceBounds'=>['min'=>0,'max'=>0]],
        ];
        $html=Blade::render('<x-storefront.logistics-filters :categories="$categories" :search="$search" :category="$category" :sort="$sort" :layout="$layout" :filters="$filters" :facets="$facets"/>',$data);
        foreach(['name="color[]" value="Blue"','name="material[]" value="Steel"','name="tag[]" value="40ft"','name="min_rating" value="4"'] as $selection)$this->assertStringContainsString($selection,$html);
        $this->assertStringNotContainsString('Minimum price amount',$html);
    }
}
