<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Plan Expiration Notification</title>
</head>
<body>
    <p>
        Hello {{ $user_details->first_name ?? '' }} {{ $user_details->last_name ?? '' }}
        @if (! empty($company_name))
            ({{ $company_name }})
        @endif,
    </p>

    <p>
        We wanted to notify you that your recurring plan is expiring on
        {{ \Illuminate\Support\Carbon::parse($subscription->ends_at)->format('F j, Y') }}.
    </p>

    <p>Plan Details:</p>
    <ul>
        <li><strong>Plan Name:</strong> {{ $plan_details->name ?? '—' }}</li>
        <li><strong>Plan Price:</strong>
            {{ number_format((float) ($plan_details->price ?? $subscription->amount ?? 0), 2) }}
            {{ $plan_details->currency ?? $subscription->currency ?? 'INR' }}
        </li>
    </ul>

    <p>If you wish to renew or upgrade your plan, or if you have any questions, please contact our support team:</p>
    <ul>
        <li>
            <strong>Phone / WhatsApp:</strong>
            <a href="{{ $support_whatsapp_url }}">{{ $support_phone }}</a>
        </li>
        <li>
            <strong>Email:</strong>
            <a href="mailto:{{ $support_email }}">{{ $support_email }}</a>
        </li>
    </ul>

    <p>Thank you for choosing WhatsApp Automation Platform. We look forward to continuing to serve you!</p>

    <p>Regards,<br>WhatsApp Automation Platform</p>
</body>
</html>
