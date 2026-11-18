@extends('layouts.app')
@section('title','راه‌اندازی اولیه فروشگاه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">فقط مدیر فروشگاه</span><h1>راه‌اندازی اولیه فروشگاه</h1><span class="muted">این بخش فقط برای شروع استفاده واقعی از پایتخت کالا است.</span></div></div>

@if($setupComplete)
    <div class="notice notice-success"><strong>راه‌اندازی اولیه تکمیل شده است.</strong><span>این صفحه فقط برای مشاهده وضعیت و دسترسی به تاریخچه باقی مانده؛ عملیات روزمره از بخش‌های اصلی انجام می‌شوند.</span></div>
@else
    <div class="notice notice-info"><strong>هر مرحله را فقط با اطلاعات واقعی فروشگاه تکمیل کنید.</strong><span>این صفحه Wizard نیست و داده جدیدی نمی‌سازد؛ فقط شما را به Flowهای موجود هدایت می‌کند.</span></div>
@endif

<div class="list">
@foreach($steps as $index => $step)
    <a class="list-item" href="{{ route($step['route']) }}">
        <span><strong>{{ $index + 1 }}. {{ $step['label'] }}</strong>@if($step['optional'] ?? false)<small class="muted">اختیاری؛ فقط اگر مانده بازی از قبل وجود دارد</small>@endif</span>
        <span class="status {{ $step['complete'] ? 'status-success' : (($step['optional'] ?? false) ? 'status-info' : 'status-warning') }}">{{ $step['complete'] ? 'تکمیل' : (($step['optional'] ?? false) ? 'در صورت نیاز' : 'نیازمند تکمیل') }}</span>
    </a>
@endforeach
</div>

<div class="notice notice-info compact"><strong>بازبینی نهایی</strong><span>نهایی‌سازی موجودی افتتاحیه نشانه پایان Setup است. تعهدات افتتاحیه در صورت وجود باید پیش از شروع عملیات روزانه ثبت شوند؛ تاریخچه هیچ مرحله‌ای حذف نمی‌شود.</span></div>
@endsection
