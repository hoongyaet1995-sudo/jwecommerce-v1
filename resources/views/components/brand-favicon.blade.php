@php($favicon = data_get(\App\Models\StoreSetting::where('key', 'shop_profile')->value('value'), 'data.faviconUrl'))
@if($favicon)
    <link id="shop-favicon" rel="icon" type="image/x-icon" href="{{ $favicon }}">
@endif
