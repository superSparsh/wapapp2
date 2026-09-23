<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Readiness Status - Eligible</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background:#f4f5f7;">
@php
    $customerName = $customer_name ?? $name ?? 'Customer';
@endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px;">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#16a34a; color:#ffffff; padding:24px;">
                            <h1 style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700;">
                                You’re Eligible for Onboarding
                            </h1>
                            <p style="margin:8px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; opacity:.9;">
                                Hi {{ $customerName }}, your readiness form is complete and you are eligible to proceed with WhatsApp Business Platform onboarding.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px; font-family:Arial,Helvetica,sans-serif; color:#111827;">
                            @if (! empty($business_name))
                                <p style="margin:0 0 12px; font-size:14px; color:#475569;">
                                    <strong>Business:</strong> {{ $business_name }}
                                </p>
                            @endif

                            <p style="margin:0 0 12px; font-size:15px;">
                                If you haven’t already, please schedule your discovery call:
                            </p>
                            <p>
                                <a href="https://calendly.com/tekpro-connect/25min" target="_blank"
                                   style="background:#16a34a; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:8px; font-size:14px; display:inline-block;">
                                    Book Discovery Call
                                </a>
                            </p>

                            <p style="margin:16px 0 6px; font-size:15px; line-height:1.6;">
                                We’ll also share a WhatsApp support group and onboarding document soon.
                            </p>

                            <p style="margin:0; font-size:14px;">
                                Best regards,<br><br>
                                <strong>Manoj Kumar</strong><br>
                                Account Manager, Tekkonnectpro IT Services
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; color:#475569; padding:18px 28px; font-family:Arial,Helvetica,sans-serif; font-size:12px; text-align:center;">
                            © {{ date('Y') }} Tekkonnectpro IT Services. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
