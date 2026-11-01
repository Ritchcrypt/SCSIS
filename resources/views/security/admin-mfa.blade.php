<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Account authentication security — TabangNow</title>
  <style>
    body{font:16px system-ui,Arial,sans-serif;background:#f4f7fb;color:#132136;margin:0;padding:28px 16px}
    main{max-width:670px;margin:auto;background:white;border:1px solid #d8e0ea;border-radius:14px;padding:24px}
    h1{font-size:1.6rem}h2{font-size:1.2rem}p,li{line-height:1.5}
    input{display:block;max-width:100%;width:330px;margin:8px 0 12px;padding:11px;border:1px solid #8e9bac;border-radius:7px}
    button{background:#1556ad;color:white;padding:10px 18px;border:0;border-radius:7px;cursor:pointer}
    .secret{font-family:ui-monospace,monospace;overflow-wrap:anywhere;padding:12px;background:#f2f5f9;border:1px solid #d5dfe9;border-radius:8px}
    .alert{padding:12px;background:#fff4d6;border-radius:8px}.success{padding:12px;background:#e8f7ec;border-radius:8px}
    .codes{font-family:monospace;columns:2}a{color:#1556ad}section{margin:20px 0}
  </style>
</head>
<body>
<main>
  <p><a href="{{ route('profile.edit') }}">Back to Profile</a></p>
  <h1>Account security</h1>
  <p>Protect your TabangNow account with an authenticator app. Available to Admin, Official, Tanod and Resident users. Once enabled, the website and mobile app will both request a new 6-digit code when you sign in.</p>
  @if(session('status'))<p class="success">{{ session('status') }}</p>@endif
  @if($errors->any())<div class="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  @if(! $enabled)
    @if($secret)
      <section><h2>Set up your authenticator app</h2>
        <p>In Google Authenticator, Microsoft Authenticator, or another TOTP app, choose <strong>Enter a setup key</strong>. Enter the account name "TabangNow" and this key:</p>
        <p class="secret">{{ $secret }}</p>
        <p>Choose <strong>Time based</strong>, then enter the six-digit code displayed by the app. Setup expires after 10 minutes.</p>
        <form action="{{ route('security.account-mfa.confirm') }}" method="POST">
          @csrf
          <label for="code">Authenticator code</label>
          <input id="code" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
          <button type="submit">Enable authenticator</button>
        </form>
      </section>
    @else
      <section><h2>Enable authenticator protection</h2>
        <form action="{{ route('security.account-mfa.begin') }}" method="POST">
          @csrf
          <label for="password">Confirm your account password</label>
          <input id="password" type="password" name="password" autocomplete="current-password" required>
          <button type="submit">Start setup</button>
        </form>
      </section>
    @endif
  @else
    <p class="success">Authenticator enabled for this account.</p>
    <p>Unused recovery codes remaining: {{ $remaining }}</p>
    @if(is_array($recovery) && count($recovery))
      <div class="alert"><h2>Save your recovery codes</h2>
        <p>These recovery codes are shown only once. Store them securely offline. Each can be used one time if your authenticator phone is unavailable.</p>
        <ul class="codes">@foreach($recovery as $code)<li>{{ $code }}</li>@endforeach</ul>
      </div>
    @endif
    <section><h2>Generate new recovery codes</h2>
      <p>Replaces all previous recovery codes. Confirm your password and an authenticator code or unused recovery code. New codes are shown once.</p>
      <form action="{{ route('security.account-mfa.recovery') }}" method="POST">
        @csrf
        <label for="recovery-password">Account password</label>
        <input id="recovery-password" type="password" name="password" autocomplete="current-password" required>
        <label for="recovery-code">Authenticator or recovery code</label>
        <input id="recovery-code" name="code" autocomplete="one-time-code" required>
        <button type="submit">Generate new recovery codes</button>
      </form>
    </section>
    <section><h2>Disable authenticator</h2>
      <p>This lowers your account security. Confirm your password and an authenticator code or unused recovery code.</p>
      <form action="{{ route('security.account-mfa.disable') }}" method="POST">
        @csrf
        <label for="password">Account password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <label for="code">Authenticator or recovery code</label>
        <input id="code" name="code" autocomplete="one-time-code" required>
        <button type="submit">Disable authenticator</button>
      </form>
    </section>
  @endif
  <p><small>Lost both authenticator and recovery codes? Contact an authorized system administrator for a documented identity-verification recovery procedure. Do not send passwords or codes over chat.</small></p>
</main>
</body>
</html>
