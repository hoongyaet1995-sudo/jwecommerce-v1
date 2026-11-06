<?php
namespace App\Services\Marketing;
/** Copy structure and trusted destination are separate from model-written claims. */
class MarketingPostCaption {
 public function strategy(string $type):string {return match($type){
  'carousel'=>'Carousel: short opening hook paragraph; numbered slide takeaways on separate lines; a closing save/swipe invitation and enquiry CTA.',
  'promotion'=>'Promotion / Sale: a compelling campaign hook paragraph; a separate clear offer paragraph using only the user-supplied discount; short selected-product highlights on separate lines; a closing CTA. Use only supplied dates and conditions, no invented urgency, sale price or savings.',
  'poster'=>'Poster: one strong hook; one short benefit/context paragraph; a concise action invitation. Separate paragraphs with blank lines.',
  'infographic'=>'Infographic: a useful takeaway hook; 3–5 fact-based bullet points; brief interpretation and save invitation. No invented numbers.',
  'meme'=>'Meme: a short relatable setup; a separate punchline; a light conversation question. Avoid a long feature list.',
  'real-photo'=>'Lifestyle: conversational everyday context; one short product-relevant paragraph; a natural question or invitation. Never imply a real testimonial.',
  'storytelling'=>'Storytelling: story opening, challenge, turning point and reflection as separate short paragraphs, based only on confirmed source events.',
  'education'=>'Educational/case: problem paragraph; numbered practical points or approach; supported lesson paragraph. No fabricated customer outcomes.',
  'checklist'=>'Checklist: a save-worthy checklist hook paragraph; 3–5 useful ✓ or • points on separate lines; a short closing invitation. Only confirmed facts.',
  default=>throw new \InvalidArgumentException('Unknown caption type')};}
 public function customDestination(string $url):string {
  $url=trim($url);$p=parse_url($url);$host=strtolower($p['host']??'');
  $bad=!filter_var($url,FILTER_VALIDATE_URL)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||!str_contains($host,'.')||preg_match('/[\x00-\x20\x7f]/',$url)||preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)$/i',$host)||(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE));
  if($bad)throw \Illuminate\Validation\ValidationException::withMessages(['cta_url'=>'Enter a public HTTPS destination without credentials or a custom port.']);return $url;
 }
 public function destination(string $handle):?string {
  $base=rtrim((string)config('marketing.caption_public_origin',config('app.url')),'/');$p=parse_url($base);$host=strtolower($p['host']??'');
  if(!$p||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||isset($p['port'])||!$host||$host==='localhost'||str_ends_with($host,'.localhost')||!str_contains($host,'.')||(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))||($p['path']??'')!==''||!preg_match('/^[a-zA-Z0-9._-]+$/',$handle))return null;
  return $base.'/products/'.rawurlencode($handle);
 }
 public function cta(string $handle,string $platform):array {return ['url'=>$this->destination($handle),'label'=>'View product & enquire','delivery'=>$platform==='facebook'?'caption_url':'profile_link_setup_required','note'=>$platform==='facebook'?'A full product URL is included in the caption.':'This button opens the product here. Before publishing, check that the destination is available from your social profile; caption-link support is not assumed.'];}
 public function complete(string $text,string $type,string $platform,?string $destination,string $language,bool $custom=false):string {
  $text=trim(str_replace(["\r\n","\r"],"\n",$text));
  if(!str_contains($text,"\n")){$sentences=preg_split('/(?<=[.!?。！？])\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY);if(count($sentences)>1){$hook=array_shift($sentences);$list=in_array($type,['checklist','infographic','education','carousel']);$text=$hook."\n\n".implode($list?"\n":"\n\n",array_map(fn($s)=>($list?'• ':'').$s,$sentences));}}
  $zh=$language==='中文';$ms=$language==='Bahasa Melayu';
  if($platform==='facebook'&&$destination)$tail=($zh?'查看商品详情／提交咨询：':($ms?'Lihat produk dan hantar pertanyaan:':'Explore the product and send an enquiry:'))."\n".$destination;
  else $tail=$zh?'想了解更多？请到我们的主页查看网站资料或联系咨询。':($ms?'Ingin tahu lebih lanjut? Lawati profil kami untuk maklumat laman web atau pertanyaan.':'Want to learn more? Visit our profile for website details or contact us with your requirements.');
  if($custom&&$destination&&$platform!=='facebook')$tail.="\n".$destination;
  return $text."\n\n".$tail;
 }
 public function html(string $text):string {
  $parts=preg_split('~(https?://[^\s<>"\x27]+)~u',$text,-1,PREG_SPLIT_DELIM_CAPTURE);$html='';foreach($parts as $i=>$part){$e=e($part);$html.=($i%2&&filter_var($part,FILTER_VALIDATE_URL))?'<a href="'.$e.'" target="_blank" rel="noopener noreferrer">'.$e.'</a>':$e;}return $html;
 }
}
