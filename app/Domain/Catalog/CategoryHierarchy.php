<?php
namespace App\Domain\Catalog;
use App\Models\Category;
use Illuminate\Validation\ValidationException;
class CategoryHierarchy {
 public function validateParent(?int $parentId, ?Category $category): void {
  $nodes=Category::query()->lockForUpdate()->get(['id','parent_id'])->keyBy('id');
  $seen=[];$depth=0;$cursor=$parentId;
  while($cursor!==null){
   if(isset($seen[$cursor])||($category && $cursor===$category->id)) $this->fail();
   $seen[$cursor]=true;$depth++;$cursor=$nodes->get($cursor)?->parent_id;
  }
  $height=function(int $id,array $visited=[]) use (&$height,$nodes): int {
   if(isset($visited[$id])) $this->fail();$visited[$id]=true;$max=1;
   foreach($nodes as $node) if($node->parent_id===$id) $max=max($max,1+$height($node->id,$visited));
   return $max;
  };
  if($depth+($category?$height($category->id):1)>3) $this->fail();
 }
 private function fail(): never {throw ValidationException::withMessages(['parentId'=>'Choose a parent that keeps the category within three levels, without circular links.']);}
 public function validateDelete(Category $category): void {
  if(Category::where('parent_id',$category->id)->exists()||$category->products()->exists()) throw ValidationException::withMessages(['category'=>'Move the subcategories and linked products before deleting this category.']);
 }
}
