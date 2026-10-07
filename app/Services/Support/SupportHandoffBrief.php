<?php
namespace App\Services\Support;
use App\Models\SupportConversation;
class SupportHandoffBrief
{
 public function build(SupportConversation $conversation):?array
 {
  $messages=$conversation->messages;
  $last=$messages->where('sender_role','ai')->filter(fn($m)=>is_array($m->ai_trace))->last();
  if(!in_array($conversation->status,['waiting_human','human_mode'],true)&&!($last?->ai_trace['escalation']??null))return null;
  $trace=$last?->ai_trace??[];
  return ['customerRequests'=>$messages->where('sender_role','customer')->filter(fn($m)=>trim($m->content)!=='')->take(-4)->map(fn($m)=>mb_substr(app(UnifiedKnowledgeService::class)->redact($m->content),0,400))->values()->all(),
   'checks'=>array_values(array_map(fn($t)=>array_intersect_key($t,array_flip(['tool','status'])),array_slice($trace['tools']??[],0,5))),
   'reason'=>$trace['escalation']??'staff_review','missingContext'=>$trace['clarification']??null,
   'unresolvedTopics'=>array_values(array_map(fn($p)=>$p['index'],array_filter($trace['coverage']??[],fn($p)=>$p['status']==='missing'))),
   'handoffStatus'=>$conversation->status==='human_mode'?'staff_handling':($conversation->status==='waiting_human'?'pending':'offered')];
 }
}
