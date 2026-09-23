@php
    // Prefer nested legacy $summary; fall back when missing or when flat digest string is passed.
    $s = (isset($summary) && is_array($summary)) ? $summary : [];
    if ($s === []) {
        $s = [
            'overview' => [
                'lines' => [
                    'total' => $lines_checked ?? 0,
                    'connected' => $lines_checked ?? 0,
                    'green' => $quality_green ?? 0,
                    'yellow' => $quality_yellow ?? 0,
                    'red' => $quality_red ?? 0,
                ],
                'templates' => [
                    'total' => $templates_total ?? 0,
                    'pending' => $templates_pending ?? 0,
                    'rejected' => $templates_rejected ?? 0,
                ],
                'alerts' => ['critical_7d' => $critical_alerts_7d ?? 0],
            ],
            'unread' => $unread_alerts ?? 0,
            'messaging' => ['overall' => $messaging_overall ?? []],
            'customerActivity' => $customerActivity ?? $customer_activity ?? [],
            'templateErrors' => $templateErrors ?? $template_errors ?? [],
            'operationalMeta' => $operationalMeta ?? $operational_meta ?? [],
            'perfFilters' => $perfFilters ?? $perf_filters ?? [],
            'needsAttention' => (bool) ($needs_attention ?? false),
            'leastFiveToday' => $leastFiveToday ?? $least_five_today ?? [],
            'mostFiveToday' => $mostFiveToday ?? $most_five_today ?? [],
            'generatedAt' => $generated_at ?? now(),
        ];
    }
    $summary = $s;
    $o = $summary['overview'] ?? [];
    $lines = $o['lines'] ?? [];
    $tpl = $o['templates'] ?? [];
    $alerts = $o['alerts'] ?? [];
    $mro = ($summary['messaging']['overall'] ?? []);
    $perf = $summary['perfFilters'] ?? [];
    $meta = $summary['operationalMeta'] ?? [];
    $leastFive = $summary['leastFiveToday'] ?? [];
    $mostFive = $summary['mostFiveToday'] ?? [];
    $adminUser = is_object($admin ?? null) ? ($admin->user ?? $admin) : null;
    $recipientName = '';
    if (is_object($adminUser)) {
        $recipientName = trim(($adminUser->first_name ?? '').' '.($adminUser->last_name ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($adminUser->name ?? ''));
        }
    }
    $logoUrl = asset('images/tittu-logo.jpeg');
    $healthLink = $healthUrl ?? $health_url ?? url('/admin/whatsapp-health');
    $performanceLink = $messagePerformanceUrl ?? $message_performance_url ?? $healthLink;
    $templatesLink = $healthLink.(str_contains((string) $healthLink, '?') ? '&' : '?').'tab=templates&template_status=REJECTED';
    $generatedAt = $summary['generatedAt'] ?? now();
    if ($generatedAt instanceof \DateTimeInterface) {
        $dateShort = $generatedAt->format('M j, Y');
        $dateLong = $generatedAt->format('l, F j, Y');
        $dateStamp = $generatedAt->format('Y-m-d H:i T');
    } else {
        $dateShort = (string) $generatedAt;
        $dateLong = (string) $generatedAt;
        $dateStamp = (string) $generatedAt;
    }
    $font = 'Arial, Helvetica, sans-serif';
