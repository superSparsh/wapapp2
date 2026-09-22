<p>Hello,</p>
<p>You received a new WhatsApp message.</p>
<ul>
  <li><strong>From:</strong> {{ $contact_name ?: $from_phone }} ({{ $from_phone }})</li>
  <li><strong>Received:</strong> {{ $received_at }}</li>
</ul>
<p><strong>Preview:</strong></p>
<p>{{ $preview }}</p>
<p>Please log in to your WapApp inbox to reply.</p>
