<svg class="pdp-promotion-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@if($promotionIcon === 'coins')
<circle cx="9" cy="9" r="6"/><path d="M8 7h2M9 6v6m6-5a7 7 0 1 1-8 12"/>
@elseif($promotionIcon === 'mix')
<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>
@elseif($promotionIcon === 'tiered')
<path d="M4 20h3v-6H4zm7 0h3V9h-3zm7 0h3V4h-3z"/>
@elseif($promotionIcon === 'gift')
<path d="M3 8h18v4H3zM5 12v9h14v-9M12 8v13"/><path d="M12 8H7a3 3 0 1 1 3-3l2 3Zm0 0h5a3 3 0 1 0-3-3l-2 3Z"/>
@else
<path d="m12 3 8 4.5v7M4 7.5 12 12l8-4.5M12 12v9M4 7.5v9l8 4.5 3-1.7M4 7.5 12 3l8 4.5M8 5.3l8 4.5M19 16v6M16 19h6"/>
@endif
</svg>
