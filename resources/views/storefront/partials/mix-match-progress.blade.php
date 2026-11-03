@foreach(app(\App\Services\Promotions\StorefrontOfferService::class)->forItems($items) as $mixProgress)
@php
 $mixOffer=$mixProgress['offer'];
 $mixApplied=($summary['promotion_snapshot']['type']??null)==='mix_match' && ($summary['promotion_snapshot']['offer_id']??null)===$mixOffer->id;
 $mixRemainder=$mixProgress['selected'] % $mixOffer->quantity;
 $mixPercent=(!$mixProgress['remaining']) ? 100 : (int)round($mixRemainder/$mixOffer->quantity*100);
@endphp
@if($mixCompact ?? false)
<section data-mix-match-progress data-mix-match-compact style="margin:0 0 .75rem;padding:.75rem 1rem;background:var(--mix-match-surface,#eef3f6);color:var(--mix-match-ink,#152538);border-left:2px solid var(--mix-match-accent,#254d68);font:inherit">
<div style="display:flex;align-items:baseline;justify-content:space-between;gap:.75rem;font-size:.8125rem;line-height:1.4">
<strong style="display:flex;align-items:center;gap:.4rem">@include('storefront.partials.promotion-icon', ['promotionIcon' => 'mix']) @if($mixApplied)You save @storefrontMoney($summary['discount'])@elseif($mixProgress['remaining']){{ $mixProgress['remaining'] }} more to unlock this offer @else Mix &amp; Match complete @endif</strong>
<a aria-label="View {{ $mixOffer->name }} offer" href="{{ route('storefront.offers.mix-match',$mixOffer->id) }}" style="color:inherit;text-decoration:none;white-space:nowrap">View offer →</a>
</div>
<div role="progressbar" aria-label="Mix and Match combination progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $mixPercent }}" style="height:3px;background:var(--mix-match-track,#dbe3e9);margin-top:.6rem"><span style="display:block;height:100%;width:{{ $mixPercent }}%;background:var(--mix-match-accent,#254d68)"></span></div>
</section>
@else
<section data-mix-match-progress style="margin:1.5rem 0 0;padding:1.5rem;background:var(--mix-match-surface,#eef3f6);color:var(--mix-match-ink,#152538);border:0;border-left:3px solid var(--mix-match-accent,#254d68);border-radius:0;font:inherit">
<p style="margin:0 0 .65rem;font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase">Mix &amp; Match · {{ $mixOffer->name }}</p>
<strong style="display:block;font-size:1rem;line-height:1.5">@if($mixApplied)You save @storefrontMoney($summary['discount'])@elseif($mixProgress['remaining'])Choose {{ $mixProgress['remaining'] }} more {{ $mixProgress['remaining']===1?'item':'items' }}@else Your combination is complete @endif</strong>
<p style="margin:.75rem 0;color:var(--mix-match-muted,#687582);font-size:.875rem;line-height:1.6">{{ $mixProgress['selected'] }} selected · Pick any {{ $mixOffer->quantity }} participating items for @storefrontMoney($mixOffer->price).</p>
<div role="progressbar" aria-label="Mix and Match combination progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $mixPercent }}" style="height:5px;background:var(--mix-match-track,#dbe3e9);margin:1rem 0"><span style="display:block;height:100%;width:{{ $mixPercent }}%;background:var(--mix-match-accent,#254d68)"></span></div>
<a style="color:inherit;text-decoration:underline;text-underline-offset:3px" href="{{ route('storefront.offers.mix-match',$mixOffer->id) }}">Choose more items →</a>
</section>@endif
@endforeach
