@php($intelligence=$record->snapshot['automation']['intelligence']??null)
@if($intelligence)
<details class="source-disclosure"><summary><i class="bi bi-chevron-down" aria-hidden="true"></i>Why this post · Generation basis</summary><div class="source-content">
<p><strong>Context captured:</strong> {{ $intelligence['as_of'] }} · {{ $intelligence['market'] }} · {{ $intelligence['timezone'] }}. This is the saved generation snapshot, not a live calendar check.</p>
<p><strong>Suggested marketing angle:</strong> {{ $intelligence['angle'] }}. Your explicit direction and Post Type take precedence.</p>
<p><strong>Actual creative angle:</strong> {{ $record->creative['angle']??'Writing not complete' }}</p>
<p><strong>AI source-selection explanation:</strong> {{ $record->snapshot['automation']['selection_reason']??'Not available' }} (Model explanation, not independent proof.)</p>
<p><strong>Selected resources:</strong> @foreach(['products'=>'title','pages'=>'title','promotions'=>'name'] as $kind=>$label)@foreach($record->snapshot['automation']['sources'][$kind]??[] as $source){{ $source[$label]??$source['code']??'Source' }} (#{{ $source['id'] }}){{ $loop->last?'':', ' }} @endforeach @endforeach</p>
<p><strong>Shared memory:</strong> {{ $intelligence['memory']['total'] }} generated concepts in the last {{ $intelligence['memory']['days'] }} days across this company. Platform variants count as one concept. The latest {{ count($intelligence['memory']['recent']) }} hooks and visual directions were supplied; repetition is reduced, not guaranteed absent.</p>
<p>Writing context retained {{ count($record->snapshot['facts']['creative_intelligence']['memory']['recent']??[]) }} recent hooks within the model brief budget; full selection history remains in this audit snapshot.</p>
@if(!empty($intelligence['memory']['recent']))<ul>@foreach($intelligence['memory']['recent'] as $old)<li>{{ $old['headline'] }} · {{ implode(', ',$old['state']) }}</li>@endforeach</ul>@endif
<p><strong>Selected occasions:</strong> @forelse($intelligence['occasions'] as $occasion){{ $occasion['name'] }} · {{ $occasion['date'] }} <a href="{{ $occasion['source'] }}" target="_blank" rel="noopener noreferrer">Calendar source</a> (verified {{ $occasion['verified_at'] }}). @empty None selected; evergreen content remains available. @endforelse</p>
<p>{{ $intelligence['calendar_coverage'] }}</p>
<p><strong>Trend retrieval:</strong> {{ $intelligence['trends']['status'] }} @if(isset($intelligence['trends']['checked_at'])) · checked {{ $intelligence['trends']['checked_at'] }} @endif</p>
<p><strong>Selected trend signals:</strong> @forelse($intelligence['trends']['items']??[] as $trend){{ $trend['title'] }} · {{ $trend['published_at'] }} <a href="{{ $trend['source'] }}" target="_blank" rel="noopener noreferrer">Data source: Google Trends</a>. Search interest only; not verified news. @empty None. No trend-based factual claims are authorized. @endforelse</p>
<p>Confirmed product facts and promotion terms remain authoritative. No store prices, discounts or publishing settings were changed.</p>
</div></details>
@endif
