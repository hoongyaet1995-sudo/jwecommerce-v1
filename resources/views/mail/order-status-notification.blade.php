<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $dispatch->rendered_subject }}</title>
</head>
<body style="margin:0;background:#f5f5f4;color:#1c1917;font-family:Arial,Helvetica,sans-serif;">
    @php
        $ownedTrackingUrl = url('/order-tracking');
        $frozenTrackingUrl = data_get($dispatch->variable_snapshot, 'tracking_url');
        $trackingUrl = is_string($frozenTrackingUrl) && hash_equals($ownedTrackingUrl, $frozenTrackingUrl)
            ? $frozenTrackingUrl
            : $ownedTrackingUrl;
    @endphp
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $dispatch->rendered_preheader }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f4;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e7e5e4;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px;background:#111827;color:#ffffff;font-size:20px;font-weight:700;">
                            {{ data_get($dispatch->variable_snapshot, 'shop_name', '') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px;">
                            <h1 style="margin:0 0 20px;font-size:28px;line-height:1.25;color:#111827;">
                                {{ $dispatch->rendered_heading }}
                            </h1>
                            <div style="font-size:16px;line-height:1.7;color:#44403c;">
                                {!! nl2br(e($dispatch->rendered_body)) !!}
                            </div>
                            @if ($dispatch->rendered_cta_text !== '' && $dispatch->rendered_cta_url !== '')
                                <p style="margin:28px 0 0;">
                                    <a href="{{ $dispatch->rendered_cta_url }}" style="display:inline-block;padding:13px 22px;border-radius:10px;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;">
                                        {{ $dispatch->rendered_cta_text }}
                                    </a>
                                </p>
                            @endif
                            <p style="margin:28px 0 0;font-size:14px;line-height:1.6;color:#57534e;">
                                View your order status securely:<br>
                                <a href="{{ $trackingUrl }}" style="color:#1d4ed8;text-decoration:underline;">
                                    {{ $trackingUrl }}
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
