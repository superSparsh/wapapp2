<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Readiness Status - Action Needed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background:#f4f5f7;">
@php
    $customerName = $customer_name ?? $name ?? 'Customer';
    $reasons = $reasons ?? data_get($meta ?? [], 'reasons', []);
@endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0"
                    style="width:100%; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#dc2626; color:#ffffff; padding:24px;">
                            <h1 style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700;">
                                Action Needed Before Onboarding
                            </h1>
                            <p style="margin:8px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; opacity:.9;">
                                Hi {{ $customerName }}, your readiness form shows that some items are still missing or invalid.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px; font-family:Arial,Helvetica,sans-serif; color:#111827;">
                            <p style="margin:0 0 12px; font-size:15px; line-height:1.6;">
                                Unfortunately, we cannot proceed until these issues are resolved:
                            </p>

                            @if (! empty($reasons) && is_array($reasons))
                                <ul style="margin:0 0 12px; padding-left:20px; font-size:14px; line-height:1.6;">
                                    @foreach ($reasons as $field)
                                        <li>{{ ucfirst(str_replace('_', ' ', (string) $field)) }} is missing or invalid.</li>
                                    @endforeach
                                </ul>
                            @else
                                <p style="font-size:14px;">One or more of your responses do not meet the onboarding requirements.</p>
                            @endif

                            <p style="margin:16px 0 12px; font-size:15px; line-height:1.6;">
                                Please review and update your readiness form at your earliest convenience.
                            </p>

                            <p style="margin:16px 0 6px; font-size:15px; line-height:1.6;">
                                If you have questions, reply to this email or book a support call:
                            </p>
                            <p>
                                <a href="https://calendly.com/tekpro-connect/25min" target="_blank"
                                    style="color:#0ea5e9; text-decoration:underline;">Book Support Call</a>
                            </p>

                            <p style="margin:0; font-size:14px;">
                                Best regards,<br><br>
                                <strong>Manoj Kumar</strong><br>
                                Account Manager, Tekkonnectpro
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; color:#475569; padding:18px 28px; font-family:Arial,Helvetica,sans-serif; font-size:12px; text-align:center;">
                            © {{ date('Y') }} Tekkonnectpro. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
