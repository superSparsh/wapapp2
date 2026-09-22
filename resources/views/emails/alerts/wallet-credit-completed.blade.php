<p>Hello,</p>
<p>Your wallet credit has been completed successfully.</p>
<ul>
  @isset($amount)
    <li><strong>Amount:</strong> {{ $amount }} {{ $currency ?? 'INR' }}</li>
  @endisset
  @isset($payment_id)
    <li><strong>Payment ID:</strong> {{ $payment_id }}</li>
  @endisset
  @isset($invoice_id)
    <li><strong>Invoice:</strong> {{ $invoice_id }}</li>
  @endisset
  @isset($balance)
    <li><strong>New balance:</strong> {{ $balance }}</li>
  @endisset
</ul>
<p>Thank you for using WapApp.</p>
