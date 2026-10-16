<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportChatService
{
    private ?\Illuminate\Support\Collection $messengerAccounts = null;
    private ?\Illuminate\Support\Collection $cadenceBatches = null;
    public function primeCadence(array $ids):void
    {
        $this->cadenceBatches=collect();
        if(!$ids||!\Illuminate\Support\Facades\Schema::hasTable('support_reply_batches'))return;
        $latest=\App\Models\SupportReplyBatch::selectRaw('MAX(latest_source_id)')->whereIn('conversation_id',$ids)->groupBy('conversation_id');
        $this->cadenceBatches=\App\Models\SupportReplyBatch::whereIn('conversation_id',$ids)->whereIn('latest_source_id',$latest)
            ->get(['id','conversation_id','status','next_index','stop_reason','trace'])->keyBy('conversation_id');
    }
    private function cadenceSummary(SupportConversation $conversation):?array
    {
        if($this->cadenceBatches===null)$this->primeCadence([$conversation->id]);
        $b=$this->cadenceBatches->get($conversation->id);
        return $b?['id'=>$b->id,'status'=>$b->status,'publishedSegments'=>$b->next_index,'totalSegments'=>$b->trace['cadence']['totalSegments']??0,'stopReason'=>$b->stop_reason]:null;
    }
    public function owner(Request $request): array
    {
        if($request->filled('enquiry_id')){
            $id=$request->input('enquiry_id');
            abort_unless(is_string($id)&&Str::isUuid($id),404);
            $enquiry=\App\Models\ProductEnquiry::find($id);
            abort_unless($enquiry&&app(\App\Services\ProductEnquiryService::class)->canView($request,$enquiry),404);
            $conversation=$enquiry->supportConversation;
            abort_unless($conversation,404);
            return [$conversation->owner_hash,$conversation->customer_user_id];
        }
        $user = auth('customer')->user();
        $generation = $request->query->has('support_generation')?($request->query('support_generation')??''):$request->session()->get('support_generation', '');
        abort_unless(is_string($generation)&&($generation===''||(str_starts_with($generation,':')&&Str::isUuid(substr($generation,1)))),404);
        if ($user && ! $user->is_admin) {
            return [hash('sha256', 'customer:'.$user->id.$generation), $user->id];
        }
        if (! $request->session()->has('support_guest_key')) {
            $request->session()->put('support_guest_key', (string) Str::uuid());
        }

        return [hash('sha256', 'guest:'.$request->session()->get('support_guest_key').$generation), null];
    }

    public function resolvedAction(Request $request, string $action): ?SupportConversation
    {
        return DB::transaction(function () use ($request, $action) {
            $current = $this->current($request);
            abort_unless($current, 409, 'No resolved conversation.');
            $locked = SupportConversation::whereKey($current->id)->lockForUpdate()->firstOrFail();
            if ($action === 'reopen' && $locked->status === 'waiting_human') return $locked;
            abort_unless($locked->status === 'resolved', 409, 'This conversation is not resolved.');
            if ($action === 'new') {
                $request->session()->forget('support_enquiry_id');
                $request->session()->put('support_generation', ':'.Str::uuid());
                return null;
            }
            $locked->notes()->create(['content'=>'[Control] resolved → waiting_human (customer reopened)']);
            $locked->update(['status'=>'waiting_human']);
            \App\Models\User::where('is_admin',true)->chunkById(100, function($staff) use($locked) {
                \Illuminate\Support\Facades\Notification::send($staff,new \App\Notifications\SupportHumanRequested($locked->id));
            });
            return $locked;
        });
    }

    public function current(Request $request): ?SupportConversation
    {
        [$owner] = $this->owner($request);

        return SupportConversation::where('owner_hash', $owner)->first();
    }

    public function sendCustomer(Request $request, array $data): SupportConversation
    {
        [$owner, $userId] = $this->owner($request);
        $conversation = SupportConversation::firstOrCreate(['owner_hash' => $owner], [
            'id' => (string) Str::uuid(), 'customer_user_id' => $userId,
        ]);

        return $this->append($conversation, $data, 'customer');
    }

    public function append(SupportConversation $conversation, array $data, string $role, ?int $staffId = null): SupportConversation
    {
        return DB::transaction(function () use ($conversation, $data, $role, $staffId) {
            $locked = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->messages()->where('client_message_id', $data['client_message_id'])->first();
            if ($existing) {
                if ($existing->sender_role !== $role || $existing->content !== $data['content'] || $existing->staff_user_id !== $staffId || $existing->attachments()->value('id') !== ($data['attachment_id'] ?? null)) {
                    throw ValidationException::withMessages(['client_message_id' => 'This message identifier has already been used.']);
                }

                return $locked;
            }
            if ($role !== 'customer' && $locked->status === 'resolved') {
                throw ValidationException::withMessages(['content' => 'Reopen the conversation before replying.']);
            }
            $lastAi = $locked->messages()->where('sender_role','ai')->latest('id')->first();
            $buttonRequest = ($data['request_human'] ?? false) && collect($lastAi?->actions ?? [])->contains(fn($a)=>($a['type']??'')==='request_human' && app(SupportEscalationPolicy::class)->enabledAction($a,$locked,app(\App\Services\SupportFaqService::class)->read()));
            if ($role === 'customer' && $buttonRequest && !$locked->customer_user_id) {
                if (empty($data['contact'])) throw ValidationException::withMessages(['contact'=>'Please provide your name and email for human support.']);
                $contact=$data['contact'];
                $contact['email']=strtolower(trim($contact['email']));
                $contact['name']=trim($contact['name']);
                $locked->notes()->create(['content'=>'[Contact] '.json_encode($contact,JSON_UNESCAPED_UNICODE)]);
                // Contact claims are unverified; the CRM resolver records them without merging users.
            }
            $message=$locked->messages()->create([
                'client_message_id' => $data['client_message_id'], 'sender_role' => $role,
                'staff_user_id' => $staffId, 'content' => $data['content'],
            ]);
            app(SupportAttachmentService::class)->bind($locked,$message,$data['attachment_id'] ?? null,$staffId);
            $status = $locked->status === 'resolved' ? 'open' : $locked->status;
            if ($role==='customer' && $locked->notes()->where('content','like','[Archive] %')->latest('id')->value('content')==='[Archive] true') {
                $locked->notes()->create(['content'=>'[Archive] false']);
            }
            if ($role === 'human_agent') $status = 'human_mode';
            if ($role === 'customer' && $status !== 'human_mode' && $buttonRequest) $status = 'waiting_human';
            if ($status !== $locked->status) {
                $locked->notes()->create(['staff_user_id' => $staffId, 'content' => '[Control] '.$locked->status.' → '.$status]);
                if ($status === 'waiting_human') {
                    \App\Models\User::where('is_admin',true)->chunkById(100, function($staff) use($locked) {
                        \Illuminate\Support\Facades\Notification::send($staff,new \App\Notifications\SupportHumanRequested($locked->id));
                    });
                }
            }
            $locked->update(['status' => $status, 'last_message_at' => now()]);
            if ($role === 'customer') {
                $profile=$data['contact']??null;
                if(!$profile){
                    $contactNote=$locked->notes()->where('content','like','[Contact] %')->latest('id')->value('content');
                    $stored=$contactNote?json_decode(substr($contactNote,10),true):null;
                    // Retain supplied contact details, without ever treating them as verified identity.
                    if(is_array($stored))$profile=array_filter(array_intersect_key($stored,array_flip(['name','email','phone'])),fn($value)=>is_string($value));
                }
                app(\App\Services\Customers\CustomerContactResolver::class)->associate($locked, ['platform'=>'storefront','merchant_key'=>config('services.facebook.merchant_key','jw-ecommerce'),'app_id'=>'storefront','account_id'=>'storefront','sender_id'=>$locked->customer_user_id ? 'user:'.$locked->customer_user_id : $locked->owner_hash], $profile?:['name'=>'Guest']);
            }
            app(SupportCustomerMemoryService::class)->schedule($locked,$message);
            app(SupportMessageAlertService::class)->received($locked,$message);

            return $locked;
        });
    }

    public function read(SupportConversation $conversation, int $through): void
    {
        DB::transaction(function () use ($conversation, $through) {
            $locked = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $latest = (int) $locked->messages()->max('id');
            $locked->update(['staff_read_message_id' => max((int) $locked->staff_read_message_id, min($through, $latest))]);
        });
    }

    public function publicView(?SupportConversation $conversation): ?array
    {
        if (! $conversation) {
            return null;
        }

        $settings = app(\App\Services\SupportFaqService::class)->read();
        return ['requiresContact'=>!auth('customer')->check(), 'id' => $conversation->id, 'status' => $conversation->status, 'waitingNotice'=>$conversation->status === 'waiting_human' ? $settings['personality']['fallbackHandoffMessage'] : null,
            'messages' => $conversation->messages()->with('attachments')->orderBy('id')->get()->map(fn ($message) => [
                'id' => (string) $message->id, 'clientMessageId' => $message->client_message_id,
                'attachments'=>app(SupportAttachmentService::class)->view($message),
                'role' => $message->sender_role === 'customer' ? 'customer' : ($message->sender_role === 'ai' ? 'ai' : 'staff'),
                ...($message->sender_role === 'ai' ? app(SupportActionResolver::class)->resolve(array_values(array_filter($message->actions ?? [], fn($a)=>app(SupportEscalationPolicy::class)->enabledAction($a,$conversation,$settings))), $message->content, empty($message->actions)) : ['content'=>$message->content,'actions'=>$message->sender_role==='human_agent'?array_values(array_filter($message->actions??[],fn($a)=>is_array($a)&&($a['type']??'')==='view_quotation'&&preg_match('#^/quotations/[0-9a-f-]{36}$#D',$a['url']??''))):[]]), 'createdAt' => $message->created_at->toIso8601String(),
            ])->all()];
    }

    public function adminView(SupportConversation $conversation): array
    {
        $conversation->loadMissing(['customerUser.customer', 'customer.channelIdentities', 'messages.staff', 'messages.attachments', 'notes.staff']);
        $user = $conversation->customerUser;
        $contactNote=$conversation->notes->filter(fn($n)=>str_starts_with($n->content,'[Contact] '))->last();
        $contact=$contactNote ? json_decode(substr($contactNote->content,10),true) : [];
        $messenger=in_array($conversation->channel??'web_guest',['facebook','instagram','whatsapp'],true);
        if ($messenger && $this->messengerAccounts === null) {
            $this->messengerAccounts = \App\Models\ManagedSocialAccount::where('merchant_key', config('services.facebook.merchant_key','jw-ecommerce'))
                ->whereIn('platform',['facebook','instagram','whatsapp'])->get()->keyBy(fn($a)=>$a->platform.':'.$a->provider_account_id);
        }
        $crm = $conversation->customer;
        $name = $crm ? trim($crm->first_name.' '.$crm->last_name) : ($user ? $user->name : ($contact['name']??($messenger?(match($conversation->channel){'instagram'=>'Instagram customer','whatsapp'=>'WhatsApp customer',default=>'Messenger customer'}):'Guest')));
        $identity = $crm?->channelIdentities->first(fn ($i) =>
            $i->platform === $conversation->channel
            && $i->merchant_key === $conversation->merchant_key
            && $i->account_id === $conversation->provider_page_id
            && $i->external_sender_id === $conversation->provider_sender_id
            && $i->app_id === $this->messengerAccounts?->get($conversation->channel.':'.$conversation->provider_page_id)?->app_id);
        $name = $identity?->display_name ?: ($identity?->username ?: $name);
        $profileAvatar = $identity?->avatar_url ?: $crm?->avatar;
        $avatar = is_string($profileAvatar) && (str_starts_with($profileAvatar, 'https://') || str_starts_with($profileAvatar, '/images/'))
            ? $profileAvatar : '/images/support-person.svg';
        $messages = $conversation->messages->sortBy('id');
        $last = $messages->last();

        return [
            'id' => $conversation->id, 'channel' => $conversation->channel??'web_guest', 'title' => $messenger?$conversation->provider_page_name:'Storefront Support',
            ...($messenger ? app($conversation->channel==='whatsapp'?\App\Services\Social\WhatsAppReplyService::class:\App\Services\Social\MessengerReplyService::class)->readiness($conversation, $this->messengerAccounts?->get($conversation->channel.':'.$conversation->provider_page_id), false) : ['canReply'=>true]),'pageName'=>$messenger?$conversation->provider_page_name:null,
            'customer' => [
                'id' => $user ? 'customer-'.$user->id : $conversation->id, 'name' => $name, 'avatar' => $avatar,
                'platformUserId' => $messenger ? $conversation->provider_sender_id : null,
                'handle' => $identity?->username,
                'email' => $crm?->email ?? $user?->email ?? ($contact['email']??null), 'phone' => $crm?->phone ?? $user?->customer?->phone ?? ($contact['phone']??null), 'language' => 'en',
                'firstSeenAt' => $conversation->created_at->toDateString(), 'lastSeenAt' => $conversation->updated_at->toDateString(),
                'tags' => [], 'totalOrdersCount' => 0, 'lifetimeValue' => 0, 'leadScore' => 0,
                'leadStage' => 'Discovery', 'qualification' => 'support_ticket',
            ],
            'mediaCapabilities'=>app(SupportMediaCapabilities::class)->forChannel($conversation->channel??'web_guest'),
            'snippet' => $last?->content ?: ($last?->attachments->isNotEmpty() ? match($last->attachments->first()->kind){'audio'=>'Voice message','document'=>'Document',default=>'Image'} : ''), 'status' => $conversation->status,
            'isArchived' => $conversation->notes->filter(fn($n)=>str_starts_with($n->content,'[Archive] '))->sortBy('id')->last()?->content==='[Archive] true',
            'unreadCount' => $messages->filter(fn ($m) => $m->sender_role === 'customer' && $m->id > $conversation->staff_read_message_id)->count(),
            'isPinned' => $conversation->notes->filter(fn($n)=>str_starts_with($n->content,'[Pin] '))->sortBy('id')->last()?->content==='[Pin] true', 'priority' => 'normal', 'activeAiDraft' => null,
            'handoffBrief'=>app(SupportHandoffBrief::class)->build($conversation),
            'replyCadence'=>$this->cadenceSummary($conversation),
            'aiSummary' => ['intent' => '', 'sentiment' => 'neutral', 'keyIssue' => '', 'suggestedAction' => '', 'summaryMarkdown' => ''],
            'lastMessageAt' => $conversation->last_message_at?->diffForHumans() ?? '',
            'lastMessageTimestamp' => $conversation->last_message_at?->toIso8601String(),
            'createdAt' => $conversation->created_at->toIso8601String(),
            'messages' => $messages->map(fn ($m) => [
                'id' => (string) $m->id, 'conversationId' => $conversation->id, 'senderRole' => $m->sender_role,
                'aiTrace'=>$m->sender_role==='ai'?$m->ai_trace:null,
                'senderName' => $m->sender_role === 'customer' ? $name : ($m->sender_role === 'ai' ? 'AI Support' : ($m->staff?->name ?? 'Support')),
                'senderAvatar' => $m->sender_role === 'customer' ? $avatar : '/images/support-person.svg', 'timestamp' => $m->created_at->format('d M H:i'),
                'attachments'=>app(SupportAttachmentService::class)->view($m,true), 'content' => $m->content, 'type' => $m->attachments->isNotEmpty() ? ($m->attachments->first()->kind ?? 'image') : 'text', 'deliveryStatus' => $m->delivery_status ?? 'sent', 'sendError' => $m->send_error, 'clientMessageId' => $m->client_message_id,
            ])->values()->all(),
            'internalNotes' => $conversation->notes->map(fn ($n) => [
                'id' => (string) $n->id, 'authorName' => $n->staff?->name ?? 'Support',
                'authorAvatar' => '/images/support-person.svg', 'timestamp' => $n->created_at->format('d M H:i'), 'content' => $n->content,
            ])->all(),
        ];
    }
}
