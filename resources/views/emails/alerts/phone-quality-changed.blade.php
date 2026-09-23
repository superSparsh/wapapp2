<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Business Account Status Update</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px 0;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: #075E54;
            padding: 40px 30px;
            text-align: center;
        }

        .header img {
            height: 50px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: white;
        }
        
        .header p {
            font-size: 15px;
            margin: 0;
            color: rgba(255, 255, 255, 0.9);
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 16px;
            color: #333;
            margin-bottom: 30px;
        }

        .section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #075E54;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }

        .status-box {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #075E54;
        }
        
        .status-box .phone-number {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 15px 0;
        }

        .status-change-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .status-change-item:last-child {
            border-bottom: none;
        }

        .status-change-item .label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .status-change-item .value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            text-align: right;
        }
        
        .status-change-item .value .arrow {
            color: #28a745;
            margin: 0 5px;
        }
        
        .status-change-item .value .old {
             text-decoration: line-through;
             color: #dc3545;
             margin-right: 5px;
        }

        .footer {
            background: #f8f9fa;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .footer p {
            font-size: 14px;
            color: #666;
            margin: 0 0 12px 0;
        }

        .footer p:last-child {
            margin-bottom: 0;
        }

        .button {
            background: #25d366;
            color: white !important;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background: #128C7E;
            color: white !important;
        }

        .link {
            color: #075E54;
            text-decoration: none;
            font-weight: 500;
        }

        .link:hover {
            text-decoration: underline;
        }
        
        .company-name {
            font-weight: 600;
            color: #075E54;
        }

    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/tittu-logo.jpeg') }}" alt="{{ config('app.name', 'WAPAPP') }}">
            <h1>WhatsApp Business Account</h1>
            <p>Status Update Notification</p>
        </div>

        <div class="content">
            <div class="greeting">
                Dear Customer,
            </div>
            
            <p>This is a notification regarding a change in the status of one of your WhatsApp Business Account phone numbers. Please review the details below.</p>

            <div class="section">
                <h2 class="section-title">Update Details</h2>
                
                <div class="status-box">
                    <div class="phone-number">Phone Number: {{ $phone }}@if(!empty($display_name)) ({{ $display_name }})@endif</div>
                    
                    <div class="status-change-item">
                        <span class="label">Quality Rating</span>
                        <span class="value">
                            @if($old_quality != $new_quality)
                                <span class="old">{{ $old_quality ?? 'N/A' }}</span>
                                <span class="arrow">&rarr;</span>
                            @endif
                            {{ $new_quality ?? 'N/A' }}
                        </span>
                    </div>

                    <div class="status-change-item">
                        <span class="label">Messaging Limit Tier</span>
                        <span class="value">
                             @if($old_tier != $new_tier)
                                <span class="old">{{ $old_tier ?? 'N/A' }}</span>
                                <span class="arrow">&rarr;</span>
                            @endif
                            {{ $new_tier ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
            
            <p>These statuses are managed by Meta and can affect your messaging capabilities. A 'GREEN' quality rating is ideal. If your rating has dropped, please review your messaging to ensure it complies with WhatsApp's policies.</p>

        </div>

        <div class="footer">
            <p><strong>Need Help?</strong> <a href="https://wap.tittu.in" class="button">Contact Support</a></p>
            <p>For more information on quality ratings, please visit the <a href="https://developers.facebook.com/docs/whatsapp/messaging-limits" target="_blank" class="link">Meta for Developers documentation</a>.</p>
            <p>Team <span class="company-name">Tekkonnectpro IT Services</span></p>
        </div>
    </div>
</body>

</html>
