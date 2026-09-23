<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Welcome to Tekkonnectpro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @media (max-width:600px) {
            .container { width: 100% !important }
            .px { padding-left: 16px !important; padding-right: 16px !important }
            .py { padding-top: 16px !important; padding-bottom: 16px !important }
            .btn { display: block !important; width: 100% !important; text-align: center !important }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f4f5f7;">
@php
    $customerName = $customer_name ?? $name ?? 'Customer';
@endphp
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f4f5f7;">
        Welcome to Tekkonnectpro — Here’s how your onboarding will unfold.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f5f7;">
        <tr>
            <td align="center" style="padding:24px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                    class="container" style="width:100%; background:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#39ac31; color:#ffffff; padding:24px;">
                            <h1 style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700;">
                                Welcome to Tekkonnectpro</h1>
                            <p style="margin:8px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px; opacity:.9;">
                                Hi {{ $customerName }} — I’m Manoj Kumar, your account manager. I’ll guide you through every step of onboarding.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="px py" style="padding:28px 28px; font-family:Arial,Helvetica,sans-serif; color:#111827;">
                            @if (! empty($business_name) || ! empty($customer_email) || ! empty($email) || ! empty($doc_type) || ! empty($website))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;">
                                    @if (! empty($business_name))
                                    <tr>
                                        <td style="padding:10px 14px;font-size:13px;color:#64748b;">Business</td>
                                        <td align="right" style="padding:10px 14px;font-size:13px;font-weight:bold;color:#111827;">{{ $business_name }}</td>
                                    </tr>
                                    @endif
                                    @if (! empty($customer_email) || ! empty($email))
                                    <tr>
                                        <td style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;color:#64748b;">Email</td>
                                        <td align="right" style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;font-weight:bold;color:#111827;">{{ $customer_email ?? $email }}</td>
                                    </tr>
                                    @endif
                                    @if (! empty($doc_type))
                                    <tr>
                                        <td style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;color:#64748b;">Doc type</td>
                                        <td align="right" style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;font-weight:bold;color:#111827;">{{ $doc_type }}</td>
                                    </tr>
                                    @endif
                                    @if (! empty($website))
                                    <tr>
                                        <td style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;color:#64748b;">Website</td>
                                        <td align="right" style="padding:10px 14px;border-top:1px solid #e5e7eb;font-size:13px;font-weight:bold;color:#111827;">{{ $website }}</td>
                                    </tr>
                                    @endif
                                </table>
                            @endif

                            <p style="margin:0 0 12px; font-size:15px; line-height:1.6;">
                                We received your readiness submission. Below is an outline of the onboarding steps and what you can expect along the way.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0 24px;">
                                <tr>
                                    <td>
                                        <a href="https://calendly.com/tekpro-connect/25min" target="_blank" class="btn"
                                            style="background:#39ac31; color:#ffffff; text-decoration:none; padding:12px 18px; border-radius:8px; font-size:14px; display:inline-block;">
                                            Book Your Discovery Call
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin:0 0 8px; font-size:18px;">Onboarding Steps</h2>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="margin:8px 0 16px; border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <strong style="display:inline-block; background:#eef2ff; color:#1f2937; padding:4px 10px; border-radius:999px; font-size:12px; margin-bottom:8px;">Day 1</strong>
                                        <h3 style="margin:6px 0 8px; font-size:16px;">Intro Email &amp; Discovery Call</h3>
                                        <p style="margin:0 0 8px; font-size:14px; line-height:1.6;">
                                            We’ll begin with an introductory call to discuss your requirements, clarify plan details, and align on the setup process.
                                        </p>
                                        <p style="margin:0; font-size:14px;">
                                            Booking link: <a href="https://calendly.com/tekpro-connect/25min" target="_blank" style="color:#0ea5e9; text-decoration:underline;">https://calendly.com/tekpro-connect/25min</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="margin:0 0 16px; border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <strong style="display:inline-block; background:#ecfeff; color:#1f2937; padding:4px 10px; border-radius:999px; font-size:12px; margin-bottom:8px;">Day 2 (or based on your availability)</strong>
                                        <h3 style="margin:6px 0 8px; font-size:16px;">Meta Business Page Check (if applicable)</h3>
                                        <p style="margin:0; font-size:14px; line-height:1.6;">
                                            If relevant to your plan, we’ll schedule a call to verify your Meta business page setup and ensure everything is in order.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                                style="margin:0 0 8px; border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <strong style="display:inline-block; background:#f0fdf4; color:#1f2937; padding:4px 10px; border-radius:999px; font-size:12px; margin-bottom:8px;">Following Steps</strong>
                                        <p style="margin:8px 0 0; font-size:14px; line-height:1.6;">
                                            Data submission → Meta account setup &amp; verification (if applicable) → platform setup → system test drive → training &amp; Q&amp;A → employee training → go-live.
                                        </p>
                                        <p style="margin:10px 0 0; font-size:14px; line-height:1.6;">
                                            Onboarding generally takes <em>10–15 working days</em> after we receive all required data and approvals.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:16px 0 6px; font-size:15px; line-height:1.6;">
                                We’re thrilled to have you on board and look forward to a smooth, successful onboarding experience.
                            </p>
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.6;">
                                If you have any immediate questions, just reply to this email.
                            </p>

                            <p style="margin:0; font-size:14px;">
                                Best regards,<br><br>
                                <strong>Manoj Kumar</strong><br><br>
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
                <div style="height:24px; line-height:24px;">&nbsp;</div>
            </td>
        </tr>
    </table>
</body>
</html>
