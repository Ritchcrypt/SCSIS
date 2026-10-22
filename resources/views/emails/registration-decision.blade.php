<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>TabangNow Registration Decision</title></head>
<body style="font-family:Arial,sans-serif;color:#17305b;line-height:1.65;max-width:620px;margin:auto;padding:24px;">
    <h2>TabangNow — Dao, Capiz</h2>
    <p>Hello {{ $recipientName }},</p>
    @if ($decision === 'approved')
        <p><strong>Your resident account registration has been approved.</strong></p>
        <p>Your TabangNow account is now active. You may sign in using your registered email and password.</p>
    @else
        <p><strong>Your resident account registration has been declined.</strong></p>
        <p>Your account was not activated. You cannot sign in at this time. Please contact the barangay office if you need assistance.</p>
    @endif
    <p>Regards,<br>TabangNow Administration</p>
    <p style="font-size:12px;color:#64748b;">This is an automated notification. Do not share your password.</p>
</body>
</html>
