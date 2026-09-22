<p>Hello,</p>
<p>A wallet credit request was received.</p>
<ul>
  @foreach ($payload ?? [] as $key => $value)
    @if (! is_array($value))
      <li><strong>{{ str_replace('_', ' ', ucfirst((string) $key)) }}:</strong> {{ $value }}</li>
    @endif
  @endforeach
  @isset($amount)
    <li><strong>Amount:</strong> {{ $amount }} {{ $currency ?? 'INR' }}</li>
  @endisset
  @isset($customer_email)
    <li><strong>Customer:</strong> {{ $customer_email }}</li>
  @endisset
  @isset($customer_name)
    <li><strong>Name:</strong> {{ $customer_name }}</li>
  @endisset
  @isset($reference)
    <li><strong>Reference:</strong> {{ $reference }}</li>
  @endisset
</ul>
