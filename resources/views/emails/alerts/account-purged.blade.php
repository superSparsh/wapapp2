<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f4f6f8; }
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; max-width: 100% !important; }
            .content-pad { padding: 20px 20px !important; }
            .header-pad { padding: 20px 15px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8;" bgcolor="#f4f6f8">
@php
    $siteName = config('app.name', 'WAPAPP');
    $logoUrl = asset('images/tittu-logo.jpeg');
    $supportEmail = config('operational-alerts.support.email', 'support@wapapp.in');
@endphp

    <div style="display: none; font-size: 1px; color: #f4f6f8; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
        Your account data deletion has completed.
    </div>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6f8;">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="container" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <tr>
                        <td align="center" class="header-pad" style="padding: 28px 30px; background-color: #044767;">
                            <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height: 42px; width: auto;" />
                        </td>
                    </tr>

                    <tr>
                        <td class="content-pad" style="padding: 36px 36px 28px 36px; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #333333; line-height: 1.65;">
                            <h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 600; color: #1a1a1a;">
                                Account Data Deletion Completed
                            </h2>

                            <p style="margin: 0 0 16px 0; font-size: 15px; color: #444444;">
                                Dear Customer,
                            </p>

                            <p style="margin: 0 0 16px 0; font-size: 15px; color: #444444;">
                                We are writing to let you know that a scheduled account data deletion has completed on {{ $siteName }}.
                            </p>

                            @if (! empty($summary))
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0;">
                                    <tr>
                                        <td style="padding: 16px 20px; background-color: #f0f5f8; border-left: 4px solid #044767; border-radius: 4px; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #555555; line-height: 1.6;">
                                            <strong style="color: #044767;">Summary:</strong><br>
                                            {{ $summary }}
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if (! empty($completed_at))
                                <p style="margin: 0 0 16px 0; font-size: 15px; color: #444444;">
                                    <strong>Completed at:</strong> {{ $completed_at }}
                                </p>
                            @endif

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 20px 0;">
                                <tr>
                                    <td style="padding: 16px 20px; background-color: #f0f5f8; border-left: 4px solid #044767; border-radius: 4px; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #555555; line-height: 1.6;">
                                        <strong style="color: #044767;">Data Retention Notice:</strong> Certain information may have been retained where required by applicable law, regulatory obligations, or for legitimate business and legal purposes, in accordance with our Privacy Policy.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 16px 0; font-size: 15px; color: #444444;">
                                If you have any questions or need further clarification, please reach out to our support team at <a href="mailto:{{ $supportEmail }}" style="color: #044767; text-decoration: underline;">{{ $supportEmail }}</a>.
                            </p>

                            <p style="margin: 0 0 0 0; font-size: 15px; color: #444444;">
                                Thank you for having been a part of {{ $siteName }}. We wish you all the best.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 36px;">
                            <hr style="border: none; border-top: 1px solid #e8e8e8; margin: 0;" />
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 20px 36px 28px 36px; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
                            <p style="margin: 0 0 6px 0; font-size: 13px; color: #999999; line-height: 1.5;">
                                Warm regards,<br />
                                <strong style="color: #666666;">The {{ $siteName }} Team</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="container" style="max-width: 600px; margin-top: 12px;">
                    <tr>
                        <td align="center" style="padding: 12px 20px; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 12px; color: #aaaaaa; line-height: 1.5;">
                            This is an automated notification regarding your account status. Please do not reply directly to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
