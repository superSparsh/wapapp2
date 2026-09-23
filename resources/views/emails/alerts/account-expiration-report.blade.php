@php
    $rows = $rows ?? [];
    $generatedAt = $generated_at ?? now()->format('d M Y h:i A');
    $logoUrl = asset('images/tittu-logo.jpeg');
    $font = 'Arial, Helvetica, sans-serif';

    $within30 = [];
    $beyond30 = [];
    foreach ($rows as $row) {
        $days = (int) ($row['days_left'] ?? 999);
        if ($days <= 30) {
            $within30[] = $row;
        } else {
            $beyond30[] = $row;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account expiry report — {{ $generatedAt }}</title>
</head>
<body style="margin:0;padding:0;background:#f2f6fb;font-family:{{ $font }};color:#13334c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="720" cellpadding="0" cellspacing="0" style="max-width:720px;width:100%;background:#fff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:20px;background:#1f4f9f;">
                            <img src="{{ $logoUrl }}" alt="WAPAPP" width="80" height="80" style="display:block;border:0;border-radius:8px;margin:0 auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 24px 12px;">
                            <h2 style="margin:0 0 6px;font-size:20px;color:#123a60;">Account expiry report</h2>
                            <p style="margin:0;font-size:14px;color:#5b7288;">Generated {{ $generatedAt }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="50%" style="padding-right:6px;">
                                        <div style="padding:12px 14px;border:1px solid #fde4c8;border-radius:8px;background:#fffaf5;">
                                            <div style="font-size:12px;color:#9a5b1a;">Expiring within 30 days</div>
                                            <div style="font-size:24px;font-weight:bold;color:#c05621;">{{ count($within30) }}</div>
                                        </div>
                                    </td>
                                    <td width="50%" style="padding-left:6px;">
                                        <div style="padding:12px 14px;border:1px solid #dbe8f5;border-radius:8px;background:#f8fcff;">
                                            <div style="font-size:12px;color:#35658f;">Expiring in 31+ days</div>
                                            <div style="font-size:24px;font-weight:bold;color:#1f4f9f;">{{ count($beyond30) }}</div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @foreach ([
                        ['title' => 'Expiring within 30 days', 'items' => $within30, 'daysColor' => '#c05621'],
                        ['title' => 'Expiring in 31+ days', 'items' => $beyond30, 'daysColor' => '#1f4f9f'],
                    ] as $section)
                        <tr>
                            <td style="padding:0 24px 16px;">
                                <h3 style="margin:0 0 10px;font-size:16px;color:#123a60;">{{ $section['title'] }}</h3>
                                @if (count($section['items']) > 0)
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6eef6;border-radius:8px;">
                                        <tr style="background:#f7fbff;">
                                            <th align="left" style="padding:8px 10px;font-size:12px;color:#5b7288;">Customer</th>
                                            <th align="left" style="padding:8px 10px;font-size:12px;color:#5b7288;">Plan</th>
                                            <th align="left" style="padding:8px 10px;font-size:12px;color:#5b7288;">Ends on</th>
                                            <th align="left" style="padding:8px 10px;font-size:12px;color:#5b7288;">Left</th>
                                        </tr>
                                        @foreach ($section['items'] as $row)
                                            <tr>
                                                <td style="padding:8px 10px;font-size:13px;border-top:1px solid #eef3f8;">
                                                    <strong>{{ $row['tenant'] ?? '—' }}</strong><br>
                                                    <span style="color:#6a8196;">{{ $row['email'] ?? '—' }}</span>
                                                </td>
                                                <td style="padding:8px 10px;font-size:13px;border-top:1px solid #eef3f8;">{{ $row['plan'] ?? '—' }}</td>
                                                <td style="padding:8px 10px;font-size:13px;border-top:1px solid #eef3f8;">{{ $row['ends_at'] ?? '—' }}</td>
                                                <td style="padding:8px 10px;font-size:13px;border-top:1px solid #eef3f8;color:{{ $section['daysColor'] }};">
                                                    {{ isset($row['days_left']) ? $row['days_left'].' day(s)' : '—' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                @else
                                    <p style="margin:0;font-size:14px;color:#5b7288;">None.</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td style="padding:12px 24px;background:#f7fbff;border-top:1px solid #e6eef6;font-size:12px;color:#6a8196;">
                            Monthly report · WAPAPP
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
