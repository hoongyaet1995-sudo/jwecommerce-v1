<?php
namespace App\Services\Support;

use App\Models\SupportConversation;

class SupportDialogueContext
{
    public function recent(SupportConversation $conversation): array
    {
        return $conversation->messages()->whereIn('sender_role',['customer','ai','human_agent'])
            ->where('content','!=','')->whereDoesntHave('attachments')->orderByDesc('id')->limit(10)
            ->get()->reverse()->filter(fn($message)=>!str_starts_with($message->content,'[Unsupported '))
            ->map(function($message){
                $role=match($message->sender_role){'customer'=>'Customer: ','human_agent'=>'Staff (context only): ',default=>'Assistant (context only): '};
                // Order messages may contain server-only customer facts; keep only public navigation context.
                if(collect($message->actions??[])->contains(fn($action)=>($action['type']??null)==='view_order_tracking')){
                    return $role.'Provided the Order Tracking entry; private details omitted.';
                }
                return $role.app(UnifiedKnowledgeService::class)->redact(mb_substr($message->content,0,600));
            })->values()->all();
    }
}
