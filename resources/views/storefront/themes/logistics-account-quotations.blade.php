<section x-show="activeTab==='quotations'" x-cloak data-customer-quotations><p class="lg-eyebrow">YOUR QUOTATIONS</p><h2>Quotations</h2><p>Review quotations sent in response to your enquiries.</p>
@forelse($accountPayload['quotations'] ?? [] as $quotation)
<article class="lg-quotation-card"><div><h3>{{ $quotation['number'] }}</h3><p>Version {{ $quotation['revision'] }} · Sent {{ \Carbon\Carbon::parse($quotation['sentAt'])->format('d M Y') }}</p><p>{{ $quotation['expired'] ? 'Validity ended' : 'Sent' }}@if($quotation['expiresAt']) · Valid until {{ \Carbon\Carbon::parse($quotation['expiresAt'])->format('d M Y') }}@endif</p></div><a class="lg-primary" href="{{ $quotation['url'] }}">View quotation →</a></article>
@empty<p class="lg-empty">No quotations have been sent to your account yet.</p>@endforelse
</section>
