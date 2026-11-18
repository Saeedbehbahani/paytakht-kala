<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#111315">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/pk-icon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/simplified.css">
    <title>ورود | پایتخت کالا</title>
</head>
<body>
<main class="login-page">
    <form class="login-card" method="POST" action="/login">
        @csrf
        <div class="login-logo" aria-hidden="true">پ</div>
        <h1>پایتخت کالا</h1>
        <p>فروش، ورود کالا، موجودی و هزینه در یک‌جا</p>
        @if($errors->any())<div class="notice notice-danger" role="alert"><strong>ورود انجام نشد</strong><span>{{ $errors->first() }}</span></div>@endif
        <div class="field"><label for="email">ایمیل</label><input class="input" id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></div>
        <div class="field"><label for="password">رمز عبور</label><input class="input" id="password" name="password" type="password" autocomplete="current-password" required></div>
        <label class="field remember-row"><input type="checkbox" name="remember" value="1"><span>مرا به خاطر بسپار</span></label>
        <button class="btn btn-primary btn-block" type="submit">ورود امن</button>
        <p class="muted login-helper">با حساب شخصی خود وارد شوید.</p>
    </form>
</main>
</body>
</html>
