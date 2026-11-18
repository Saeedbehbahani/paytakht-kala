@extends('layouts.app')
@section('title', 'گزارش فروشگاه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">گزارش فروشگاه</span><h1>خلاصه عملکرد</h1></div><a class="btn btn-soft" href="{{ route('dashboard') }}">خانه</a></div>
<form class="form-card" method="GET" action="{{ route('reports.index') }}">
    <div class="field"><label>بازه گزارش</label><select class="input" name="period" data-period-select><option value="today" @selected($period==='today')>امروز</option><option value="week" @selected($period==='week')>این هفته</option><option value="month" @selected($period==='month')>این ماه</option><option value="range" @selected($period==='range')>بازه دلخواه</option></select></div>
    <div class="row"><div class="field"><label>از تاریخ</label><input class="input" type="date" name="from" value="{{ request('from') }}"></div><div class="field"><label>تا تاریخ</label><input class="input" type="date" name="to" value="{{ request('to') }}"></div></div>
    <button class="btn btn-secondary btn-block">نمایش گزارش</button>
</form>
<div class="grid keep-two">
    <article class="card metric-card"><span>مبلغ فروش</span><strong><x-money :value="$metrics['salesAmount']" /></strong></article>
    <article class="card metric-card"><span>سود فروش</span><strong><x-money :value="$metrics['salesProfit']" /></strong></article>
    <article class="card metric-card"><span>هزینه‌ها</span><strong><x-money :value="$metrics['expenses']" /></strong></article>
    <article class="card metric-card"><span>سود خالص</span><strong><x-money :value="$metrics['netProfit']" /></strong></article>
    <article class="card metric-card"><span>تعداد فروش</span><strong>{{ $metrics['saleCount'] }}</strong></article>
    <article class="card metric-card"><span>تعداد ورود کالا</span><strong>{{ $metrics['stockInCount'] }}</strong></article>
    <article class="card metric-card"><span>تعداد کالای واردشده</span><strong>{{ number_format($metrics['stockInQuantity']) }} عدد</strong></article>
    <article class="card metric-card"><span>ارزش ورود کالا</span><strong><x-money :value="$metrics['stockInValue']" /></strong></article>
    <article class="card metric-card"><span>کل عملیات</span><strong>{{ number_format($metrics['transactionCount']) }}</strong></article>
    <article class="card metric-card"><span>کالای ناموجود</span><strong>{{ number_format($metrics['outOfStock']) }}</strong></article>
</div>
@if($metrics['unknownProfitSales'])<p class="notice">سود {{ $metrics['unknownProfitSales'] }} فروش قدیمی، به دلیل نبود قیمت خرید ثبت‌شده در زمان فروش، در جمع سود منظور نشده است.</p>@endif
<div class="section-title"><h2>پرفروش‌ترین کالاها</h2></div><div class="list">@forelse($metrics['topSelling'] as $item)<div class="list-item"><strong>{{ $item->name }}</strong><span>{{ $item->quantity }} عدد</span></div>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
<div class="section-title"><h2>پرسودترین کالاها</h2></div><div class="list">@forelse($metrics['mostProfitable'] as $item)<div class="list-item"><strong>{{ $item->name }}</strong><x-money :value="$item->profit" /></div>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
<div class="section-title"><h2>کم‌موجود</h2></div><div class="list">@forelse($metrics['lowStock'] as $item)<a class="list-item" href="{{ route('products.show', $item->id) }}"><strong>{{ $item->name }}</strong><span>{{ $item->stock_quantity }} عدد</span></a>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
<div class="section-title"><h2>جزئیات عملیات</h2></div><div class="segmented"><button class="active" type="button" data-tab-target="report-sales">فروش</button><button type="button" data-tab-target="report-stockin">ورود</button><button type="button" data-tab-target="report-expenses">هزینه</button></div>
<div data-tab-panel="report-sales" class="history-list">@forelse($metrics['salesRows'] as $row)<article class="history-card"><strong>{{ $row->name }} · {{ $row->quantity }} عدد</strong><small>{{ $row->user_name }} · {{ $row->business_at ?? $row->created_at }}</small><span>خرید {{ number_format($row->purchase_price_snapshot_toman) }} · فروش {{ number_format($row->unit_price_toman) }} تومان</span></article>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
<div data-tab-panel="report-stockin" class="history-list" hidden>@forelse($metrics['stockInRows'] as $row)<article class="history-card"><strong>{{ $row->name }} · {{ $row->quantity }} عدد</strong><small>{{ $row->user_name }} · {{ $row->business_at ?? $row->created_at }}</small><span>هر عدد {{ number_format($row->unit_cost_toman) }} تومان</span></article>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
<div data-tab-panel="report-expenses" class="history-list" hidden>@forelse($metrics['expenseRows'] as $row)<article class="history-card"><strong>{{ $row->name }}</strong><small>{{ $row->user_name }} · {{ $row->business_at ?? $row->created_at }}</small><span>{{ number_format($row->amount_toman) }} تومان</span></article>@empty<div class="empty">موردی نیست.</div>@endforelse</div>
@endsection
