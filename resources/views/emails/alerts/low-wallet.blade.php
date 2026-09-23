<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Wallet Balance</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px 0; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #075E54; padding: 40px 30px; text-align: center; }
        .header img { height: 50px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 600; margin: 0 0 8px 0; color: white; }
        .header p { font-size: 15px; margin: 0; color: rgba(255,255,255,0.9); }
        .content { padding: 40px 30px; }
        .section-title { font-size: 18px; font-weight: 600; color: #075E54; margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1px solid #e9ecef; }
        .status-box { background: #fff8f0; border-radius: 6px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #f59e0b; }
        .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .row:last-child { border-bottom: none; }
        .label { font-size: 14px; color: #666; font-weight: 500; }
        .value { font-size: 14px; color: #333; font-weight: 600; }
        .footer { background: #f8f9fa; padding: 25px 30px; text-align: center; border-top: 1px solid #e9ecef; }
        .footer p { font-size: 14px; color: #666; margin: 0 0 12px 0; }
        .button { background: #25d366; color: white !important; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: 600; font-size: 13px; }
        .company-name { font-weight: 600; color: #075E54; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/tittu-logo.jpeg') }}" alt="{{ config('app.name', 'WAPAPP') }}">
            <h1>WAPAPP Wallet</h1>
            <p>Low Balance Alert</p>
        </div>
        <div class="content">
            <p>Dear Customer,</p>
            <p>Your WAPAPP wallet balance is running low. Please recharge soon so campaign and messaging sends are not interrupted.</p>
            <h2 class="section-title">Balance details</h2>
            <div class="status-box">
                <div class="row">
                    <span class="label">Current balance</span>
                    <span class="value">{{ number_format((float) ($balance ?? 0), 2) }} {{ $currency ?? 'INR' }}</span>
                </div>
                <div class="row">
                    <span class="label">Alert threshold</span>
                    <span class="value">{{ number_format((float) ($threshold ?? 0), 2) }} {{ $currency ?? 'INR' }}</span>
                </div>
                <div class="row">
                    <span class="label">Context</span>
                    <span class="value">{{ $context ?? 'general' }}</span>
                </div>
            </div>
            <p>You can recharge from your WAPAPP billing / wallet page. If you need help, contact support.</p>
        </div>
        <div class="footer">
            <p><strong>Need Help?</strong> <a href="https://wap.tittu.in" class="button">Contact Support</a></p>
            <p>Team <span class="company-name">Tekkonnectpro IT Services</span> · WAPAPP</p>
        </div>
    </div>
</body>
</html>
