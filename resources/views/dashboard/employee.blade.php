@extends('layouts.app')
@section('title', 'خانه من | پایتخت کالا')
@section('content')
@php
    $monthProgress = $previousMonth > 0 ? min(100, (int) round(($monthSales / $previousMonth) * 100)) : ($monthSales > 0 ? 100 : 0);
    $trend = $previousMonth > 0 ? (int) round((($monthSales - $previousMonth) / $previousMonth) * 100) : null;
@endphp

<div class="hero">
    <div>
        <span class="eyebrow">سلام {{ auth()->user()->name }} 👋</span>
        <h1>امروز چه کاری داریم؟</h1>
        <span class="muted">ثبت کارها سریع است؛ محاسبات را سیستم انجام می‌دهد.</span>
    </div>
</div>

<div class="grid keep-two">
    @if($storeMoneyActive)
    <article class="card metric-card"><span class="metric-label">موجودی پول فروشگاه</span><div class="metric"><x-money :value="$storeMoneyBalance" /></div><small class="metric-support">فقط نمایش؛ ثبت‌های مالی از مسیر عملیات انجام می‌شود.</small></article>
    @endif
    <article class="card brand-card metric-card">
        <span class="metric-label">فروش امروز من</span>
        <div class="metric"><x-money :value="$todaySales" /></div>
        <small class="metric-support">{{ $todayCount ? 'تا این لحظه' : 'امروز هنوز فروشی ثبت نشده.' }}</small>
    </article>
    <article class="card metric-card">
        <div class="metric-head"><span class="metric-label">تعداد فروش امروز</span><span class="metric-icon"><x-icon name="shopping-cart"/></span></div>
        <div class="metric">{{ $todayCount }} <span class="unit">فاکتور</span></div>
        <small class="metric-support">فروش‌های نهایی‌شده شما</small>
    </article>
</div>

<div class="progress-card">
    <div class="progress-head">
        <span><strong>پیشرفت این ماه من</strong><small><x-money :value="$monthSales" /> فروش ثبت‌شده</small></span>
        @if($trend !== null)
            <span class="status {{ $trend >= 0 ? 'status-success' : 'status-info' }}">{{ $trend >= 0 ? 'رشد' : 'کمتر از ماه قبل' }} {{ abs($trend) }}٪</span>
        @else
            <span class="status status-info">شروع دوره</span>
        @endif
    </div>
    <div class="progress-track" role="progressbar" aria-label="مقایسه فروش این ماه با ماه قبل" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $monthProgress }}"><span style="--progress:{{ $monthProgress }}%"></span></div>
</div>

<a class="primary-task" href="{{ route('sales.create') }}">
    <span class="primary-task-icon"><x-icon name="shopping-cart"/></span>
    <span><strong>فروش جدید</strong><small>کالا، تعداد، پرداخت؛ تمام</small></span>
    <x-icon name="arrow-left" class="primary-task-arrow"/>
</a>

<div class="section-title"><div><h2>کارهای اصلی</h2><span class="section-kicker">دسترسی سریع برای کارهای روزانه</span></div></div>
<div class="actions">
    <a class="action" href="{{ route('products.index') }}"><span class="action-icon"><x-icon name="package"/></span><span>کالاها</span></a>
    <a class="action" href="{{ route('sales.index') }}"><span class="action-icon"><x-icon name="shopping-cart"/></span><span>فروش‌های من</span></a>
</div>

<div class="section-title"><div><h2>آخرین فروش‌های من</h2><span class="section-kicker">دسترسی سریع به ثبت‌های اخیر</span></div><a class="text-link" href="{{ route('sales.index') }}">مشاهده همه</a></div>
<div class="list">
    @forelse($recent as $sale)
        <a class="list-item" href="{{ route('sales.index') }}"><span><strong>{{ $sale->invoice_number }}</strong><small class="muted">{{ \Carbon\Carbon::parse($sale->created_at)->format('Y/m/d H:i') }}</small></span><x-money :value="$sale->total_toman" /></a>
    @empty
        <div class="empty empty-action"><strong>امروز هنوز فروشی ثبت نشده.</strong><span>اولین فروش را در چند ثانیه ثبت کنید.</span><a class="btn btn-primary" href="{{ route('sales.create') }}">شروع فروش</a></div>
    @endforelse
</div>
@endsection
