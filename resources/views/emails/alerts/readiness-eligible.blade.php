<p>Hello {{ $customer_name ?? $name ?? '' }},</p>
<p>Good news — your business readiness submission looks eligible. Our team will follow up shortly.</p>
<ul>
  <li><strong>Business:</strong> {{ $business_name ?? '—' }}</li>
  <li><strong>Email:</strong> {{ $customer_email ?? $email ?? '—' }}</li>
</ul>
