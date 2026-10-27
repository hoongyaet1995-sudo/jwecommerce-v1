<?php

namespace App\Services\Marketing;

use App\Models\MarketingProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MarketingPlanningConfirmation
{
    public const RULES = ['offer' => 'required|string|max:800', 'goal' => 'required|in:awareness,introduction,promotion,enquiry,appointment,purchase', 'audience' => 'required|string|max:500', 'market' => 'required|string|max:100', 'language' => 'required|in:English,zh-MY,ms-MY', 'platforms' => 'required|array|list|min:1|max:4', 'duration_seconds' => 'required|integer|min:5|max:60', 'pain_point' => 'required|string|max:800', 'differentiator' => 'required|string|max:800', 'tone' => 'required|string|max:300', 'cta' => 'required|string|max:150', 'destination' => 'nullable|url:http,https|max:500'];

    public function seed(MarketingProject $p, array $prefs): array
    {
        $b = $p->brief;
        $prior = $p->planning_state['fields'] ?? [];
        $values = ['offer' => $p->snapshot['title'] ?? '', 'goal' => $b['goal'] ?? 'introduction', 'audience' => $b['audience'] ?? '', 'market' => $b['market'] ?? '', 'language' => $prefs['language'] ?? '', 'platforms' => $prefs['platforms'] ?? [], 'duration_seconds' => ($prefs['duration'] ?? 'auto') === 'auto' ? null : (int) $prefs['duration'], 'pain_point' => $b['pain_point'] ?? '', 'differentiator' => $b['differentiator'] ?? '', 'tone' => $b['tone'] ?? '亲切专业', 'cta' => $b['cta'] ?? '了解更多', 'destination' => $b['destination'] ?? ''];
        $fields = [];
        foreach ($values as $k => $value) {
            $fields[$k] = $prior[$k] ?? ['value' => $value, 'status' => 'suggested', 'origin' => $k === 'offer' ? 'source_snapshot' : 'saved_brief'];
        }
        foreach (['language', 'platforms', 'duration_seconds'] as $k) {
            $pref = $k === 'duration_seconds' ? 'duration' : $k;
            if (($p->preferences[$pref] ?? null) !== ($prefs[$pref] ?? null) || ! isset($prior[$k])) {
                $fields[$k] = ['value' => $values[$k], 'status' => $values[$k] === null ? 'missing' : 'confirmed', 'origin' => 'merchant_preference', 'confirmed_by' => $p->owner_id, 'confirmed_at' => $values[$k] === null ? null : now()->toIso8601String()];
            }
        }

        return ['status' => 'draft', 'fields' => $fields, 'confirmed_digest' => null];
    }

    public function values(MarketingProject $p): array
    {
        return array_map(fn ($f) => $f['value'] ?? null, $p->planning_state['fields'] ?? []);
    }

    public function missing(MarketingProject $p): array
    {
        $v = $this->values($p);
        $rules = self::RULES;
        unset($rules['destination']);
        $rules['platforms.*'] = 'required|distinct|in:Facebook,Instagram,TikTok,YouTube';

        return array_keys(Validator::make($v, $rules)->errors()->toArray());
    }

    public function question(MarketingProject $p): ?string
    {
        return app(MarketingPlanningGuide::class)->next($p);
    }

    /** Called under the project lock after a valid guided reply. Suggestions are drafts, never production consent. */
    public function syncConversation(MarketingProject $p,int $baseVersion,array $changes):bool
    {
        if($p->version!==$baseVersion||!($p->preferences['complete']??false)||!$p->planning_state)return false;
        $state=$p->planning_state;$changed=false;
        foreach($changes as $change){
            $field=$change['field'];$value=$change['value'];
            if(($state['fields'][$field]['value']??null)===$value)continue;
            $state['fields'][$field]=['value'=>$value,'status'=>'suggested','origin'=>'ai_conversation'];
            unset($state['proposed_changes'][$field]);$changed=true;
        }
        if(!$changed)return true;
        app(MarketingConceptHistory::class)->capture($p,'before_chat_summary');
        app(MarketingWorkspacePreparation::class)->invalidate($p);
        $state['status']='draft';$state['confirmed_digest']=null;$p->planning_state=$state;$p->version++;$p->save();
        return true;
    }

    public function answer(string $id, int $actor, int $version, string $request, string $field, mixed $value): MarketingProject
    {
        abort_unless(array_key_exists($field, self::RULES), 422);
        $rules = ['value' => self::RULES[$field]];
        if ($field === 'platforms') {
            $rules['value.*'] = 'required|distinct|in:Facebook,Instagram,TikTok,YouTube';
        }
        $v = Validator::make(['value' => $value], $rules)->validate()['value'] ?? '';
        if ($field === 'duration_seconds') {
            $v = (int) $v;
        }
        $data = ['field' => $field, 'value' => $v];
        $prep = app(MarketingWorkspacePreparation::class);

        return DB::transaction(function () use ($id, $actor, $version, $request, $field, $v, $data, $prep) {
            if ($old = $prep->replay($actor, $request, 'field_confirmed', $data, $id)) {
                return $old;
            }
            $p = app(MarketingWorkflow::class)->locked($id, $actor, $version);
            abort_unless($p->preferences['complete'] ?? false, 422, 'Save production preferences first.');
            $state = $p->planning_state ?? $this->seed($p, $p->preferences);
            $state['fields'][$field] = ['value' => $v, 'status' => 'confirmed', 'origin' => 'merchant_answer', 'confirmed_by' => $actor, 'confirmed_at' => now()->toIso8601String()];
            $state['status'] = 'draft';
            $state['confirmed_digest'] = null;
            unset($state['proposed_changes'][$field]);
            if ($field === 'goal') {
                $cta = ['awareness' => '了解更多', 'introduction' => '了解产品', 'promotion' => '查看优惠详情', 'enquiry' => '联系我们咨询报价', 'appointment' => '预约咨询', 'purchase' => '了解购买方式'][$v];
                $state['fields']['cta'] = ['value' => $cta, 'status' => 'suggested', 'origin' => 'goal_rule'];
            }
            $prep->invalidate($p);
            if (in_array($field, ['language', 'platforms'], true)) {
                $p->preferences = array_replace($p->preferences, [$field => $v]);
            }$p->planning_state = $state;
            $p->version++;
            $p->save();
            $prep->record($p, $actor, $request, 'field_confirmed', $data);

            return $p->fresh();
        });
    }

    public function suggest(string $id, int $actor, int $version, array $values, string $origin, ?string $run = null, ?string $request = null): MarketingProject
    {
        return DB::transaction(function () use ($id, $actor, $version, $values, $origin, $run, $request) {
            $p = app(MarketingWorkflow::class)->locked($id, $actor, $version, (bool) $run);
            if ($run) {
                abort_unless($p->busy_run_id === $run, 409);
            }
            $state = $p->planning_state ?? $this->seed($p, $p->preferences ?? []);
            foreach ($values as $field => $value) {
                if (! array_key_exists($field, self::RULES)) {
                    continue;
                }
                $r = ['value' => self::RULES[$field]];
                if ($field === 'platforms') {
                    $r['value.*'] = 'required|distinct|in:Facebook,Instagram,TikTok,YouTube';
                }$v = Validator::make(['value' => $value], $r)->validate()['value'] ?? '';
                if (($state['fields'][$field]['status'] ?? null) === 'confirmed') {
                    if ($v !== ($state['fields'][$field]['value'] ?? null)) {
                        $state['proposed_changes'][$field] = ['value' => $v, 'origin' => $origin, 'run_id' => $run];
                    }

continue;
                }$state['fields'][$field] = ['value' => $v, 'status' => 'suggested', 'origin' => $origin, 'run_id' => $run];
            }
            if (isset($values['reason'])) {
                $state['reason'] = $values['reason'];
            }if (isset($values['questions'])) {
                $state['questions'] = $values['questions'];
            }
            $p->planning_state = $state;
            $p->version++;
            $p->save();
            if ($request) {
                app(MarketingWorkspacePreparation::class)->record($p, $actor, $request, 'ai_suggested', ['values' => $values, 'origin' => $origin]);
            }

return $p->fresh();
        });
    }

    public function confirm(string $id, int $actor, int $version, string $request): MarketingProject
    {
        $prep = app(MarketingWorkspacePreparation::class);

        return DB::transaction(function () use ($id, $actor, $version, $request, $prep) {
            $data = ['intent' => 'confirm_summary'];
            if ($old = $prep->replay($actor, $request, 'summary_confirmed', $data, $id)) {
                return $old;
            }
            $p = app(MarketingWorkflow::class)->locked($id, $actor, $version);
            abort_unless($p->preferences['complete'] ?? false, 422);
            if ($this->missing($p) || ! empty($p->planning_state['proposed_changes'])) {
                throw ValidationException::withMessages(['planning' => '请先补齐并检查摘要中的必要资料。']);
            }
            $v = $this->values($p);
            $state = $p->planning_state;
            foreach ($state['fields'] as &$f) {
                $f['status'] = 'confirmed';
                $f['confirmed_by'] = $actor;
                $f['confirmed_at'] = now()->toIso8601String();
            }unset($f);
            $state['status'] = 'confirmed';
            $state['confirmed_digest'] = hash('sha256', json_encode([$v, $p->snapshot, $p->preferences], JSON_THROW_ON_ERROR));
            $b = $v;
            unset($b['offer']);
            $b['source_summary'] = $v['offer'];
            $b['source_confirmed'] = true;
            $b['facts_confirmed'] = true;
            $b['goal_confirmed'] = true;
            $b['audience_confirmed'] = true;
            app(MarketingWorkflow::class)->saveBrief($id,$actor,$p->version,$b);
            $p->refresh();
            $p->planning_state = $state;
            $p->save();
            $prep->record($p,$actor,$request,'summary_confirmed',$data);

            return $p->fresh();
        });
    }
}