@endphp
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ __('wa_health.digest_subject', ['date' => $dateShort]) }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        @media only screen and (max-width: 620px) {
            .wa-email-container { width: 100% !important; }
            .wa-email-stack { display: block !important; width: 100% !important; max-width: 100% !important; }
            .wa-email-pad { padding-left: 16px !important; padding-right: 16px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eef2f6;font-family:{{ $font }};color:#1f2937;">
    <div style="display:none;font-size:1px;color:#eef2f6;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        {{ __('wa_health.digest_preheader', [
            'unread' => number_format($summary['unread'] ?? 0),
            'lines' => number_format($lines['total'] ?? 0),
            'red' => number_format($lines['red'] ?? 0),
        ]) }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef2f6" style="background-color:#eef2f6;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <!--[if mso]>
                <table role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" width="600">
                <tr>
                <td>
                <![endif]-->
                <table role="presentation" class="wa-email-container" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:600px;max-width:600px;background-color:#ffffff;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;border:1px solid #d1d5db;">

                    {{-- Header --}}
                    <tr>
                        <td align="center" bgcolor="#075E54" style="padding:24px 28px;background-color:#075E54;text-align:center;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" style="padding-bottom:14px;">
                                        <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" width="88" height="88" style="display:block;width:88px;height:88px;border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;">
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-family:{{ $font }};font-size:24px;line-height:30px;font-weight:bold;color:#ffffff;mso-line-height-rule:exactly;">
                                        {{ __('wa_health.menu_label') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top:6px;font-family:{{ $font }};font-size:14px;line-height:20px;color:#d1fae5;mso-line-height-rule:exactly;">
                                        {{ __('wa_health.digest_daily_report', ['date' => $dateLong]) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:28px 28px 10px;font-family:{{ $font }};">
                            <p style="margin:0 0 12px;font-size:16px;line-height:24px;color:#111827;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_greeting', ['name' => $recipientName !== '' ? $recipientName : __('wa_health.digest_greeting_fallback')]) }}
                            </p>
                            <p style="margin:0;font-size:15px;line-height:22px;color:#4b5563;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_intro') }}
                            </p>
                        </td>
                    </tr>

                    {{-- Status banner --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:8px 28px 18px;">
                            @if (! empty($summary['needsAttention']))
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fff4f4" style="background-color:#fff4f4;border:1px solid #fecaca;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:14px 16px;font-family:{{ $font }};font-size:14px;line-height:22px;color:#991b1b;mso-line-height-rule:exactly;">
                                            <strong>{{ __('wa_health.digest_health_attention') }}</strong><br>
                                            {{ __('wa_health.digest_health_attention_help') }}
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0fdf4" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:14px 16px;font-family:{{ $font }};font-size:14px;line-height:22px;color:#166534;mso-line-height-rule:exactly;">
                                            <strong>{{ __('wa_health.digest_health_ok') }}</strong><br>
                                            {{ __('wa_health.digest_health_ok_help') }}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- KPI grid --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:0 28px 8px;font-family:{{ $font }};">
                            <p style="margin:0 0 14px;font-size:18px;line-height:24px;font-weight:bold;color:#075E54;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_summary_title') }}
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <td class="wa-email-stack" width="276" valign="top" style="width:276px;padding:0 8px 12px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border:1px solid #e5e7eb;border-collapse:collapse;">
                                            <tr>
                                                <td style="padding:16px;font-family:{{ $font }};">
                                                    <p style="margin:0 0 6px;font-size:12px;line-height:16px;text-transform:uppercase;color:#6b7280;mso-line-height-rule:exactly;">{{ __('wa_health.digest_lines_section') }}</p>
                                                    <p style="margin:0;font-size:28px;line-height:32px;font-weight:bold;color:#111827;mso-line-height-rule:exactly;">{{ number_format($lines['total'] ?? 0) }}</p>
                                                    <p style="margin:8px 0 0;font-size:13px;line-height:20px;color:#4b5563;mso-line-height-rule:exactly;">
                                                        {{ __('wa_health.lines_connected') }}: <strong>{{ number_format($lines['connected'] ?? 0) }}</strong><br>
                                                        <span style="color:#16a34a;">{{ __('wa_health.digest_quality_good') }} {{ number_format($lines['green'] ?? 0) }}</span> |
                                                        <span style="color:#ca8a04;">{{ __('wa_health.digest_quality_watch') }} {{ number_format($lines['yellow'] ?? 0) }}</span> |
                                                        <span style="color:#dc2626;">{{ __('wa_health.digest_quality_poor') }} {{ number_format($lines['red'] ?? 0) }}</span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td class="wa-email-stack" width="276" valign="top" style="width:276px;padding:0 0 12px 8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border:1px solid #e5e7eb;border-collapse:collapse;">
                                            <tr>
                                                <td style="padding:16px;font-family:{{ $font }};">
                                                    <p style="margin:0 0 6px;font-size:12px;line-height:16px;text-transform:uppercase;color:#6b7280;mso-line-height-rule:exactly;">{{ __('wa_health.digest_templates_section') }}</p>
                                                    <p style="margin:0;font-size:28px;line-height:32px;font-weight:bold;color:#111827;mso-line-height-rule:exactly;">{{ number_format($tpl['total'] ?? 0) }}</p>
                                                    <p style="margin:8px 0 0;font-size:13px;line-height:20px;color:#4b5563;mso-line-height-rule:exactly;">
                                                        {{ __('wa_health.templates_pending') }}: <strong>{{ number_format($tpl['pending'] ?? 0) }}</strong><br>
                                                        {{ __('wa_health.templates_rejected') }}: <strong>{{ number_format($tpl['rejected'] ?? 0) }}</strong>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="wa-email-stack" colspan="2" valign="top" style="padding:0 0 12px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f9fafb" style="background-color:#f9fafb;border:1px solid #e5e7eb;border-collapse:collapse;">
                                            <tr>
                                                <td style="padding:16px;font-family:{{ $font }};">
                                                    <p style="margin:0 0 6px;font-size:12px;line-height:16px;text-transform:uppercase;color:#6b7280;mso-line-height-rule:exactly;">{{ __('wa_health.digest_alerts_section') }}</p>
                                                    <p style="margin:0;font-size:28px;line-height:32px;font-weight:bold;color:#111827;mso-line-height-rule:exactly;">{{ number_format($summary['unread'] ?? 0) }}</p>
                                                    <p style="margin:8px 0 0;font-size:13px;line-height:20px;color:#4b5563;mso-line-height-rule:exactly;">
                                                        {{ __('wa_health.alerts_unread') }}<br>
                                                        {{ __('wa_health.alerts_critical_7d') }}: <strong>{{ number_format($alerts['critical_7d'] ?? 0) }}</strong>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Today's usage (lowest / highest 5) --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:8px 28px 18px;font-family:{{ $font }};">
                            <p style="margin:0 0 8px;font-size:18px;line-height:24px;font-weight:bold;color:#075E54;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_usage_section') }}
                            </p>
                            <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#6b7280;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_usage_help') }}
                            </p>
                            @if (count($leastFive) > 0 || count($mostFive) > 0)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                    <tr>
                                        <td class="wa-email-stack" width="276" valign="top" style="width:276px;padding:0 8px 12px 0;">
                                            <p style="margin:0 0 10px;font-size:14px;line-height:20px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">
                                                {{ __('wa_health.digest_least_usage_today') }}
                                            </p>
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-collapse:collapse;">
                                                <tr bgcolor="#f3f4f6" style="background-color:#f3f4f6;">
                                                    <td style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_customer') }}</td>
                                                    <td align="right" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_messages_out') }}</td>
                                                </tr>
                                                @foreach ($leastFive as $row)
                                                    <tr>
                                                        <td valign="top" style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;line-height:18px;color:#111827;mso-line-height-rule:exactly;">
                                                            <strong>{{ $row['name'] ?? $row['uid'] ?? '—' }}</strong>
                                                            @if (! empty($row['uid']))
                                                                <br><span style="color:#6b7280;">{{ $row['uid'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td align="right" valign="top" style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;line-height:18px;font-weight:bold;color:#111827;mso-line-height-rule:exactly;">
                                                            {{ number_format($row['outbound'] ?? 0) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                        <td class="wa-email-stack" width="276" valign="top" style="width:276px;padding:0 0 12px 8px;">
                                            <p style="margin:0 0 10px;font-size:14px;line-height:20px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">
                                                {{ __('wa_health.digest_most_usage_today') }}
                                            </p>
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-collapse:collapse;">
                                                <tr bgcolor="#f3f4f6" style="background-color:#f3f4f6;">
                                                    <td style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_customer') }}</td>
                                                    <td align="right" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_messages_out') }}</td>
                                                </tr>
                                                @foreach ($mostFive as $row)
                                                    <tr>
                                                        <td valign="top" style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;line-height:18px;color:#111827;mso-line-height-rule:exactly;">
                                                            <strong>{{ $row['name'] ?? $row['uid'] ?? '—' }}</strong>
                                                            @if (! empty($row['uid']))
                                                                <br><span style="color:#6b7280;">{{ $row['uid'] }}</span>
                                                            @endif
                                                        </td>
                                                        <td align="right" valign="top" style="padding:10px 12px;border-top:1px solid #e5e7eb;font-size:13px;line-height:18px;font-weight:bold;color:#111827;mso-line-height-rule:exactly;">
                                                            {{ number_format($row['outbound'] ?? 0) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:12px 0 0;font-size:12px;line-height:18px;mso-line-height-rule:exactly;">
                                    <a href="{{ $performanceLink }}" target="_blank" style="color:#075E54;text-decoration:underline;">{{ __('wa_health.digest_view_performance') }}</a>
                                </p>
                            @else
                                <p style="margin:0;font-size:14px;line-height:22px;color:#6b7280;mso-line-height-rule:exactly;">{{ __('wa_health.digest_usage_empty') }}</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Messaging performance --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:8px 28px 18px;font-family:{{ $font }};">
                            <p style="margin:0 0 8px;font-size:18px;line-height:24px;font-weight:bold;color:#075E54;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_messaging_section') }}
                            </p>
                            <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#6b7280;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_messaging_period', [
                                    'from' => $perf['date_from'] ?? '',
                                    'to' => $perf['date_to'] ?? '',
                                ]) }}
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f8fbff" style="background-color:#f8fbff;border:1px solid #dbeafe;border-collapse:collapse;">
                                <tr>
                                    <td width="60%" style="padding:12px 14px;font-size:13px;line-height:18px;color:#38546c;mso-line-height-rule:exactly;">{{ __('wa_health.col_send_rate') }}</td>
                                    <td width="40%" align="right" style="padding:12px 14px;font-size:14px;line-height:18px;font-weight:bold;color:#123a60;mso-line-height-rule:exactly;">{{ number_format($mro['send_rate'] ?? 0, 1) }}% <span style="font-weight:normal;color:#6b7280;">({{ number_format($mro['sent'] ?? 0) }}/{{ number_format($mro['outbound'] ?? 0) }})</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:13px;line-height:18px;color:#38546c;mso-line-height-rule:exactly;">{{ __('wa_health.col_delivery_rate') }}</td>
                                    <td align="right" style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:14px;line-height:18px;font-weight:bold;color:#123a60;mso-line-height-rule:exactly;">{{ number_format($mro['delivery_rate'] ?? 0, 1) }}% <span style="font-weight:normal;color:#6b7280;">({{ number_format($mro['delivered'] ?? 0) }}/{{ number_format($mro['sent'] ?? 0) }})</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:13px;line-height:18px;color:#38546c;mso-line-height-rule:exactly;">{{ __('wa_health.col_response_rate') }}</td>
                                    <td align="right" style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:14px;line-height:18px;font-weight:bold;color:#123a60;mso-line-height-rule:exactly;">{{ number_format($mro['response_rate'] ?? 0, 1) }}% <span style="font-weight:normal;color:#6b7280;">({{ number_format($mro['replied'] ?? 0) }}/{{ number_format($mro['delivered'] ?? 0) }})</span></td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:13px;line-height:18px;color:#38546c;mso-line-height-rule:exactly;">{{ __('wa_health.col_unsubscribe_rate') }}</td>
                                    <td align="right" style="padding:12px 14px;border-top:1px solid #e4edf5;font-size:14px;line-height:18px;font-weight:bold;color:#123a60;mso-line-height-rule:exactly;">{{ number_format($mro['unsubscribe_rate'] ?? 0, 1) }}% <span style="font-weight:normal;color:#6b7280;">({{ number_format($mro['subscribers_unsub'] ?? 0) }}/{{ number_format($mro['subscribers_total'] ?? 0) }})</span></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Template errors with reasons --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:8px 28px 18px;font-family:{{ $font }};">
                            <p style="margin:0 0 8px;font-size:18px;line-height:24px;font-weight:bold;color:#075E54;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_template_errors_section') }}
                            </p>
                            <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#6b7280;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_template_errors_help') }}
                            </p>
                            @if (! empty($summary['templateErrors']) && count($summary['templateErrors']) > 0)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #fecaca;border-collapse:collapse;">
                                    <tr bgcolor="#fef2f2" style="background-color:#fef2f2;">
                                        <td width="22%" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_customer') }}</td>
                                        <td width="20%" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_template') }}</td>
                                        <td width="14%" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_template_status') }}</td>
                                        <td width="32%" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_error_message') }}</td>
                                        <td width="12%" style="padding:10px 12px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_updated') }}</td>
                                    </tr>
                                    @foreach ($summary['templateErrors'] as $tplErr)
                                        @php
                                            $custLabel = data_get($tplErr, 'customer_display_name') ?: data_get($tplErr, 'customer_uid') ?: '—';
                                            $tplName = data_get($tplErr, 'template_name') ?: '—';
                                            $tplStatus = data_get($tplErr, 'status') ?: '—';
                                            $mediaStatus = data_get($tplErr, 'header_media_status');
                                            $errReason = data_get($tplErr, 'error_reason');
                                            $updatedAt = data_get($tplErr, 'updated_at') ?: '—';
                                            $mediaBad = is_string($mediaStatus) && $mediaStatus !== ''
                                                && preg_match('/fail|error|missing|invalid|reject/i', $mediaStatus);
                                        @endphp
                                        <tr>
                                            <td valign="top" style="padding:12px;border-top:1px solid #fecaca;font-size:12px;line-height:18px;color:#111827;mso-line-height-rule:exactly;">
                                                <strong>{{ $custLabel }}</strong>
                                            </td>
                                            <td valign="top" style="padding:12px;border-top:1px solid #fecaca;font-size:12px;line-height:18px;color:#111827;mso-line-height-rule:exactly;">
                                                <strong>{{ $tplName }}</strong>
                                            </td>
                                            <td valign="top" style="padding:12px;border-top:1px solid #fecaca;font-size:12px;line-height:16px;color:#dc2626;font-weight:bold;mso-line-height-rule:exactly;">
                                                {{ $tplStatus }}
                                                @if ($mediaBad)
                                                    <br><span style="color:#991b1b;font-weight:normal;">Media: {{ $mediaStatus }}</span>
                                                @endif
                                            </td>
                                            <td valign="top" style="padding:12px;border-top:1px solid #fecaca;font-size:12px;line-height:18px;color:#7f1d1d;mso-line-height-rule:exactly;">
                                                @if (! empty($errReason))
                                                    {{ $errReason }}
                                                @else
                                                    <span style="color:#6b7280;">{{ __('wa_health.digest_template_no_reason') }}</span>
                                                @endif
                                            </td>
                                            <td valign="top" style="padding:12px;border-top:1px solid #fecaca;font-size:11px;line-height:16px;color:#6b7280;mso-line-height-rule:exactly;">
                                                {{ is_object($updatedAt) && method_exists($updatedAt, 'format') ? $updatedAt->format('d M Y H:i') : $updatedAt }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                                <p style="margin:12px 0 0;font-size:12px;line-height:18px;mso-line-height-rule:exactly;">
                                    <a href="{{ $templatesLink }}" target="_blank" style="color:#075E54;text-decoration:underline;">{{ __('wa_health.digest_template_errors_link') }}</a>
                                </p>
                            @else
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0fdf4" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-collapse:collapse;">
                                    <tr>
                                        <td style="padding:14px 16px;font-size:14px;line-height:22px;color:#166534;mso-line-height-rule:exactly;">
                                            {{ __('wa_health.digest_template_errors_empty') }}
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Customer activity --}}
                    @if (! empty($summary['customerActivity']) && count($summary['customerActivity']) > 0)
                        <tr>
                            <td class="wa-email-pad" style="padding:8px 28px 18px;font-family:{{ $font }};">
                                <p style="margin:0 0 8px;font-size:18px;line-height:24px;font-weight:bold;color:#075E54;mso-line-height-rule:exactly;">
                                    {{ __('wa_health.digest_customer_activity_section') }}
                                </p>
                                <p style="margin:0 0 14px;font-size:13px;line-height:20px;color:#6b7280;mso-line-height-rule:exactly;">
                                    {{ __('wa_health.digest_customer_activity_help') }}
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;border-collapse:collapse;">
                                    <tr bgcolor="#f3f4f6" style="background-color:#f3f4f6;">
                                        <td width="34%" style="padding:10px 14px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_customer') }}</td>
                                        <td width="33%" style="padding:10px 14px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_last_campaign') }}</td>
                                        <td width="33%" style="padding:10px 14px;font-size:12px;line-height:16px;font-weight:bold;color:#374151;mso-line-height-rule:exactly;">{{ __('wa_health.col_last_message') }}</td>
                                    </tr>
                                    @foreach ($summary['customerActivity'] as $row)
                                        @php
                                            $actName = data_get($row, 'customer_display_name') ?: data_get($row, 'customer_uid') ?: '—';
                                            $bizName = data_get($row, 'business_name');
                                            $lastCampaignAt = data_get($row, 'last_campaign_at');
                                            $lastCampaignName = data_get($row, 'last_campaign_name');
                                            $lastMessageAt = data_get($row, 'last_message_at');
                                        @endphp
                                        <tr>
                                            <td valign="top" style="padding:12px 14px;border-top:1px solid #e5e7eb;font-size:13px;line-height:20px;color:#111827;mso-line-height-rule:exactly;">
                                                <strong>{{ $actName }}</strong>
                                                @if (! empty($bizName))
                                                    <br><span style="color:#6b7280;">{{ $bizName }}</span>
                                                @endif
                                            </td>
                                            <td valign="top" style="padding:12px 14px;border-top:1px solid #e5e7eb;font-size:12px;line-height:18px;color:#374151;mso-line-height-rule:exactly;">
                                                @if (! empty($lastCampaignAt))
                                                    <strong>{{ is_object($lastCampaignAt) && method_exists($lastCampaignAt, 'format') ? $lastCampaignAt->format('d M Y, h:i A') : $lastCampaignAt }}</strong>
                                                    @if (! empty($lastCampaignName))
                                                        <br><span style="color:#6b7280;">{{ $lastCampaignName }}</span>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td valign="top" style="padding:12px 14px;border-top:1px solid #e5e7eb;font-size:12px;line-height:18px;color:#6b7280;mso-line-height-rule:exactly;">
                                                {{ ! empty($lastMessageAt) ? (is_object($lastMessageAt) && method_exists($lastMessageAt, 'format') ? $lastMessageAt->format('d M Y, h:i A') : $lastMessageAt) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Ops note --}}
                    <tr>
                        <td class="wa-email-pad" style="padding:4px 28px 18px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fafafa" style="background-color:#fafafa;border:1px solid #e5e7eb;border-collapse:collapse;">
                                <tr>
                                    <td style="padding:14px 16px;font-family:{{ $font }};font-size:12px;line-height:20px;color:#6b7280;mso-line-height-rule:exactly;">
                                        @if (! empty($meta['last_snapshot']['at']))
                                            {{ __('wa_health.last_snapshot_at', ['at' => $meta['last_snapshot']['at'], 'lines' => $meta['last_snapshot']['lines'] ?? 0]) }}<br>
                                        @else
                                            {{ __('wa_health.last_snapshot_never') }}<br>
                                        @endif
                                        {{ __('wa_health.lines_without_quality') }}: <strong>{{ number_format($meta['lines_without_quality'] ?? 0) }}</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA (bulletproof for Outlook) --}}
                    <tr>
                        <td align="center" class="wa-email-pad" style="padding:8px 28px 28px;">
                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" align="center" style="border-collapse:collapse;">
                                <tr>
                                    <td align="center" bgcolor="#25D366" style="background-color:#25D366;">
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $healthLink }}" style="height:44px;v-text-anchor:middle;width:280px;" arcsize="10%" strokecolor="#25D366" fillcolor="#25D366">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">{{ __('wa_health.digest_cta') }}</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="{{ $healthLink }}" target="_blank" style="background-color:#25D366;border:1px solid #25D366;color:#ffffff;display:inline-block;font-family:{{ $font }};font-size:15px;font-weight:bold;line-height:44px;text-align:center;text-decoration:none;width:280px;-webkit-text-size-adjust:none;mso-hide:all;">
                                            {{ __('wa_health.digest_cta') }}
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0;font-family:{{ $font }};font-size:12px;line-height:18px;color:#9ca3af;text-align:center;mso-line-height-rule:exactly;">
                                {{ __('wa_health.digest_cta_help') }}
                            </p>
                            <p style="margin:8px 0 0;font-family:{{ $font }};font-size:12px;line-height:18px;color:#9ca3af;text-align:center;mso-line-height-rule:exactly;">
                                <a href="{{ $healthLink }}" target="_blank" style="color:#075E54;text-decoration:underline;">{{ $healthLink }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" bgcolor="#f8fafc" class="wa-email-pad" style="padding:18px 28px;background-color:#f8fafc;border-top:1px solid #e5e7eb;font-family:{{ $font }};font-size:12px;line-height:20px;color:#6b7280;text-align:center;mso-line-height-rule:exactly;">
                            {{ __('wa_health.digest_footer', ['app' => config('app.name')]) }}<br>
                            {{ __('wa_health.digest_generated_at', ['at' => $dateStamp]) }}
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
