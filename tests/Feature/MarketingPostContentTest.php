<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Storage,DB};
use App\Models\{User,Product,ProductImage};
use App\Services\Marketing\MarketingPostContent;
class MarketingPostContentTest extends TestCase {
 use RefreshDatabase;
 public function test_order_overrides_and_all_source_hashes_are_bound(){Http::preventStrayRequests();Storage::fake('public');$u=User::factory()->create();$p=Product::create(['title'=>'Generic item','handle'=>'generic','status'=>'active','base_price'=>1]);$keys=[];foreach(['a','b','c'] as $n){Storage::disk('public')->put($n.'.jpg','image-'.$n);$i=ProductImage::create(['product_id'=>$p->id,'path'=>$n.'.jpg','position'=>count($keys)]);$keys[]='image:'.$i->id;}$s=app(MarketingPostContent::class);$d=['asset'=>$keys[0],'assets'=>$keys,'media_copies'=>['fb'=>array_reverse($keys)],'type'=>'Post','copies'=>[],'copy'=>'Confirmed facts','targets'=>['fb']];$this->assertSame(array_reverse($keys),$s->keys($d,'fb'));$before=$s->snapshot($u->id,$d);Storage::disk('public')->put('b.jpg','changed');$this->assertNotSame($before['sha256'],$s->snapshot($u->id,$d)['sha256']);Http::assertNothingSent(); }
 public function test_uploaded_image_is_private_and_original_is_retained(){Http::preventStrayRequests();Storage::fake('local');$u=User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]);$this->actingAs($u);$file=\Illuminate\Http\UploadedFile::fake()->image('original.png',640,640);$r=$this->post('/admin/marketing/posts/upload',['file'=>$file],['Accept'=>'application/json'])->assertOk()->assertJsonPath('asset.kind','image');$id=substr($r->json('asset.id'),7);$row=DB::table('marketing_post_media')->where('id',$id)->first();Storage::disk('local')->assertExists(str_replace('.jpg','.original',$row->path));$this->get('/admin/marketing/posts/media/upload:'.$id)->assertOk()->assertHeader('Content-Type','image/jpeg');$this->actingAs(User::factory()->create(['is_admin'=>true,'is_super_admin'=>true]));$this->get('/admin/marketing/posts/media/upload:'.$id)->assertNotFound();Http::assertNothingSent(); }
 public function test_legacy_single_asset_still_resolves(){ $s=app(MarketingPostContent::class);$this->assertSame(['image:1'],$s->keys(['asset'=>'image:1']));$this->assertSame([], $s->keys(['asset'=>null])); }
}
