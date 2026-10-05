@php
    $s = (isset($summary) && is_array($summary)) ? $summary : [];
    $email = is_array($s['email'] ?? null) ? $s['email'] : [];
    $score = is_array($email['scorecard'] ?? null) ? $email['scorecard'] : [];
    $actionItems = is_array($email['action_items'] ?? $s['actionItems'] ?? null)
        ? ($email['action_items'] ?? $s['actionItems'])
        : [];
    $rejectedByCustomer = is_array($email['rejected_by_customer'] ?? $s['rejectedByCustomer'] ?? null)
        ? ($email['rejected_by_customer'] ?? $s['rejectedByCustomer'])
        : [];
    $activity = is_array($email['activity'] ?? $s['activityBuckets'] ?? null)
        ? ($email['activity'] ?? $s['activityBuckets'])
        : ['active' => ['count' => 0, 'names' => []], 'quiet' => ['count' => 0, 'names' => []], 'inactive' => ['count' => 0, 'names' => []]];
    $freshness = is_array($email['freshness'] ?? null) ? $email['freshness'] : null;
    $adminUser = is_object($admin ?? null) ? ($admin->user ?? $admin) : null;
    $recipientName = '';
    if (is_object($adminUser)) {
        $recipientName = trim(($adminUser->first_name ?? '').' '.($adminUser->last_name ?? ''));
        if ($recipientName === '') {
            $recipientName = trim((string) ($adminUser->name ?? ''));
        }
    }
    if ($recipientName === '') {
        $recipientName = 'team';
    }
    $healthLink = $healthUrl ?? $health_url ?? url('/admin/whatsapp-health');
    $templatesLink = $healthLink.(str_contains((string) $healthLink, '?') ? '&' : '?').'tab=templates&template_status=REJECTED';
    $subject = (string) ($email['subject'] ?? __('wa_health.digest_subject', ['date' => $email['date_short'] ?? now()->format('j M Y')]));
    $preheader = (string) ($email['preheader'] ?? '');
    $greeting = (string) ($email['greeting'] ?? 'Hello');
    $headline = (string) ($email['headline'] ?? '');
    $headlineDetail = (string) ($email['headline_detail'] ?? '');
    $dateLabel = (string) ($email['date_label'] ?? now()->timezone('Asia/Kolkata')->format('D, j M Y'));
    $periodLabel = (string) ($email['period_label'] ?? '');
    $rejectedTotal = (int) ($email['rejected_total'] ?? data_get($s, 'overview.templates.rejected', 0));
    $footerStamp = (string) ($email['footer_stamp'] ?? now()->timezone('Asia/Kolkata')->format('j M Y, H:i').' IST');
    $snapshotLabel = (string) ($email['snapshot_label'] ?? data_get($s, 'operationalMeta.last_snapshot.at', 'n/a'));
    $font = 'Arial, Helvetica, sans-serif';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f1;font-family:{{ $font }};color:#1f2933;">

