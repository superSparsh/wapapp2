<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Message Alert</title>
</head>
<body style="font-family:'Poppins','Open Sans','Helvetica Neue',Arial,Helvetica,Verdana,sans-serif;background-color:#f4f4f4;padding:20px;text-align:center;">
@php
    $fromLabel = ($contact_name ?? null) ?: ($from_phone ?? 'Unknown');
    $inboxUrl = url('/inbox');
@endphp
    <div class="email-container" style="max-width:600px;background-color:#ffffff;padding:20px;margin:auto;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <div class="header" style="background-color:#00838F;color:white;padding:10px;border-radius:10px 10px 0 0;font-size:18px;font-weight:bold;">
            New Message Alert – WAPAPP
        </div>

        <p>Hi,</p>

        <p>You have received a new message from <strong>{{ $fromLabel }}</strong>
            @if (! empty($from_phone) && $fromLabel !== $from_phone)
                (<strong>{{ $from_phone }}</strong>)
            @endif
            on <strong>WAPAPP (WhatsApp Automation Platform)</strong>.
        </p>

        @if (! empty($received_at))
            <p style="font-size:13px;color:#666;">Received: {{ $received_at }}</p>
        @endif

        <div class="message-preview" style="font-style:italic;color:#333;background-color:#f8f9fa;padding:10px;border-left:4px solid #0077b6;margin:15px 0;text-align:left;">
            <strong>Message Preview:</strong><br>
            "{{ $preview ?? '' }}"
        </div>

        <p>This may require your attention. Click below to view and reply instantly.</p>

        <a href="{{ $inboxUrl }}" class="cta-button" style="display:inline-block;padding:12px 25px;background-color:#00838F;color:white;text-decoration:none;font-weight:bold;border-radius:5px;margin-top:20px;">
            View Message
        </a>

        <p class="footer" style="font-size:12px;color:#666;margin-top:20px;">
            Stay connected with <strong>WAPAPP</strong> for seamless messaging automation!<br>
            If you have any questions, contact our support team.
        </p>
    </div>
</body>
</html>
