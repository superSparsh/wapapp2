<p>Hello,</p>
<p>Your WhatsApp phone line quality or messaging limit has changed.</p>
<ul>
  <li><strong>Phone:</strong> {{ $phone }} @if(!empty($display_name)) ({{ $display_name }}) @endif</li>
  <li><strong>Quality:</strong> {{ $old_quality ?? '—' }} → {{ $new_quality ?? '—' }}</li>
  <li><strong>Messaging tier:</strong> {{ $old_tier ?? '—' }} → {{ $new_tier ?? '—' }}</li>
</ul>
<p>Please review your Meta / WhatsApp Business quality if the rating dropped.</p>
