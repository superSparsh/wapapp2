<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>New WAPAPP Wallet Credits Invoice</title>
</head>
<body style="margin:0;padding:0;background:#f2f6fb;font-family:Arial,Helvetica,sans-serif;color:#13334c;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f6fb;padding:24px 12px;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(4,56,94,0.08);border-collapse:separate;">
                    <tr>
                        <td align="center" bgcolor="#1f4f9f" style="padding:22px 26px;background-color:#1f4f9f;background-image:linear-gradient(90deg,#1f4f9f 0%,#0f8f8f 55%,#17a84e 100%);text-align:center;">
                            <img src="{{ asset('images/tittu-logo.jpeg') }}" alt="WAPAPP" width="96" height="96" style="display:block;border:0;border-radius:8px;margin:0 auto;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 26px 16px;">
                            <h2 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#123a60;">New WAPAPP Wallet Credits request</h2>
                            <p style="margin:0;font-size:15px;line-height:1.7;color:#3b556d;">
                                An invoice has been generated for a customer WAPAPP Wallet Credits top-up.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 26px 14px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dce8f3;border-radius:12px;background:#f8fcff;">
                                <tr>
                                    <td style="padding:12px 16px;font-size:14px;color:#38546c;">Customer ID</td>
                                    <td align="right" style="padding:12px 16px;font-size:14px;font-weight:bold;color:#123a60;">{{ $customer_id ?? $tenant_id ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Customer Name</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;font-weight:bold;color:#123a60;">{{ $customer_name }}</td>
                                </tr>
                                @if (!empty($customer_email))
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Customer Email</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;font-weight:bold;color:#123a60;">{{ $customer_email }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Amount</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:16px;font-weight:bold;color:#0f8f8f;">₹{{ number_format((float) ($amount ?? 0), 2) }}</td>
                                </tr>
                                @if (!empty($invoice_number))
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Invoice Number</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;font-weight:bold;color:#123a60;">{{ $invoice_number }}</td>
                                </tr>
                                @endif
                                @if (!empty($zoho_invoice_id))
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:14px;color:#38546c;">Zoho Invoice ID</td>
                                    <td align="right" style="padding:12px 16px;border-top:1px solid #e4edf5;font-size:13px;font-weight:bold;color:#123a60;">{{ $zoho_invoice_id }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                    @if (!empty($invoice_url))
                    <tr>
                        <td style="padding:6px 26px 20px;">
                            <a href="{{ $invoice_url }}" style="display:inline-block;background:#1f4f9f;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:12px 18px;border-radius:8px;">
                                Open Invoice
                            </a>
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:16px 26px;background:#f7fbff;border-top:1px solid #e6eef6;font-size:12px;color:#6a8196;">
                            Automated notification from WAPAPP Wallet Credits billing.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
