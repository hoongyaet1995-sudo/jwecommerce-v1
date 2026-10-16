<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\CatalogFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SharedCatalogTagsFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_quote_prices_and_stock_never_become_public_filter_bounds(): void
    {
        config(['product_enquiries.enabled'=>true]);
        $quote=Product::create(['handle'=>'quote-only','title'=>'Quote Container','status'=>'active','base_price'=>987654,'purchase_mode'=>'quote','price_display'=>'hide','tags'=>['20ft']]);
        $quote->variants()->create(['sku'=>'QUOTE-ONLY','title'=>'20ft','price'=>987654,'stock'=>15,'enabled'=>true]);
        $request=Request::create('/shop','GET',['in_stock'=>'1','price_filter'=>'1','min_price'=>1]);
        $request->attributes->set('websiteDesignPreview','logistics');
        $result=app(CatalogFilterService::class)->for($request,Product::where('status','active'));
        $this->assertFalse($result->facets['hasPricedProducts']);
        $this->assertSame(['min'=>0,'max'=>0],$result->facets['priceBounds']);
        $this->assertFalse($result->filters['inStock']);
        $this->assertFalse($result->filters['priceRequested']);
        $this->assertSame(1,$result->products->total());
        $buy=Product::create(['handle'=>'buy-item','title'=>'Buy accessory','status'=>'active','base_price'=>12000,'purchase_mode'=>'buy','tags'=>['Accessory']]);
        $buy->variants()->create(['sku'=>'BUY-ITEM','title'=>'Standard','price'=>12000,'stock'=>10,'enabled'=>true]);
        $request=Request::create('/shop','GET',['tag'=>['20ft']]);
        $request->attributes->set('websiteDesignPreview','logistics');
        $result=app(CatalogFilterService::class)->for($request,Product::where('status','active'));
        $this->assertSame(['min'=>12000,'max'=>12000],$result->facets['priceBounds']);
        $this->assertSame(['20ft'],$result->filters['tags']);
        $this->assertSame([$quote->id],$result->products->pluck('id')->all());
        $this->assertContains('Accessory',array_column($result->facets['tags'],'value'));
    }

    public function test_tags_are_or_within_group_and_scoped_to_eligible_catalog_and_search(): void
    {
        foreach ([['a','Container A',['20ft','General']], ['b','Container B',['40ft']], ['c','Other',['20ft']], ['secret','Hidden',['Secret']]] as [$handle,$title,$tags]) {
            Product::create(['handle'=>$handle,'title'=>$title,'tags'=>$tags,'status'=>$handle==='secret'?'draft':'active','base_price'=>100]);
        }
        $request = Request::create('/shop', 'GET', ['tag'=>['20ft','40ft','Secret',['bad']], 'search'=>'Container','sort'=>'newest']);
        $request->attributes->set('websiteDesignPreview', 'default');
        $result = app(CatalogFilterService::class)->for($request, Product::where('status','active'));
        $this->assertSame(['20ft','40ft'], $result->filters['tags']);
        $this->assertEqualsCanonicalizing(['a','b'], $result->products->pluck('handle')->all());
        $this->assertSame(['20ft','40ft','General'], array_column($result->facets['tags'], 'value'));
        $this->assertSame([1,1,1], array_column($result->facets['tags'], 'count'));
        $this->assertSame($result->filters['tags'], $result->toViewData()['tags']);
        $request = Request::create('/shop','GET',['tag'=>['40ft'],'search'=>'Container A']);
        $request->attributes->set('websiteDesignPreview','default');
        $empty = app(CatalogFilterService::class)->for($request,Product::where('status','active'));
        $this->assertSame(['40ft'],$empty->filters['tags']);
        $this->assertSame(0,$empty->products->total());
    }

    public function test_faqs_save_order_delete_normalize_and_preserve_custom_accordions(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $payload = ['title'=>'FAQ Container','status'=>'active','hasVariants'=>false,'basePrice'=>0,'featured'=>false,'options'=>[],'tags'=>[],'images'=>[],'collectionIds'=>[],
            'variants'=>[['sku'=>'FAQ-1','title'=>'Default','options'=>[],'price'=>0,'inventoryQuantity'=>0,'enabled'=>true]],
            'pdpContent'=>['faqs'=>[['id'=>'second','question'=>' Delivery? ','answer'=>' Confirm location. '],['id'=>'first','question'=>'Size?','answer'=>'20ft']],
                'customAccordions'=>[['id'=>'custom','title'=>'Care','content'=>'Dry storage']]]];
        $response = $this->postJson('/admin/api/catalog/products',$payload)->assertCreated();
        $id = $response->json('data.id');
        $response->assertJsonPath('data.pdpContent.faqs.0.question','Delivery?');
        $product = Product::findOrFail($id);
        $payload['variants'][0]['id'] = (string) $product->variants()->firstOrFail()->id;
        $this->assertSame(['second','first'],array_column($product->pdpContent->faqs,'id'));
        $savedFaqs = $response->json('data.pdpContent.faqs');
        unset($payload['pdpContent']['faqs']);
        $this->patchJson('/admin/api/catalog/products/'.$id,$payload)->assertOk()->assertJsonPath('data.pdpContent.faqs',$savedFaqs);
        $payload['pdpContent']['faqs'] = [$savedFaqs[1]];
        $this->patchJson('/admin/api/catalog/products/'.$id,$payload)->assertOk()->assertJsonCount(1,'data.pdpContent.faqs');
        $payload['pdpContent']['faqs'] = [];
        $this->patchJson('/admin/api/catalog/products/'.$id,$payload)->assertOk()->assertJsonPath('data.pdpContent.faqs',[])->assertJsonPath('data.pdpContent.customAccordions.0.title','Care');
        $payload['pdpContent']['faqs'] = [['id'=>'invalid','question'=>'','answer'=>'Answer']];
        $this->patchJson('/admin/api/catalog/products/'.$id,$payload)->assertUnprocessable()->assertJsonValidationErrors('pdpContent.faqs.0.question');
    }

    public function test_tags_retain_collection_scope_category_and_pagination_query(): void
    {
        $category = \App\Models\Category::create(['name'=>'Containers','slug'=>'containers','is_active'=>true]);
        $collection = \App\Models\Collection::create(['title'=>'Selected','slug'=>'selected','type'=>'manual','is_active'=>true]);
        for ($i = 0; $i < 14; $i++) {
            $product = Product::create(['category_id'=>$category->id,'handle'=>'box-'.$i,'title'=>'Box '.$i,'status'=>'active','base_price'=>100,'tags'=>['20ft','Group'.$i]]);
            $collection->products()->attach($product);
        }
        Product::create(['handle'=>'outside','title'=>'Outside','status'=>'active','base_price'=>100,'tags'=>['PrivateScope']]);
        $request = Request::create('/collections/selected', 'GET', ['tag'=>['20ft','PrivateScope'], 'category'=>'containers','sort'=>'price_asc']);
        $request->attributes->set('websiteDesignPreview','default');
        $result = app(CatalogFilterService::class)->for($request,$collection->products()->where('status','active'));
        $this->assertSame(['20ft'],$result->filters['tags']);
        $this->assertSame(14,$result->products->total());
        $this->assertNotContains('PrivateScope',array_column($result->facets['tags'],'value'));
        // Laravel pagination copies the active HTTP query via withQueryString.
        $this->assertSame('containers',$result->filters['category']);
    }
}
