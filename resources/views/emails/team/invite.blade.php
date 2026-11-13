<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team invitation – WapApp</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.08);">
          <tr>
            <td style="background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);padding:28px 32px;color:#ffffff;">
              <p style="margin:0 0 6px;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.85;">WapApp</p>
              <h1 style="margin:0;font-size:24px;line-height:1.3;font-weight:700;">You're invited to the team</h1>
              <p style="margin:10px 0 0;font-size:15px;line-height:1.5;opacity:0.95;">
                {{ $ownerName }} added you to <strong>{{ $businessName }}</strong>
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                Hi {{ $memberName }},
              </p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">
                Your team account is ready. Set a password to activate access, then sign in with the email below.
              </p>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;">
                <tr>
                  <td style="padding:16px 18px;">
                    <p style="margin:0 0 8px;font-size:12px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:#6b7280;">Login details</p>
                    <p style="margin:0 0 6px;font-size:14px;color:#111827;"><strong>Email:</strong> {{ $memberEmail }}</p>
                    <p style="margin:0;font-size:14px;color:#111827;"><strong>Role:</strong> {{ $roleLabel }}</p>
                  </td>
                </tr>
              </table>

              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
                <tr>
                  <td style="border-radius:10px;background-color:#16a34a;">
                    <a href="{{ $setPasswordUrl }}" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                      Set password &amp; get started
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 18px;font-size:13px;line-height:1.5;color:#6b7280;">
                This link expires in {{ config('auth.passwords.team_members.expire', 60) }} minutes.
                After setting your password you can sign in anytime at
                <a href="{{ $loginUrl }}" style="color:#15803d;text-decoration:underline;">{{ $loginUrl }}</a>.
              </p>

              <p style="margin:0;font-size:13px;line-height:1.5;color:#9ca3af;">
                If the button does not work, copy and paste this link into your browser:<br>
                <span style="word-break:break-all;color:#4b5563;">{{ $setPasswordUrl }}</span>
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 32px 28px;border-top:1px solid #f3f4f6;">
              <p style="margin:0;font-size:12px;line-height:1.5;color:#9ca3af;text-align:center;">
                You received this email because you were added as a team member on WapApp.
                If you were not expecting this, you can ignore it.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
