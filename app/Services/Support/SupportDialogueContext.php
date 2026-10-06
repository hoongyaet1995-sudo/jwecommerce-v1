<?php
namespace App\Services\Support;
use App\Models\SupportConversation;
class SupportDialogueContext {
 public function recent(SupportConversation $conversation):array {
  return $conversation->messages()->whereIn('sender_role',['customer','ai'])->where('content','!=','')->whereDoesntHave('attachments')->orderByDesc('id')->limit(6)->get()->reverse()->filter(fn($m)=>!str_starts_with($m->content,'[Unsupported ')&&!collect($m->actions??[])->contains(fn($a)=>($a['type']??null)==='view_order_tracking'))->map(fn($m)=>($m->sender_role==='customer'?'Customer: ':'Assistant (context only): ').app(UnifiedKnowledgeService::class)->redact(mb_substr($m->content,0,600)))->values()->all();
 }
}
