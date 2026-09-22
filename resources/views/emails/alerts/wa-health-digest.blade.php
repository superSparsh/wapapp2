<p style="font-family:Arial,sans-serif;color:#111;">
  <strong>WhatsApp Health Digest</strong> — {{ $generated_at ?? now()->format('d M Y') }}
</p>

@if (!empty($needs_attention))
  <p style="padding:10px;background:#fef2f2;color:#991b1b;font-family:Arial,sans-serif;">
    Attention needed: unread alerts or degraded line quality.
  </p>
@else
  <p style="padding:10px;background:#ecfdf5;color:#065f46;font-family:Arial,sans-serif;">
    Fleet looks healthy.
  </p>
@endif

<ul style="font-family:Arial,sans-serif;font-size:14px;color:#333;">
  <li><strong>Lines:</strong> {{ $lines_checked ?? 0 }}</li>
  <li><strong>Green / Yellow / Red:</strong> {{ $quality_green ?? 0 }} / {{ $quality_yellow ?? 0 }} / {{ $quality_red ?? 0 }}</li>
  <li><strong>Unread alerts:</strong> {{ $unread_alerts ?? 0 }}</li>
  <li><strong>Critical (7d):</strong> {{ $critical_alerts_7d ?? 0 }}</li>
  <li><strong>Failed messages:</strong> {{ $failed_messages ?? 0 }}</li>
</ul>

@if (!empty($recent_alerts))
  <p style="font-family:Arial,sans-serif;"><strong>Recent alerts</strong></p>
  <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;">
    <thead>
      <tr>
        <th>Severity</th>
        <th>Title</th>
        <th>Tenant</th>
        <th>When</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($recent_alerts as $alert)
        <tr>
          <td>{{ $alert['severity'] ?? '' }}</td>
          <td>{{ $alert['title'] ?? '' }}<br><span style="color:#666;">{{ \Illuminate\Support\Str::limit($alert['body'] ?? '', 80) }}</span></td>
          <td>{{ $alert['tenant_id'] ?? '—' }}</td>
          <td>{{ $alert['occurred_at'] ?? '' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif

@isset($health_url)
  <p style="font-family:Arial,sans-serif;margin-top:16px;">
    <a href="{{ $health_url }}">Open WhatsApp Health Center</a>
  </p>
@endisset

@isset($notes)
  <p style="font-family:Arial,sans-serif;font-size:12px;color:#666;">{{ $notes }}</p>
@endisset
