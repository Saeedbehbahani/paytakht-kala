@extends('layouts.app')
@section('title', 'فعالیت کارمند | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">{{ $user->name }}</span><h1>عملکرد کارمند</h1><span class="muted">امروز: <x-money :value="$summary['today']" /> · این ماه: <x-money :value="$summary['month']" /></span></div><a class="btn btn-soft" href="{{ route('admin.employees.index') }}">کارکنان</a></div>
<div class="section-title"><div><h2>فروش‌ها</h2></div></div><div class="list">@forelse($sales as $sale)<article class="list-item"><div><strong>{{ $sale->invoice_number }}</strong><div class="product-meta">{{ $sale->created_at->format('Y/m/d H:i') }}</div></div><strong><x-money :value="$sale->total_toman" /></strong></article>@empty <div class="empty">فروشی ثبت نشده است.</div>@endforelse</div>{{ $sales->links() }}
<div class="section-title"><div><h2>سابقه فعالیت</h2></div></div><div class="list">@forelse($activity as $item)<article class="list-item"><div><strong>@if($item->summary){{ $item->summary }}@else<x-presentation-label type="audit-action" :value="$item->action"/>@endif</strong><div class="product-meta">{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('Y/m/d H:i') }}</div></div></article>@empty <div class="empty">فعالیتی ثبت نشده است.</div>@endforelse</div>
@endsection
