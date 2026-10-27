<?php
namespace App\Services\Marketing;

/** Read-only quality facts for the currently supported text-research pipeline.
 * A retrieved page or a model's claim of playback is not visual evidence.
 * Browser audit notes stay separate until an authenticated observation pipeline exists.
 */
class MarketingReferenceReadiness
{
 public function assess(array $sources):array
 {
  $ids=[];
  foreach($sources as $source){
   if(($source['observation_level']??null)==='publisher_transcript_unverified_sync'&&!empty($source['video_identity'])&&trim($source['bounded_excerpt']??'')!=='')$ids[$source['video_identity']]=true;
  }
  return ['status'=>$ids?'pending_visual_evidence':'insufficient_text_evidence','usable_text_references'=>count($ids),'verified_visual_references'=>0,'marketing_quality_accepted'=>false,'execution_authorized'=>false,
   'limitations'=>['Saved transcripts support spoken-content analysis only, not composition, cuts, subtitle styling or speech synchronization.','Visual observation ingestion is not yet implemented; external audit notes do not upgrade saved research.','Views alone do not establish recent viral performance or commercial results.'],
   'next_gate'=>['Observe identifiable reference footage and record time ranges and evidence.','Explain original adaptation and factual limits in three distinct concepts.','Carry the selected visual treatment into storyboard, feasibility review and final-video inspection.']];
 }
}
