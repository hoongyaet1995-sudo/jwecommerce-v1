<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\Marketing\MarketingOverlays;
class MarketingOverlaysTest extends TestCase {
 public function test_more_than_three_layers_are_rejected_before_reading_files():void{$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingOverlays::class)->normalize(array_fill(0,4,[]),[],new \App\Models\MarketingProject);}
 public function test_external_urls_are_not_image_identifiers():void{$this->expectException(\Illuminate\Validation\ValidationException::class);app(MarketingOverlays::class)->normalize([['url'=>'https://example.com/a.png']],[],new \App\Models\MarketingProject);}
 public function test_empty_array_explicitly_clears_layers():void{$this->assertSame([],app(MarketingOverlays::class)->normalize([],[],new \App\Models\MarketingProject));}
}
