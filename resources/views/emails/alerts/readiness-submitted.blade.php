<p>A customer readiness form was submitted.</p>
<ul>
  <li><strong>Name:</strong> {{ $customer_name ?? $name ?? '—' }}</li>
  <li><strong>Email:</strong> {{ $customer_email ?? $email ?? '—' }}</li>
  <li><strong>Business:</strong> {{ $business_name ?? '—' }}</li>
  <li><strong>Doc type:</strong> {{ $doc_type ?? '—' }}</li>
  <li><strong>Website:</strong> {{ $website ?? '—' }}</li>
</ul>
