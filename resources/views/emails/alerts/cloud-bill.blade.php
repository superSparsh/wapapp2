<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud bill notification</title>
</head>
<body style="margin:0;padding:0;background:#f2f6fb;font-family:Arial,Helvetica,sans-serif;color:#13334c;">
@php
    $amountValue = is_numeric($amount ?? null) ? (float) $amount : null;
    $currencyLabel = $currency ?? '';
    $amountDisplay = $amountValue !== null
        ? trim(($currencyLabel === 'INR' ? '₹ ' : ($currencyLabel ? $currencyLabel.' ' : '')).number_format($amountValue, 2))
        : (string) ($amount ?? '—');
@endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f6fb;padding:24px 12px;border-collapse:collapse;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(4,56,94,0.08);border-collapse:separate;">
                    <tr>
                        <td align="center" style="padding:22px 26px;background:linear-gradient(90deg,#1f4f9f 0%,#0f8f8f 55%,#17a84e 100%);text-align:center;">
                            <img src="{{ asset('images/tittu-logo.jpeg') }}" alt="WAPAPP" width="96" height="96" style="display:block;border:0;border-radius:8px;margin:0 auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px 16px;">
                            <h2 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#123a60;">Cloud bill uploaded</h2>
                            <p style="margin:0;font-size:15px;line-height:1.7;color:#3b556d;">
                                A new cloud / CAMS bill file was uploaded in admin and shared for review.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 26px 14px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dce8f3;border-radius:12px;background:#f8fcff;">
                                <tr>
                                    <td style="padding:12px 16px;font-size:14px;color:#38546c;">File</td>
                                    <td align="right" style="padding:12px 16px;font-size:13px;font-weight:bold;color:#123a60;word-break:break-all;">{{ $filename ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Period</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;font-weight:bold;color:#123a60;">{{ $period ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Amount</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:15px;font-weight:bold;color:#0f8f8f;">{{ $amountDisplay }}</td>
                                </tr>
                                @if (! empty($currencyLabel))
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Currency</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;font-weight:bold;color:#123a60;">{{ $currencyLabel }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 26px;background:#f7fbff;border-top:1px solid #e6eef6;font-size:12px;color:#6a8196;">
                            Recipients are taken from app admin notification config (same as plan expiry / wallet alerts).
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
