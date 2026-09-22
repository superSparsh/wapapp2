<p>Hello,</p>
<p>Your wallet balance is low.</p>
<ul>
  <li><strong>Current balance:</strong> {{ number_format((float) $balance, 2) }} {{ $currency ?? 'INR' }}</li>
  <li><strong>Alert threshold:</strong> {{ number_format((float) $threshold, 2) }} {{ $currency ?? 'INR' }}</li>
  <li><strong>Context:</strong> {{ $context }}</li>
</ul>
<p>Please recharge your wallet to avoid interrupted messaging.</p>
