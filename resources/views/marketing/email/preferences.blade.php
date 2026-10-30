{{-- Source: supplied CRM campaign_unsubscribe.php; GET confirmation added, sender branding is JW shop. --}}
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Email Preferences | {{ app(\App\Support\ShopBrand::class)->name() }}</title></head>
<body style="margin:0;background:#f4f7f5;color:#17221c;font-family:Arial,sans-serif;">
<main style="box-sizing:border-box;max-width:560px;margin:10vh auto;padding:40px 24px;text-align:center;">
<div style="margin-bottom:18px;color:#315f4b;font-size:42px;">{{ $success?'✓':'✉' }}</div>
<h1 style="margin:0 0 12px;font-size:24px;">{{ $success?'Email preferences updated':'Unsubscribe from marketing emails?' }}</h1>
<p style="margin:0;color:#66756e;font-size:15px;line-height:1.7;">{{ $success?'You have been unsubscribed from future marketing emails.':'You can stop marketing emails from '.app(\App\Support\ShopBrand::class)->name().'. Your account and order messages are unaffected.' }}</p>
@if(!$success)<form method="post" action="{{ request()->fullUrl() }}" style="margin-top:24px;">@csrf<button type="submit" style="border:0;border-radius:8px;background:#082b1e;color:white;padding:12px 20px;font:600 15px Arial;cursor:pointer;">Unsubscribe</button></form>@endif
</main></body></html>
