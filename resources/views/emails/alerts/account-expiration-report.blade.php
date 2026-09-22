<p>Monthly account expiration report — generated {{ $generated_at }}</p>
@if (empty($rows))
  <p>No subscriptions expiring in the upcoming window.</p>
@else
  <table border="1" cellpadding="6" cellspacing="0">
    <thead>
      <tr>
        <th>Tenant</th>
        <th>Email</th>
        <th>Plan</th>
        <th>Ends at</th>
        <th>Days left</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $row)
        <tr>
          <td>{{ $row['tenant'] ?? '—' }}</td>
          <td>{{ $row['email'] ?? '—' }}</td>
          <td>{{ $row['plan'] ?? '—' }}</td>
          <td>{{ $row['ends_at'] ?? '—' }}</td>
          <td>{{ $row['days_left'] ?? '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif
