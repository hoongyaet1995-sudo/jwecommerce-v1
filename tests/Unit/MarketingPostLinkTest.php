<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\Marketing\MarketingPostLink;
class MarketingPostLinkTest extends TestCase {
 public function test_links_are_destination_specific_and_success_only():void {
  $s=new MarketingPostLink;
  $this->assertSame('https://www.youtube.com/watch?v=7ybeOgut2ag',$s->url('youtube',['status'=>'Published','ref'=>'7ybeOgut2ag']));
  $this->assertSame('https://www.facebook.com/123/posts/456',$s->url('facebook',['status'=>'Published','ref'=>'123_456']));
  $this->assertSame('https://www.instagram.com/p/ABC123/',$s->url('instagram',['status'=>'Published','url'=>'https://www.instagram.com/p/ABC123/']));
  $this->assertSame('https://www.tiktok.com/@_/video/1234567890123456789',$s->url('tiktok',['status'=>'Published','ref'=>'1234567890123456789']));
  $this->assertNull($s->url('tiktok',['status'=>'Published','ref'=>'v_pub_task']));
  $this->assertNull($s->url('instagram',['status'=>'Published','ref'=>'123456']));
  foreach(['javascript:alert(1)','https://www.instagram.com.evil.test/p/X/','https://www.instagram.com@evil.test/p/X/','https://www.instagram.com/p/X/?access_token=secret'] as $url)$this->assertNull($s->url('instagram',['status'=>'Published','url'=>$url]));
  $this->assertNull($s->url('youtube',['status'=>'Publishing','ref'=>'7ybeOgut2ag']));
 }
}
