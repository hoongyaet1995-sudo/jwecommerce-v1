<?php

namespace App\Services\Support;

use App\Models\StoreSetting;
use App\Models\SupportConversation;
use App\Models\SupportFaqDraft;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\SupportFaqService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SupportImprovementService
{
    public const MISSING = ['no_match', 'partial'];

    public const FAILURES = ['generation_unavailable', 'coverage_incomplete', 'query_unavailable', 'service_unavailable'];

    public function activatedAt(): Carbon
    {
        $value = StoreSetting::where('key', 'support_improvement')->value('value');
        abort_unless(isset($value['activatedAt']), 503, 'Support improvement is not ready. Ask an administrator to complete the update.');

        return Carbon::parse($value['activatedAt']);
    }

    public function sanitize(string $text, ?SupportConversation $c = null): string
    {
        $text = app(UnifiedKnowledgeService::class)->redact(strip_tags($text));
        $text = preg_replace('/\b(?:order|reference|tracking|AWB)\s*(?:number|no\.?|id|#|:)?\s*[:#]?\s*[A-Z0-9]+(?:[-_][A-Z0-9]+)+\b/iu', '[private reference]', $text);
        $text = preg_replace('/\b(?:order|reference|tracking|AWB)\s*(?:number|no\.?|id|#|:)?\s*[:#]?\s*(?=[A-Z0-9]*\d)[A-Z0-9]{3,}\b/iu', '[private reference]', $text);
        $text = preg_replace('/\b(?:address|alamat|shipping address)\s*[:=]\s*[^\n]+/iu', '[private address]', $text);
        $text = preg_replace('/\b(?:\d{1,5}[\w\/-]*\s+)(?:jalan|lorong|jln|street|road|avenue)\b[^\n.!?]*/iu', '[private address]', $text);
        $text = preg_replace('/\b\d{1,5}\s+[\p{L}\s]{2,60}\b(?:street|road|avenue|lane|drive)\b[^\n.!?]*/iu', '[private address]', $text);
        if ($c) {
            foreach ([$c->customerUser?->name, $c->customerUser?->email, $c->provider_sender_id, trim(($c->customer?->first_name ?? '').' '.($c->customer?->last_name ?? '')), $c->customer?->phone, $c->customer?->email] as $private) {
                if (is_string($private) && mb_strlen($private) > 2) {
                    $text = str_ireplace($private, '[private detail]', $text);
                }
            }
        }

        return trim($text);
    }

    private function eligibleSource(SupportMessage $source): SupportConversation
    {
        $c = SupportConversation::findOrFail($source->conversation_id);
        $evidence = $c->messages()->where('sender_role', 'ai')->where('ai_trace->sourceMessageId', $source->id)->latest('id')->first();
        $reason=$this->draftBlockReason($source,$evidence);
        abort_if($reason!==null,422,$reason ?? 'This question cannot be reviewed.');

        return $c;
    }

    public function overview(int $days, string $channel): array
    {
        $activated=$this->activatedAt();
        $from = now()->subDays($days)->max($activated);
        $rows = SupportMessage::where('sender_role', 'ai')->whereNotNull('ai_trace')->where('created_at', '>=', $from)
            ->whereIn('conversation_id', SupportConversation::select('id')->when($channel !== 'all', fn ($q) => $q->where('channel', $channel)))
            ->latest('id')->limit(5001)->get();
        $limited = $rows->count() > 5000;
        $rows = $rows->take(5000);
        $sources = SupportMessage::with('attachments')->whereIn('id', $rows->map(fn ($m) => $m->ai_trace['sourceMessageId'] ?? null)->filter()->unique())->get()->keyBy('id');
        $conversations = SupportConversation::with(['customerUser', 'customer'])->whereIn('id', $sources->pluck('conversation_id'))->get()->keyBy('id');
        $counts = ['received' => 0, 'answered' => 0, 'improve' => 0, 'handoff' => 0, 'failures' => 0, 'filtered'=>0];
        $questions = [];
        $failures = [];
        $seen = [];
        foreach ($rows as $m) {
            $id = $m->ai_trace['sourceMessageId'] ?? null;
            $source = $sources->get($id);
            $c = $conversations->get($source?->conversation_id);
            if (! $source || ! $c || $source->sender_role !== 'customer' || $source->created_at->lt($from) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $counts['received']++;
            $decision = $m->ai_trace['decision'] ?? '';
            if(in_array($decision,['off_topic','harassment'],true)){$counts['filtered']++;continue;}
            $text = $this->sanitize($source->content, $c);
            $base = ['sourceMessageId' => $source->id, 'conversationId' => $c->id, 'question' => mb_substr($text, 0, 1000), 'channel' => $c->channel, 'resolved' => $c->status === 'resolved', 'createdAt' => $source->created_at->toIso8601String()];
            $blocked=$this->draftBlockReason($source,$m,$activated);$base+=['canCreateDraft'=>$blocked===null,'draftBlockedReason'=>$blocked];
            if ($this->failed($m) || in_array($m->delivery_status, ['failed', 'unknown', 'pending'], true)) {
                $counts['failures']++;
                $failures[] = $base + ['reason' => match ($decision) {
                    'coverage_incomplete' => 'Reply was incomplete','query_unavailable' => 'A business check could not be completed',default => 'Reply could not be completed or delivered'
                }];
            } elseif ($this->missing($m) && $text !== '' && $source->attachments->isEmpty()) {
                $counts['improve']++;
                $key = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $text)));
                if (isset($questions[$key])) {
                    $questions[$key]['count']++;
                } else {
                    $questions[$key] = $base + ['count' => 1, 'reason' => $decision === 'partial' ? 'Part of the question needs a better answer' : 'No confirmed answer was found'];
                }
            } elseif (in_array($decision, ['handoff_requested'], true)) {
                $counts['handoff']++;
            } else {
                $counts['answered']++;
            }
        }
        usort($questions, fn ($a, $b) => $b['count'] <=> $a['count']);

        return ['counts' => $counts, 'questions' => array_slice(array_values($questions), 0, 50), 'failures' => array_slice($failures, 0, 50), 'limited' => $limited, 'activatedAt' => $activated->toIso8601String(), 'from' => $from->toIso8601String()];
    }

    public function create(int $sourceId, User $actor): array
    {
        return DB::transaction(function () use ($sourceId, $actor) {
            $source = SupportMessage::whereKey($sourceId)->lockForUpdate()->firstOrFail();
            $c = $this->eligibleSource($source);
            $existing = SupportFaqDraft::where('source_message_id', $sourceId)->first();
            if ($existing) {
                return [$existing, false];
            }
            $nextCustomer = $c->messages()->where('sender_role', 'customer')->where('id', '>', $sourceId)->min('id');
            $staff = $c->messages()->where('sender_role', 'human_agent')->where('id', '>', $sourceId)->when($nextCustomer, fn ($q) => $q->where('id', '<', $nextCustomer))->latest('id')->first();
            $draft = SupportFaqDraft::create(['source_message_id' => $sourceId, 'conversation_id' => $c->id, 'staff_message_id' => $staff?->id, 'question' => mb_substr($this->sanitize($source->content, $c), 0, 1000), 'answer' => mb_substr($this->sanitize($staff?->content ?? '', $c), 0, 10000), 'channel' => $c->channel, 'created_by' => $actor->id]);

            return [$draft->refresh(), true];
        });
    }

    public function edit(SupportFaqDraft $draft, array $data): SupportFaqDraft
    {
        return DB::transaction(function () use ($draft, $data) {
            $d = SupportFaqDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($d, $data['version']);
            abort_unless($d->status === 'Needs review', 409, 'This suggestion has already been reviewed.');
            $c = SupportConversation::find($d->conversation_id);
            $d->update(['question' => $this->sanitize($data['question'], $c), 'answer' => $this->sanitize($data['answer'], $c), 'category' => $data['category'], 'version' => $d->version + 1]);

            return $d;
        });
    }

    private function checkVersion(SupportFaqDraft $draft, int $version): void
    {
        abort_unless($draft->version === $version, 409, 'This draft changed in another session. Reload before saving.');
    }

    public function review(SupportFaqDraft $draft, int $version, bool $publish, User $actor): SupportFaqDraft
    {
        return DB::transaction(function () use ($draft, $version, $publish, $actor) {
            $d = SupportFaqDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($publish && $d->status === 'Published') {
                return $d;
            }
            $this->checkVersion($d, $version);
            abort_unless($d->status === 'Needs review', 409, 'This suggestion has already been reviewed.');
            if ($publish) {
                abort_unless($d->source_message_id && SupportMessage::whereKey($d->source_message_id)->exists(), 422, 'The original conversation is no longer available.');
                validator($d->toArray(), ['question' => 'required|string|max:1000', 'answer' => 'required|string|max:10000'])->validate();
                $settingsRow = StoreSetting::where('key', 'support_faq')->lockForUpdate()->firstOrFail();
                $settings = $settingsRow->value;
                $faq = array_replace(['isEnabled' => false, 'retrievalDepth' => 'strict', 'categories' => [['id' => 'others', 'label' => 'Others']], 'items' => []], $settings['faq'] ?? []);
                abort_unless(in_array($d->category, array_column($faq['categories'], 'id'), true), 422, 'This category is no longer available. Choose another category.');
                foreach ($faq['items'] as $i) {
                    abort_if(mb_strtolower(trim($i['question'])) === mb_strtolower(trim($d->question)), 409, 'A FAQ already has this question. Edit that FAQ instead.');
                }
                $faqId = 'reviewed-'.$d->id;
                $faq['items'][] = ['id' => $faqId, 'question' => $d->question, 'answer' => $d->answer, 'category' => $d->category, 'keywords' => [], 'isActive' => true, 'lastUpdatedAt' => now()->toIso8601String()];
                app(SupportFaqService::class)->save(['version' => $settings['version'] ?? 0, 'faq' => $faq]);
                $d->published_faq_id = $faqId;
            }
            $d->fill(['status' => $publish ? 'Published' : 'Dismissed', 'version' => $d->version + 1, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();

            return $d;
        });
    }

    public function view(SupportFaqDraft $d): array
    {
        $d->loadMissing(['staffMessage', 'sourceMessage', 'conversation.customerUser', 'conversation.customer']);
        $staff = $d->staffMessage;
        $c = $d->conversation;
        $source = $d->sourceMessage;

        return ['id' => $d->id, 'question' => $d->question, 'originalQuestion' => $this->sanitize($source?->content ?? '', $c), 'answer' => $d->answer, 'category' => $d->category, 'channel' => $d->channel, 'status' => $d->status, 'version' => $d->version, 'conversationId' => $c?->id, 'sourceMessageId' => $d->source_message_id, 'staffAnswer' => $this->sanitize($staff?->content ?? '', $c), 'publishedFaqId' => $d->published_faq_id, 'reviewedAt' => $d->reviewed_at?->toIso8601String(), 'createdAt' => $d->created_at->toIso8601String()];
    }

    private function draftBlockReason(SupportMessage $source, ?SupportMessage $evidence, ?Carbon $activated=null): ?string
    {
        if($source->sender_role!=='customer'||$source->created_at->lt($activated??$this->activatedAt()))return 'Choose a customer question recorded after improvement tracking began.';
        if(mb_strlen(trim($source->content))<3)return 'Choose a meaningful customer text question.';
        if($source->relationLoaded('attachments')?$source->attachments->isNotEmpty():$source->attachments()->exists())return 'Attachment questions require a separate text question before FAQ review.';
        if(!$evidence)return 'No recorded AI reply is linked to this question.';
        if(in_array($evidence->delivery_status,['failed','unknown','pending'],true))return 'Check reply delivery before using this question for FAQ review.';
        if(!$this->missing($evidence)&&($evidence->ai_trace['decision']??'')!=='coverage_incomplete')return 'This is a service or delivery issue, not a confirmed knowledge gap. Check its settings first.';
        return null;
    }

    /** Recover only the proven historical zero-fact fallback; retain original trace for diagnosis. */
    private function legacyMissing(SupportMessage $m):bool
    {
        $t=$m->ai_trace??[];$tools=$t['tools']??[];
        return ($t['decision']??'')==='coverage_incomplete'&&array_key_exists('retrievedSourceIds',$t)&&$t['retrievedSourceIds']===[]&&empty($t['clarification'])&&count($tools)>0
            &&collect($tools)->every(fn($tool)=>($tool['status']??'')==='no_match'&&empty($tool['sourceIds']));
    }

    private function failed(SupportMessage $m): bool
    {
        if($this->legacyMissing($m))return false;
        return in_array($m->ai_trace['decision'] ?? '', array_merge(self::FAILURES,['rate_limited']), true)
            || collect($m->ai_trace['tools'] ?? [])->contains(fn ($t) => ($t['status'] ?? '') === 'unavailable');
    }

    private function missing(SupportMessage $m): bool
    {
        return $this->legacyMissing($m)||(! $this->failed($m) && in_array($m->ai_trace['decision'] ?? '',self::MISSING,true));
    }
}
