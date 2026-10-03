<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportChatService
{
    public function owner(Request $request): array
    {
        $user = auth('customer')->user();
        if ($user && ! $user->is_admin) {
            return [hash('sha256', 'customer:'.$user->id), $user->id];
        }
        if (! $request->session()->has('support_guest_key')) {
            $request->session()->put('support_guest_key', (string) Str::uuid());
        }

        return [hash('sha256', 'guest:'.$request->session()->get('support_guest_key')), null];
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
                if ($existing->sender_role !== $role || $existing->content !== $data['content'] || $existing->staff_user_id !== $staffId) {
                    throw ValidationException::withMessages(['client_message_id' => 'This message identifier has already been used.']);
                }

                return $locked;
            }
            if ($role !== 'customer' && $locked->status === 'resolved') {
                throw ValidationException::withMessages(['content' => 'Reopen the conversation before replying.']);
            }
            $locked->messages()->create([
                'client_message_id' => $data['client_message_id'], 'sender_role' => $role,
                'staff_user_id' => $staffId, 'content' => $data['content'],
            ]);
            $locked->update(['status' => 'open', 'last_message_at' => now()]);

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

        return ['id' => $conversation->id, 'status' => $conversation->status,
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn ($message) => [
                'id' => (string) $message->id, 'clientMessageId' => $message->client_message_id,
                'role' => $message->sender_role === 'customer' ? 'customer' : ($message->sender_role === 'ai' ? 'ai' : 'staff'),
                ...($message->sender_role === 'ai' ? app(SupportActionResolver::class)->resolve($message->actions ?? [], $message->content, empty($message->actions)) : ['content'=>$message->content,'actions'=>[]]), 'createdAt' => $message->created_at->toIso8601String(),
            ])->all()];
    }

    public function adminView(SupportConversation $conversation): array
    {
        $conversation->loadMissing(['customerUser.customer', 'messages.staff', 'notes.staff']);
        $user = $conversation->customerUser;
        $name = $user ? $user->name : 'Guest';
        $avatar = '/images/support-person.svg';
        $messages = $conversation->messages->sortBy('id');
        $last = $messages->last();

        return [
            'id' => $conversation->id, 'channel' => 'web_guest', 'title' => 'Storefront Support',
            'customer' => [
                'id' => $user ? 'customer-'.$user->id : $conversation->id, 'name' => $name, 'avatar' => $avatar,
                'email' => $user?->email, 'phone' => $user?->customer?->phone, 'language' => 'en',
                'firstSeenAt' => $conversation->created_at->toDateString(), 'lastSeenAt' => $conversation->updated_at->toDateString(),
                'tags' => [], 'totalOrdersCount' => 0, 'lifetimeValue' => 0, 'leadScore' => 0,
                'leadStage' => 'Discovery', 'qualification' => 'support_ticket',
            ],
            'snippet' => $last?->content ?? '', 'status' => $conversation->status,
            'unreadCount' => $messages->filter(fn ($m) => $m->sender_role === 'customer' && $m->id > $conversation->staff_read_message_id)->count(),
            'isPinned' => false, 'priority' => 'normal', 'activeAiDraft' => null,
            'aiSummary' => ['intent' => '', 'sentiment' => 'neutral', 'keyIssue' => '', 'suggestedAction' => '', 'summaryMarkdown' => ''],
            'lastMessageAt' => $conversation->last_message_at?->diffForHumans() ?? '',
            'createdAt' => $conversation->created_at->toIso8601String(),
            'messages' => $messages->map(fn ($m) => [
                'id' => (string) $m->id, 'conversationId' => $conversation->id, 'senderRole' => $m->sender_role,
                'senderName' => $m->sender_role === 'customer' ? $name : ($m->sender_role === 'ai' ? 'AI Support' : ($m->staff?->name ?? 'Support')),
                'senderAvatar' => $avatar, 'timestamp' => $m->created_at->format('d M H:i'),
                'content' => $m->content, 'type' => 'text', 'deliveryStatus' => 'sent',
            ])->values()->all(),
            'internalNotes' => $conversation->notes->map(fn ($n) => [
                'id' => (string) $n->id, 'authorName' => $n->staff?->name ?? 'Support',
                'authorAvatar' => $avatar, 'timestamp' => $n->created_at->format('d M H:i'), 'content' => $n->content,
            ])->all(),
        ];
    }
}
