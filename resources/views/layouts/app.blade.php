<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#f8f7f4">
    <title>@yield('title', 'پایتخت کالا')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body>
<div class="app-shell">
@auth
    <header class="topbar">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="خانه پایتخت کالا"><span class="brand-mark">پ</span><span><strong>{{ $storeProfile['name'] ?? 'پایتخت کالا' }}</strong><small>{{ auth()->user()->name }}</small></span></a>
        <details class="profile-menu"><summary aria-label="منوی کاربر"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span></summary><div class="profile-popover"><strong>{{ auth()->user()->name }}</strong><small dir="ltr">{{ auth()->user()->email }}</small><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="menu-action">خروج از حساب</button></form></div></details>
    </header>
    <div class="offline" data-offline>اینترنت قطع است؛ ثبت عملیات پس از اتصال دوباره ممکن می‌شود.</div>
@endauth
<main class="content">
    @if(session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice notice-danger" role="alert"><strong>اطلاعات نیاز به اصلاح دارد</strong><span>{{ $errors->first() }}</span></div>@endif
    @yield('content')
</main>
@auth
<nav class="bottom-nav" aria-label="ناوبری اصلی">
    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="layout-dashboard"/><span>خانه</span></a>
    <a class="{{ request()->routeIs('sales.*') ? 'active' : '' }}" href="{{ route('sales.create') }}"><x-icon name="shopping-cart"/><span>فروش</span></a>
    @if(auth()->user()->isAdmin())
        <a class="{{ request()->routeIs('purchases.*') ? 'active' : '' }}" href="{{ route('purchases.create') }}"><x-icon name="truck"/><span>ورود</span></a>
        <a class="{{ request()->routeIs('inventory.*', 'products.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}"><x-icon name="package"/><span>موجودی</span></a>
        <a class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.create') }}"><x-icon name="receipt"/><span>هزینه</span></a>
    @else
        <a class="{{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}"><x-icon name="package"/><span>کالاها</span></a>
        <a class="{{ request()->routeIs('more') ? 'active' : '' }}" href="{{ route('more') }}"><x-icon name="settings"/><span>بیشتر</span></a>
    @endif
</nav>
@endauth
</div>
<script src="{{ asset('assets/app.js') }}" defer></script>
</body>
</html>
