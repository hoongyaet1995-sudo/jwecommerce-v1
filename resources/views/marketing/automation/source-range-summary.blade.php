@php($rangeLabels=['products'=>'products','categories'=>'categories','collections'=>'collections','promotions'=>'valid promotions','pages'=>'published pages'])
@if(($sourceSettings['source_scope']??'selected')==='all')
All eligible resources
@else
@foreach(app(\App\Services\Marketing\MarketingAutomationSources::class)->modes($sourceSettings) as $kind=>$mode)
@if($mode!=='none')<div class="small">{{ $mode==='all'?'All':count($sourceSettings['sources'][$kind]).' selected' }} {{ $rangeLabels[$kind] }}</div>@endif
@endforeach
@endif