<div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#eef2f1;">
{{ $preheader }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f1;">
<tr><td align="center" style="padding:24px 12px;">

<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">

  <tr>
    <td style="background:#07594f;padding:20px 28px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td style="color:#ffffff;font-size:20px;font-weight:bold;">Tittu WhatsApp Health</td>
          <td align="right" style="color:#b9e3dc;font-size:13px;">{{ $dateLabel }}</td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td style="padding:24px 28px 8px 28px;">
      <p style="margin:0 0 8px 0;font-size:15px;">{{ $greeting }} {{ $recipientName }},</p>
      <p style="margin:0;font-size:15px;line-height:22px;">
        <strong>{{ $headline }}</strong>
        @if ($headlineDetail !== '')
          {{ $headlineDetail }}
        @endif
      </p>
    </td>
  </tr>

  @if ($freshness)
  <tr>
    <td style="padding:16px 28px 0 28px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff6e5;border-left:4px solid #e0a100;border-radius:6px;">
        <tr>
          <td style="padding:12px 16px;font-size:13px;line-height:19px;color:#6b4e00;">
            <strong>{{ $freshness['title'] ?? '' }}</strong> {{ $freshness['body'] ?? '' }}
          </td>
        </tr>
      </table>
    </td>
  </tr>
  @endif

  @if (count($actionItems) > 0)
  <tr>
    <td style="padding:24px 28px 0 28px;">
      <p style="margin:0 0 12px 0;font-size:16px;font-weight:bold;color:#07594f;">Action required</p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e8e7;border-radius:8px;">
        @foreach ($actionItems as $index => $item)
          @php
            $severity = (string) ($item['severity'] ?? 'warning');
            $badgeBg = $severity === 'critical' ? '#d64545' : '#e0a100';
            $isLast = $index === count($actionItems) - 1;
          @endphp
          <tr>
            <td width="44" valign="top" style="padding:14px 0 14px 14px;">
              <div style="width:26px;height:26px;line-height:26px;border-radius:13px;background:{{ $badgeBg }};color:#fff;font-size:13px;font-weight:bold;text-align:center;">{{ $index + 1 }}</div>
            </td>
            <td style="padding:14px 14px 14px 8px;{{ $isLast ? '' : 'border-bottom:1px solid #eef1f0;' }}">
              <p style="margin:0;font-size:14px;font-weight:bold;">{{ $item['title'] ?? '' }}</p>
              @if (! empty($item['body']))
                <p style="margin:4px 0 0 0;font-size:13px;color:#52606d;line-height:19px;">{{ $item['body'] }}</p>
              @endif
              @if (! empty($item['href']) && ! empty($item['cta']))
                <a href="{{ $item['href'] }}" style="display:inline-block;margin-top:6px;font-size:13px;color:#07594f;font-weight:bold;text-decoration:none;">{{ $item['cta'] }} &rarr;</a>
              @endif
            </td>
          </tr>
        @endforeach
      </table>
    </td>
  </tr>
  @endif

  <tr>
    <td style="padding:28px 28px 0 28px;">
      <p style="margin:0 0 12px 0;font-size:16px;font-weight:bold;color:#07594f;">This week at a glance</p>
      @if ($periodLabel !== '')
        <p style="margin:-6px 0 12px 0;font-size:12px;color:#7b8794;">{{ $periodLabel }}</p>
      @endif

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td width="50%" valign="top" style="padding:0 6px 12px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f9f9;border-radius:8px;">
              <tr><td style="padding:14px 16px;">
                <p style="margin:0;font-size:12px;color:#7b8794;text-transform:uppercase;letter-spacing:0.5px;">Delivery rate</p>
                <p style="margin:6px 0 2px 0;font-size:26px;font-weight:bold;color:{{ $score['delivery_rate_color'] ?? '#1f2933' }};">{{ number_format((float) ($score['delivery_rate'] ?? 0), 1) }}%</p>
                <p style="margin:0;font-size:12px;color:#52606d;">{{ number_format((int) ($score['delivered'] ?? 0)) }} of {{ number_format((int) ($score['sent'] ?? 0)) }} sent</p>
              </td></tr>
            </table>
          </td>
          <td width="50%" valign="top" style="padding:0 0 12px 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f9f9;border-radius:8px;">
              <tr><td style="padding:14px 16px;">
                <p style="margin:0;font-size:12px;color:#7b8794;text-transform:uppercase;letter-spacing:0.5px;">Response rate</p>
                <p style="margin:6px 0 2px 0;font-size:26px;font-weight:bold;color:{{ $score['response_rate_color'] ?? '#1f2933' }};">{{ number_format((float) ($score['response_rate'] ?? 0), 1) }}%</p>
                <p style="margin:0;font-size:12px;color:#52606d;">{{ number_format((int) ($score['replied'] ?? 0)) }} replies on delivered</p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td width="50%" valign="top" style="padding:0 6px 0 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f9f9;border-radius:8px;">
              <tr><td style="padding:14px 16px;">
                <p style="margin:0;font-size:12px;color:#7b8794;text-transform:uppercase;letter-spacing:0.5px;">Numbers connected</p>
                <p style="margin:6px 0 2px 0;font-size:26px;font-weight:bold;">{{ number_format((int) ($score['connected'] ?? 0)) }} <span style="font-size:15px;color:#7b8794;font-weight:normal;">of {{ number_format((int) ($score['lines_total'] ?? 0)) }}</span></p>
                <p style="margin:0;font-size:12px;">
                  <span style="color:#1f9d55;">{{ number_format((int) ($score['green'] ?? 0)) }} good</span>
                  &middot; <span style="color:#d64545;">{{ number_format((int) ($score['red'] ?? 0) + (int) ($score['yellow'] ?? 0)) }} need work</span>
                  &middot; <span style="color:#7b8794;">{{ number_format((int) ($score['unrated'] ?? 0)) }} unrated</span>
                </p>
              </td></tr>
            </table>
          </td>
          <td width="50%" valign="top" style="padding:0 0 0 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f9f9;border-radius:8px;">
              <tr><td style="padding:14px 16px;">
                <p style="margin:0;font-size:12px;color:#7b8794;text-transform:uppercase;letter-spacing:0.5px;">Unsubscribes</p>
                @if (! empty($score['unsubscribes_tracked']))
                  <p style="margin:6px 0 2px 0;font-size:26px;font-weight:bold;">{{ number_format((float) ($score['unsubscribe_rate'] ?? 0), 1) }}%</p>
                  <p style="margin:0;font-size:12px;color:#52606d;">{{ number_format((int) ($score['unsubscribed'] ?? 0)) }} of {{ number_format((int) ($score['subscribers_total'] ?? 0)) }}</p>
                @else
                  <p style="margin:6px 0 2px 0;font-size:20px;font-weight:bold;color:#7b8794;">Not tracked</p>
                  <p style="margin:0;font-size:12px;color:#52606d;">Opt out capture not yet enabled</p>
                @endif
              </td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  @if (count($rejectedByCustomer) > 0)
  <tr>
    <td style="padding:28px 28px 0 28px;">
      <p style="margin:0 0 12px 0;font-size:16px;font-weight:bold;color:#07594f;">Rejected templates this week, by customer</p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;border:1px solid #e3e8e7;border-radius:8px;">
        <tr style="background:#f7f9f9;">
          <td style="padding:10px 14px;font-weight:bold;color:#52606d;">Customer</td>
          <td align="center" style="padding:10px 8px;font-weight:bold;color:#52606d;">Rejected</td>
          <td style="padding:10px 14px;font-weight:bold;color:#52606d;">Reason from Meta</td>
        </tr>
        @foreach ($rejectedByCustomer as $row)
          <tr>
            <td style="padding:10px 14px;border-top:1px solid #eef1f0;">
              <strong>{{ $row['customer'] ?? '-' }}</strong><br>
              <span style="color:#7b8794;font-size:12px;">{{ $row['templates'] ?? '' }}@if (! empty($row['dates'])) &middot; {{ $row['dates'] }}@endif</span>
            </td>
            <td align="center" style="padding:10px 8px;border-top:1px solid #eef1f0;font-weight:bold;color:#d64545;">{{ number_format((int) ($row['rejected'] ?? 0)) }}</td>
            <td style="padding:10px 14px;border-top:1px solid #eef1f0;color:#d64545;">{{ $row['reason'] ?? 'Not captured' }}</td>
          </tr>
        @endforeach
      </table>
      <p style="margin:8px 0 0 0;font-size:12px;color:#7b8794;line-height:18px;">
        Capture the <code>reason</code> field from Meta's template status webhook so customers know what to fix.
        <a href="{{ $templatesLink }}" style="color:#07594f;font-weight:bold;text-decoration:none;">All {{ number_format($rejectedTotal) }} templates &rarr;</a>
      </p>
    </td>
  </tr>
  @endif

  <tr>
    <td style="padding:28px 28px 0 28px;">
      <p style="margin:0 0 12px 0;font-size:16px;font-weight:bold;color:#07594f;">Customer activity</p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
        <tr>
          <td style="padding:0 0 12px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-left:4px solid #1f9d55;background:#f3faf6;border-radius:6px;">
              <tr><td style="padding:12px 14px;">
                <p style="margin:0 0 6px 0;font-weight:bold;color:#1f9d55;">Active in last 24 hours &middot; {{ number_format((int) data_get($activity, 'active.count', 0)) }}</p>
                <p style="margin:0;line-height:20px;color:#3e4c59;">
                  {{ count(data_get($activity, 'active.names', [])) > 0 ? implode(' · ', data_get($activity, 'active.names', [])) : 'None in the sample window.' }}
                </p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 12px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-left:4px solid #e0a100;background:#fffaf0;border-radius:6px;">
              <tr><td style="padding:12px 14px;">
                <p style="margin:0 0 6px 0;font-weight:bold;color:#b07d00;">Quiet for 2 to 7 days &middot; {{ number_format((int) data_get($activity, 'quiet.count', 0)) }}</p>
                <p style="margin:0;line-height:20px;color:#3e4c59;">
                  {{ count(data_get($activity, 'quiet.names', [])) > 0 ? implode(' · ', data_get($activity, 'quiet.names', [])) : 'None in the sample window.' }}
                </p>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-left:4px solid #d64545;background:#fdf3f3;border-radius:6px;">
              <tr><td style="padding:12px 14px;">
                <p style="margin:0 0 6px 0;font-weight:bold;color:#d64545;">No activity in 7+ days &middot; {{ number_format((int) data_get($activity, 'inactive.count', 0)) }}</p>
                <p style="margin:0;line-height:20px;color:#3e4c59;">
                  {{ count(data_get($activity, 'inactive.names', [])) > 0 ? implode(' · ', data_get($activity, 'inactive.names', [])) : 'None in the sample window.' }}
                </p>
                <p style="margin:6px 0 0 0;font-size:12px;color:#7b8794;">Worth a check in call: these accounts are paying but not sending.</p>
              </td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <tr>
    <td align="center" style="padding:28px;">
      <a href="{{ $healthLink }}" style="display:inline-block;background:#07594f;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;padding:12px 28px;border-radius:6px;">Open WhatsApp Health Center</a>
    </td>
  </tr>

  <tr>
    <td style="background:#f7f9f9;padding:16px 28px;font-size:11px;color:#7b8794;line-height:17px;">
      Sent {{ $footerStamp }} by {{ config('app.name', 'WapApp') }} Health Center.<br>
      Campaign data: {{ $periodLabel !== '' ? $periodLabel : 'last 7 days' }}. Activity: previous full day and last 7 days. Quality snapshot: {{ $snapshotLabel }}.<br>
      Internal test accounts excluded from customer figures where configured.
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
