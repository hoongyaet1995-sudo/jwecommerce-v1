<?php
namespace Tests\Unit\Domain\Cms;
use App\Domain\Cms\PageDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
final class CorporatePresetsTest extends TestCase {
 public function test_gradient_cta_is_generic_and_preserves_text_and_action_across_layouts():void {
  $d=['title'=>'Services','slug'=>'services','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'closing','type'=>'cta_strip','ctaPreset'=>'gradient-panel','title'=>'Book your visit','content'=>'Tailored for you.','buttonText'=>'Contact us','buttonLink'=>'/pages/contact','isVisible'=>true]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));$d['sections'][0]['ctaPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['ctaPreset']='gradient-panel';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['type']='faq_accordion';$this->expectException(ValidationException::class);$c->validate($d);
 }
 public function test_connected_features_preserve_owned_illustrations_copy_and_cta_destinations():void {
  $d=['title'=>'Connections','slug'=>'connections','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'f01-connections','type'=>'media_gallery','galleryPreset'=>'corporate-connections','title'=>'What comes next','isVisible'=>true,'items'=>[]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'shop','title'=>'Ecommerce','description'=>'Editable description','imageUrl'=>'','buttonText'=>'Explore Ecommerce','linkUrl'=>'/pages/ecommerce']];$d['sections'][0]['imageSlots']=[['target'=>'item:shop:imageUrl','mediaAlt'=>'Ecommerce illustration']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['galleryPreset']='portfolio-cards';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['galleryPreset']='corporate-connections';$this->assertSame($d,$c->validate($d));
 }
 public function test_illustrated_steps_preserve_independent_images_titles_and_descriptions():void {
  $d=['title'=>'Workflow','slug'=>'workflow','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'f01-workflow','type'=>'media_gallery','galleryPreset'=>'corporate-illustrated-steps','title'=>'From first draft','isVisible'=>true,'items'=>[]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'shape','title'=>'Shape your page','description'=>'Editable explanation','imageUrl'=>'']];$d['sections'][0]['imageSlots']=[['target'=>'item:shape:imageUrl','mediaAlt'=>'Shape illustration']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['galleryPreset']='routine-steps';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['galleryPreset']='corporate-illustrated-steps';$this->assertSame($d,$c->validate($d));
 }
 public function test_scenarios_keeps_owned_independent_images_icons_and_names():void {
  $d=['title'=>'Scenarios','slug'=>'scenarios','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'f01-scenarios','type'=>'media_gallery','galleryPreset'=>'corporate-scenarios','title'=>'Made for your business','isVisible'=>true,'items'=>[]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'business','title'=>'Business websites','icon'=>'Monitor','imageUrl'=>''],['id'=>'services','title'=>'Service showcases','icon'=>'Layers','imageUrl'=>'']];
  $d['sections'][0]['imageSlots']=[['target'=>'item:business:imageUrl','mediaAlt'=>'Business preview'],['target'=>'item:services:imageUrl','mediaAlt'=>'Service preview']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['galleryPreset']='editorial-cards';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['galleryPreset']='corporate-scenarios';$this->assertSame($d,$c->validate($d));
 }
 public function test_section_story_accepts_sparse_data_and_preserves_labels_and_image_slots():void {
  $d=['title'=>'Section story','slug'=>'section-story','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'f01-story','type'=>'media_text_split','layoutPreset'=>'corporate-section-story','title'=>"Build a page that\ntells your story.",'isVisible'=>true,'mediaUrl'=>'','items'=>[]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'label','title'=>'Services & features','icon'=>'LayoutGrid']];$d['sections'][0]['imageSlots']=[['target'=>'mediaUrl','mediaAlt'=>'Layered sections']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='corporate-image-story';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['layoutPreset']='corporate-section-story';$this->assertSame($d,$c->validate($d));
 }
 public function test_builder_overview_keeps_independent_icon_labels_and_image_across_switches():void {
  $d=['title'=>'Builder overview','slug'=>'builder-overview','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'f01-builder','type'=>'media_text_split','layoutPreset'=>'corporate-builder-overview','title'=>'Make changes.','isVisible'=>true,'mediaUrl'=>'','items'=>[]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'first','title'=>'Arrange sections','icon'=>'Layers'],['id'=>'second','title'=>'Edit content','icon'=>'Pencil']];
  $d['sections'][0]['imageSlots']=[['target'=>'mediaUrl','mediaAlt'=>'Page builder overview']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='default';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['layoutPreset']='corporate-builder-overview';$this->assertSame($d,$c->validate($d));
 }
 public function test_split_composite_hero_preserves_copy_actions_and_owned_image_slot():void {
  $d=['title'=>'Website Hero','slug'=>'website-hero','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'hero','type'=>'hero_banner','heroBannerPreset'=>'corporate-split-composite','title'=>'Editable','isVisible'=>true,'mediaUrl'=>'','buttonText'=>'Demo','buttonLink'=>'https://www.jwaistudio.online/pages/contact-jw-studio','secondaryButtonText'=>'Builder','secondaryButtonLink'=>'#section-f01-builder','imageSlots'=>[['target'=>'mediaUrl','mediaAlt'=>'Editable artwork']]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['heroBannerPreset']='corporate-centered';$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['heroBannerPreset']='corporate-split-composite';$this->assertSame($d,$c->validate($d));
 }
 public function test_marketplace_sparse_and_benefit_content_survives_layout_switches():void {
  $d=['title'=>'Marketplace','slug'=>'marketplace','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'market','type'=>'media_text_split','layoutPreset'=>'corporate-marketplace','title'=>'Marketplace','isVisible'=>true]]];$c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'benefit','title'=>'SKU mapping','description'=>'Editable','icon'=>'Tag']];$d['sections'][0]['imageSlots']=[['target'=>'mediaUrl','mediaAlt'=>'Connected platforms']];$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='corporate-marketplace';$this->assertSame($d,$c->validate($d));
 }

 public function test_courier_preset_accepts_sparse_data_and_owned_logo_image_slots():void {
  $d=['title'=>'Courier','slug'=>'courier','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'courier','type'=>'media_text_split','layoutPreset'=>'corporate-courier','title'=>'Shipping','isVisible'=>true]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));
  $d['sections'][0]['items']=[['id'=>'logo','title'=>'Ninja Van','imageUrl'=>'']];$d['sections'][0]['imageSlots']=[['target'=>'item:logo:imageUrl','mediaAlt'=>'Courier brand']];
  $this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='corporate-courier';$this->assertSame($d,$c->validate($d));
 }

 public function test_payment_preset_accepts_logo_slots_and_preserves_layout_switches():void {
  $d=['title'=>'Payment','slug'=>'payment','template'=>'standard','containerWidth'=>'site','sections'=>[['id'=>'p','type'=>'media_text_split','layoutPreset'=>'corporate-payments','title'=>'Payments','isVisible'=>true,'mediaUrl'=>'','items'=>[['id'=>'logo','title'=>'PayPal','imageUrl'=>'']],'imageSlots'=>[['target'=>'item:logo:imageUrl','mediaAlt'=>'Provider']]]]];
  $c=app(PageDocumentContract::class);$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='default';$this->assertSame($d,$c->validate($d));$d['sections'][0]['layoutPreset']='corporate-payments';$this->assertSame($d,$c->validate($d));
 }

 public function test_support_network_keeps_owned_items_and_managed_photo_slot():void {
  $document=['title'=>'Growth','slug'=>'growth','template'=>'standard','containerWidth'=>'site','sections'=>[
   ['id'=>'support','type'=>'media_text_split','layoutPreset'=>'corporate-support','title'=>'Support','isVisible'=>true,'mediaUrl'=>'','items'=>[
    ['id'=>'channel','title'=>'Website','icon'=>'Globe','category'=>'channel'],['id'=>'ai','title'=>'AI assistance','icon'=>'Sparkles','category'=>'workflow'],['id'=>'caption','title'=>'Human-led.','category'=>'caption']
   ],'imageSlots'=>[['target'=>'mediaUrl','imageFit'=>'cover']]],
  ]];
  $contract=app(PageDocumentContract::class);$this->assertSame($document,$contract->validate($document));
  $document['sections'][0]['layoutPreset']='default';$this->assertSame($document,$contract->validate($document));
  $document['sections'][0]['layoutPreset']='corporate-support';$this->assertSame($document,$contract->validate($document));
 }
 public function test_sparse_corporate_presets_round_trip_and_keep_page_width():void {
  $document=['title'=>'Corporate','slug'=>'corporate','template'=>'standard','containerWidth'=>'site','sections'=>[
   ['id'=>'hero','type'=>'hero_banner','heroBannerPreset'=>'corporate-centered','title'=>'Hello','isVisible'=>true],
   ['id'=>'tabs','type'=>'media_gallery','galleryPreset'=>'product-preview-tabs','title'=>'Tabs','isVisible'=>true,'items'=>[]],
   ['id'=>'feature','type'=>'media_text_split','layoutPreset'=>'corporate-feature','title'=>'Story','isVisible'=>true,'items'=>[]],
  ]];
  $contract=app(PageDocumentContract::class);$this->assertSame($document,$contract->validate($document));
  $codec=app(\App\Domain\Cms\PageDocumentJsonCodec::class);$this->assertEquals($document,$codec->decodeDocument($codec->encodeDocument($document)));
  $document['sections'][1]['heroBannerPreset']='corporate-centered';$this->expectException(ValidationException::class);$contract->validate($document);
 }
}
