<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Plan Expiration — Admin</title>
</head>
<body>
    <p>Plan expiration alert for a customer:</p>
    <ul>
        <li><strong>Name:</strong> {{ $user_details->first_name ?? '' }} {{ $user_details->last_name ?? '' }}</li>
        <li><strong>Email:</strong> {{ $user_details->email ?? '—' }}</li>
        <li><strong>Company:</strong> {{ $company_name ?? '—' }}</li>
        <li><strong>Tenant:</strong> {{ $tenant_id ?? '—' }}</li>
        <li><strong>Plan:</strong> {{ $plan_details->name ?? '—' }}</li>
        <li><strong>Expires:</strong> {{ \Illuminate\Support\Carbon::parse($subscription->ends_at)->format('F j, Y') }}</li>
    </ul>
</body>
</html>
