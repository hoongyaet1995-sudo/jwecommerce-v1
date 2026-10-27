@php($guide=$guide??app(\App\Services\Marketing\MarketingPlanningGuide::class)->data($project))
<div data-planning-guide data-guide-version="{{ $project->version }}" class="mt-3">
<article class="ws-conversation-message"><small>AI策划助手</small><p>{{ $guide['question'] }}</p>
@if($guide['options'])<div class="ws-suggestions my-3">@foreach($guide['options'] as $option)<button type="button" data-planning-choice data-field="{{ $guide['field'] }}" data-value="{{ $option['value'] }}" data-version="{{ $project->version }}">{{ $option['label'] }}</button>@endforeach</div>@endif
</article>
</div>
