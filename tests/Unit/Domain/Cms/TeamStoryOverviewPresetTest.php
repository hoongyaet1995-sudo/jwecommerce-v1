<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Tests\TestCase;
class TeamStoryOverviewPresetTest extends TestCase
{
 public function test_three_image_preset_preserves_independent_assets_alt_and_hidden_layout_data():void
 {
  $s=['id'=>'flow','type'=>'media_text_split','layoutPreset'=>'team-story-overview','title'=>'Editable title','content'=>'Editable introduction','subtitle'=>'Editable eyebrow','isVisible'=>true,'containerMode'=>'page','items'=>[],'imageSlots'=>[]];
  $s['mediaUrl']='https://www.jwaistudio.online/composite.png';$s['imageSlots']=[['target'=>'mediaUrl','mediaAlt'=>'Independent composite']];
  foreach(range(1,3) as $i){$s['items'][]=['id'=>'image-'.$i,'icon'=>'Layers','title'=>'Editable card '.$i,'description'=>'Editable copy '.$i,'imageUrl'=>'https://www.jwaistudio.online/card-'.$i.'.png'];$s['imageSlots'][]=['target'=>'item:image-'.$i.':imageUrl','mediaAlt'=>'Editable image '.$i];}
  $d=['title'=>'Review','slug'=>'flow-review','template'=>'standard','containerWidth'=>'site','sections'=>[$s]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['layoutPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='team-story-overview';$this->assertSame($d,$c->validate($d));
 }
}
