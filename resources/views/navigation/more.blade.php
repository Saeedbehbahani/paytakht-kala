@extends('layouts.app')
@section('title','بیشتر | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">دسترسی‌های تکمیلی</span><h1>بیشتر</h1><span class="muted">کارهای کم‌تکرار، مرتب و در دسترس</span></div></div>
@if(auth()->user()->isAdmin())
    <div class="section-title"><h2>کنترل</h2></div>
    <div class="menu-grid">
        <a class="menu-card" href="{{ route('admin.exceptions.index',['status'=>'OPEN']) }}"><span class="action-icon"><x-icon name="receipt"/></span><span><strong>موارد نیازمند بررسی</strong><small>اختلاف‌ها و رفتارهای غیرعادی</small></span></a>
    </div>
    <div class="section-title"><h2>افراد و طرف‌حساب‌ها</h2></div>
    <div class="menu-grid">
        <a class="menu-card" href="{{ route('admin.employees.index') }}"><span class="action-icon"><x-icon name="users"/></span><span><strong>کارکنان</strong><small>دسترسی، رمز و عملکرد</small></span></a>
        <a class="menu-card" href="{{ route('parties.index') }}"><span class="action-icon"><x-icon name="contact"/></span><span><strong>مشتری‌ها و تأمین‌کننده‌ها</strong><small>مدیریت طرف‌حساب‌ها</small></span></a>
        <a class="menu-card" href="{{ route('uploads.index') }}"><span class="action-icon"><x-icon name="receipt"/></span><span><strong>اسناد خصوصی</strong><small>بارگذاری و دریافت کنترل‌شده</small></span></a>
    </div>
    <div class="section-title"><h2>مدیریت سیستم</h2></div>
    <div class="menu-grid">
        <a class="menu-card" href="{{ route('admin.settings') }}"><span class="action-icon"><x-icon name="settings"/></span><span><strong>تنظیمات فروشگاه</strong><small>مشخصات، سیاست فروش و دسته‌ها</small></span></a>
        <a class="menu-card" href="{{ route('admin.audit') }}"><span class="action-icon"><x-icon name="history"/></span><span><strong>سابقه فعالیت</strong><small>کارهای مهم و قابل پیگیری</small></span></a>
    </div>
    @unless($setupComplete)
        <div class="section-title"><h2>راه‌اندازی اولیه</h2></div>
        <div class="menu-grid">
            <a class="menu-card" href="{{ route('admin.setup') }}"><span class="action-icon"><x-icon name="clipboard-list"/></span><span><strong>راه‌اندازی اولیه فروشگاه</strong><small>فقط برای شروع استفاده واقعی</small></span></a>
        </div>
    @endunless
@else
    <div class="menu-grid">
        <a class="menu-card" href="{{ route('sales.index') }}"><span class="action-icon"><x-icon name="shopping-cart"/></span><span><strong>فروش‌های من</strong><small>فاکتورها و مرجوعی</small></span></a>
        <a class="menu-card" href="{{ route('products.index') }}"><span class="action-icon"><x-icon name="package"/></span><span><strong>کالاها</strong><small>جست‌وجوی کالای قابل فروش</small></span></a>
    </div>
@endif
@endsection
