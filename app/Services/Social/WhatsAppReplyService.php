<?php

namespace App\Services\Social;

use App\Models\ManagedSocialAccount;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WhatsAppReplyService
{
    private function account(SupportConversation $conversation): ?ManagedSocialAccount
    {
        if ($conversation->merchant_key !== app(FacebookAccountService::class)->merchant()) return null;
        return ManagedSocialAccount::where('merchant_key', $conversation->merchant_key)->where('platform', $conversation->channel)
            ->where('app_id', (string) config('services.whatsapp.app_id'))->where('provider_account_id', $conversation->provider_page_id)->first();
    }

    public function readiness(SupportConversation $conversation, ?ManagedSocialAccount $account = null, bool $lookup = true): array
    {
        if (!$account && $lookup) $account = $this->account($conversation);
        if ($account && ($account->platform !== $conversation->channel || $account->merchant_key !== $conversation->merchant_key || $account->provider_account_id !== $conversation->provider_page_id || $account->app_id !== (string) config('services.whatsapp.app_id'))) $account = null;
        $lastCustomer = $conversation->relationLoaded('messages')
            ? $conversation->messages->where('sender_role', 'customer')->sortByDesc('created_at')->first()?->created_at
            : $conversation->messages()->where('sender_role', 'customer')->latest('created_at')->value('created_at');
        $archived = $conversation->relationLoaded('notes')
            ? $conversation->notes->filter(fn($n) => str_starts_with($n->content, '[Archive] '))->sortBy('id')->last()?->content
            : $conversation->notes()->where('content', 'like', '[Archive] %')->latest('id')->value('content');
        $expires = $lastCustomer ? \Illuminate\Support\Carbon::parse($lastCustomer)->addHours(24) : null;
        $reason = match (true) {
            $conversation->merchant_key !== app(FacebookAccountService::class)->merchant() || !$account => 'The WhatsApp account connection is unavailable for this store.',
            $conversation->status === 'resolved' => 'Conversation resolved. Reopen to reply.',
            $archived === '[Archive] true' => 'Restore this conversation before replying.',
            config('services.'.$conversation->channel.'.inbound_routing_ready') !== true => 'WhatsApp setup is awaiting your administrator.',
            $account->state !== 'connected' || !$account->inbound_enabled || !$account->inbound_verified_at => $account->messaging_error ?: 'Reconnect this WhatsApp account and verify its messaging permissions.',
            !in_array('whatsapp_business_messaging', $account->permissions ?? [], true) => 'Review Facebook authorization and grant messaging permission.',
            !$expires || $expires->lessThanOrEqualTo(now()) => 'The 24-hour WhatsApp reply window has ended. An approved template is required; wait for a new customer message.',
            !preg_match('/^\d+$/', (string) $conversation->provider_sender_id) || !preg_match('/^\d+$/', (string) $conversation->provider_page_id) => 'The WhatsApp recipient could not be verified.',
            default => null,
        };
        return ['canReply' => $reason === null, 'replyBlockedReason' => $reason, 'replyWindowExpiresAt' => $expires?->toIso8601String()];
    }

    public function send(SupportConversation $conversation, array $data, int $staffId): SupportMessage
    {
        // Lock order matches ingress: WhatsApp account first, then conversation. No provider call holds database locks.
        [$message, $account, $reserved] = DB::transaction(function () use ($conversation, $data, $staffId) {
            $account = $this->account($conversation);
            if ($account) $account = ManagedSocialAccount::whereKey($account->id)->lockForUpdate()->first();
            $locked = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->messages()->where('client_message_id', $data['client_message_id'])->first();
            if ($existing) {
                if ($existing->sender_role !== 'human_agent' || $existing->staff_user_id !== $staffId || $existing->content !== $data['content'] || $existing->attachments()->value('id') !== ($data['attachment_id'] ?? null)) {
                    throw ValidationException::withMessages(['client_message_id' => 'This message identifier was already used.']);
                }
                if ($existing->delivery_status === 'sending' && $existing->updated_at->lt(now()->subSeconds(30))) {
                    $existing->update(['delivery_status' => 'unknown', 'send_error' => 'Send confirmation is unavailable. Check WhatsApp; do not resend this reply.']);
                    $locked->touch();
                }
                if ($existing->delivery_status !== 'failed' || empty($data['retry'])) return [$existing, $account, false];
            }
            $ready = $this->readiness($locked, $account, false);
            abort_unless($ready['canReply'], 409, $ready['replyBlockedReason']);
            $imageHash=!empty($data['attachment_id'])?\App\Models\SupportAttachment::whereKey($data['attachment_id'])->where('conversation_id',$locked->id)->where('staff_user_id',$staffId)->value('sha256'):null;
            // An uncertain prior send must be reconciled by a person, never silently repeated under a new UUID.
            abort_if($locked->messages()->where('sender_role', 'human_agent')->where('content', $data['content'])->when(!empty($data['attachment_id']),fn($q)=>$q->whereHas('attachments',fn($a)=>$a->where('sha256',$imageHash)),fn($q)=>$q->whereDoesntHave('attachments'))->whereIn('delivery_status', ['sending', 'unknown'])->exists(), 409, 'This reply is awaiting confirmation. Check WhatsApp before sending it again.');
            $message = $existing ?? $locked->messages()->create([
                'client_message_id' => $data['client_message_id'], 'sender_role' => 'human_agent', 'staff_user_id' => $staffId,
                'content' => $data['content'], 'delivery_status' => 'sending',
            ]);
            app(\App\Services\Support\SupportAttachmentService::class)->bind($locked,$message,$data['attachment_id'] ?? null,$staffId);
            if ($existing) $message->update(['delivery_status' => 'sending', 'send_error' => null]);
            if ($locked->status !== 'human_mode') {
                $locked->notes()->create(['staff_user_id'=>$staffId,'content'=>'[Control] '.$locked->status.' â†’ human_mode']);
                $locked->update(['status'=>'human_mode']);
            } else $locked->touch();
            return [$message, $account, true];
        });
        if (!$reserved) return $message;

        return $this->deliver($message, $account, $data['content'], $conversation->provider_sender_id);
    }

    public function sendGenerated(SupportMessage $reply, SupportMessage $source): void
    {
        $reserved = DB::transaction(function () use ($reply, $source) {
            $conversation = SupportConversation::find($reply->conversation_id);
            if (!$conversation || !in_array($conversation->channel,['whatsapp'],true) || $source->conversation_id !== $conversation->id) return null;
            $account = $this->account($conversation);
            if ($account) $account = ManagedSocialAccount::whereKey($account->id)->lockForUpdate()->first();
            $locked = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $stored = $locked->messages()->whereKey($reply->id)->firstOrFail();
            // The durable generated message is its own one-attempt delivery reservation.
            if ($stored->sender_role !== 'ai') return null;
            if ($stored->delivery_status !== null) {
                if ($stored->delivery_status === 'sending' && $stored->updated_at->lt(now()->subSeconds(30))) {
                    $stored->update(['delivery_status'=>'unknown','send_error'=>'Send confirmation is unavailable. Check WhatsApp; do not resend this reply.']);
                    $locked->touch();
                }
                return null;
            }
            $acknowledgement = collect($stored->actions ?? [])->contains(fn($a) => ($a['type'] ?? '') === 'request_human' && ($a['reason'] ?? '') === 'explicit_request');
            $settings = app(\App\Services\SupportFaqService::class)->read();
            $enabled = app(\App\Services\Support\SupportAiPolicy::class)->enabled($settings, $locked->channel);
            $eligible = $enabled && ($locked->status === 'open' || ($locked->status === 'waiting_human' && $acknowledgement));
            $eligible = $eligible && $stored->ai_settings_version !== null && $stored->ai_control_version !== null
                && (int)$stored->ai_settings_version === (int)($settings['version'] ?? 0)
                && (int)$stored->ai_control_version === (int)$locked->notes()->max('id');
            if (!$eligible || (int)$locked->messages()->where('sender_role','customer')->max('id') !== $source->id
                || (int)$locked->messages()->where('sender_role','human_agent')->max('id') >= $source->id) {
                $stored->update(['delivery_status'=>'failed','send_error'=>'AI reply cancelled after the conversation changed.']);
                $locked->touch();
                return null;
            }
            $ready = $this->readiness($locked,$account,false);
            if (!$ready['canReply']) {
                $stored->update(['delivery_status'=>'failed','send_error'=>$ready['replyBlockedReason']]);
                $locked->touch();
                return null;
            }
            $content = $stored->content;
            $resolved = app(\App\Services\Support\SupportActionResolver::class)->resolve($stored->actions ?? [],$content);
            foreach ($resolved['actions'] as $action) {
                // WhatsApp renders trusted web actions as ordinary public links; never identity-derived order access.
                if (isset($action['url']) && str_starts_with($action['url'],'/') && !str_starts_with($action['url'],'//')) {
                    $line = "\n".($action['label'] ?? 'View').': '.rtrim(config('app.url'),'/').$action['url'];
                    if (mb_strlen($content.$line) <= 2000) $content .= $line;
                }
            }
            $stored->update(['content'=>$content,'delivery_status'=>'sending','send_error'=>null]);
            $locked->touch();
            return [$stored,$account,$locked->provider_sender_id];
        });
        if ($reserved) $this->deliver($reserved[0],$reserved[1],$reserved[0]->content,$reserved[2]);
    }

    private function deliver(SupportMessage $message, ManagedSocialAccount $account, string $content, string $recipient): SupportMessage
    {
        $status='unknown';$providerId=null;$error='Send confirmation is unavailable. Check WhatsApp; do not resend this reply.';
        $deliveryAttempted=false;
        try {
            $payload=app(\App\Services\Support\SupportImageTransport::class)->payload($message,$account);
            $deliveryAttempted=true;
            $response=Http::withToken($account->access_token)->acceptJson()->connectTimeout(5)->timeout(12)->post(
                'https://graph.facebook.com/'.config('services.whatsapp.graph_version','v25.0').'/'.$account->provider_account_id.'/messages',
                ['messaging_product'=>'whatsapp','recipient_type'=>'individual','to'=>$recipient,...$payload]);
            $body=$response->json();$id=data_get($body,'messages.0.id');
            if($response->successful()&&($body['messaging_product']??null)==='whatsapp'&&is_string($id)&&strlen($id)>0&&strlen($id)<=512&&(string)data_get($body,'contacts.0.wa_id')===$recipient){$status='accepted';$providerId=$id;$error=null;}
            elseif(is_array($body['error']??null)){$status='failed';$error=match((int)($body['error']['code']??0)){190=>'WhatsApp authorization expired. Reconnect this account.',131047=>'An approved WhatsApp template is required outside the customer service window.',default=>'Meta rejected this WhatsApp reply. Check the account before retrying.'};}
        } catch(\Illuminate\Contracts\Encryption\DecryptException $e){$status='failed';$error='WhatsApp authorization is unavailable.';}
        catch(\Throwable $e){if(!$deliveryAttempted){$status='failed';$error='Media upload failed. Your attachment is retained; check the connection before retrying.';}}
        DB::transaction(function()use($message,$account,$recipient,$status,$providerId,$error){
            ManagedSocialAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $thread=SupportConversation::whereKey($message->conversation_id)->lockForUpdate()->firstOrFail();
            $stored=SupportMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            // Do not overwrite a receipt which raced the API response.
            if(!in_array($stored->delivery_status,['delivered','read'],true))$stored->update(['delivery_status'=>$status,'provider_message_id'=>$providerId,'send_error'=>$error]);
            app(WhatsAppStatusService::class)->reconcile($account,$stored,$recipient);
            $thread->touch();
        });
        return $message->fresh();
    }
}
