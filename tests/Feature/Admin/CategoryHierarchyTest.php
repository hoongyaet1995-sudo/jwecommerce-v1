<?php
namespace Tests\Feature\Admin;
use App\Models\{Category,Product,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CategoryHierarchyTest extends TestCase {
 use RefreshDatabase;
 private function category(?Category $parent=null): Category {return Category::create(['name'=>uniqid('Category'),'slug'=>uniqid('cat-'),'parent_id'=>$parent?->id]);}
 private function payload(?Category $parent): array {return ['name'=>'New','status'=>'active','sortOrder'=>0,'parentId'=>$parent?->id];}
 public function test_depth_cycles_and_subtree_moves_are_rejected(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $root=$this->category();$child=$this->category($root);$leaf=$this->category($child);
  $this->postJson('/admin/api/catalog/categories',$this->payload($leaf))->assertUnprocessable();
  $this->patchJson('/admin/api/catalog/categories/'.$root->id,$this->payload($leaf))->assertUnprocessable();
  $other=$this->category();$otherChild=$this->category($other);
  $this->patchJson('/admin/api/catalog/categories/'.$child->id,$this->payload($otherChild))->assertUnprocessable();
  $this->postJson('/admin/api/catalog/categories',$this->payload($child))->assertCreated();
  $this->deleteJson('/admin/api/catalog/categories/'.$root->id)->assertUnprocessable();
 }
 public function test_product_paging_linking_and_unlinking_preserve_product_fields(): void {
  $this->actingAs(User::factory()->create(['is_admin'=>true]));$category=$this->category();$other=$this->category();
  for($i=0;$i<23;$i++) Product::create(['title'=>'Product '.$i,'handle'=>'product-'.$i,'category_id'=>$category->id,'base_price'=>12345,'status'=>'active']);
  $this->getJson('/admin/api/catalog/categories/'.$category->id.'/products')->assertOk()->assertJsonCount(20,'data')->assertJsonPath('total',23);
  $this->getJson('/admin/api/catalog/categories/'.$category->id.'/products?page=2')->assertOk()->assertJsonCount(3,'data');
  $product=Product::create(['title'=>'Move me','handle'=>'move-me','category_id'=>$other->id,'base_price'=>45678,'status'=>'active']);
  $this->postJson('/admin/api/catalog/categories/'.$category->id.'/products',['productIds'=>[$product->id]])->assertOk();
  $this->assertSame($category->id,$product->fresh()->category_id);$this->assertSame(45678,$product->fresh()->base_price);
  $this->deleteJson('/admin/api/catalog/categories/'.$other->id.'/products/'.$product->id)->assertNotFound();
  $this->deleteJson('/admin/api/catalog/categories/'.$category->id.'/products/'.$product->id)->assertNoContent();
  $this->assertNull($product->fresh()->category_id);
  $this->postJson('/admin/api/catalog/categories/'.$category->id.'/products',['productIds'=>[999999]])->assertUnprocessable();
  $this->deleteJson('/admin/api/catalog/categories/'.$category->id)->assertUnprocessable();
 }
 public function test_picker_search_and_non_admin_access(): void {
  $category=$this->category();$other=$this->category();
  Product::create(['title'=>'Searchable RAM','handle'=>'searchable','category_id'=>$other->id,'base_price'=>100,'status'=>'active']);
  Product::create(['title'=>'Excluded RAM','handle'=>'excluded','category_id'=>$category->id,'base_price'=>100,'status'=>'active']);
  $this->actingAs(User::factory()->create(['is_admin'=>true]));
  $this->getJson('/admin/api/catalog/categories/'.$category->id.'/products?picker=1&search=RAM')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.title','Searchable RAM');
  $this->actingAs(User::factory()->create(['is_admin'=>false]));
  $this->postJson('/admin/api/catalog/categories/'.$category->id.'/products',['productIds'=>[1]])->assertUnauthorized();
 }
 public function test_guests_cannot_link_products(): void {$this->postJson('/admin/api/catalog/categories/1/products',['productIds'=>[1]])->assertUnauthorized();}
}
