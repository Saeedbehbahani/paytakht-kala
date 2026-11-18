@extends('layouts.app')
@section('title', 'هزینه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">ثبت سریع</span><h1>هزینه</h1></div></div>
<div class="segmented"><button class="active" type="button" data-tab-target="expense-new">هزینه جدید</button><button type="button" data-tab-target="expense-history">تاریخچه هزینه‌ها</button></div>
<section data-tab-panel="expense-new">
<form class="wizard" method="POST" action="{{ route('expenses.store') }}" data-wizard data-expense-wizard novalidate>@csrf
<input type="hidden" name="request_uuid" value="{{ old('request_uuid', (string) Str::uuid()) }}"><input type="hidden" name="business_at" value="">
<header class="wizard-progress three"><span class="active">مبلغ</span><span>عنوان</span><span>تأیید</span></header>
<section class="wizard-step active" data-step="0"><div class="step-heading"><span>مرحله ۱ از ۳</span><h2>مبلغ هزینه چقدر بود؟</h2></div><div class="field"><label>مبلغ</label><div class="money-input prominent"><input class="input" name="amount_toman" value="{{ old('amount_toman') }}" type="text" inputmode="numeric" data-money required autofocus><span>تومان</span></div><small data-money-preview></small></div></section>
<section class="wizard-step" data-step="1" hidden><div class="step-heading"><span>مرحله ۲ از ۳</span><h2>برای چه چیزی؟</h2></div><div class="field"><label>عنوان یا دسته</label><input class="input" name="title" value="{{ old('title') }}" list="expense-titles" placeholder="مثلاً اجاره یا بسته‌بندی" required><datalist id="expense-titles">@foreach($categories as $category)<option value="{{ $category->name }}">@endforeach</datalist></div><details class="optional-note"><summary>افزودن توضیح</summary><textarea class="input" name="note">{{ old('note') }}</textarea></details></section>
<section class="wizard-step" data-step="2" hidden><div class="step-heading"><span>مرحله ۳ از ۳</span><h2>بررسی و ثبت</h2></div><div class="receipt-preview"><div><span>مبلغ</span><strong data-review-expense-amount>—</strong></div><div><span>عنوان</span><strong data-review-expense-title>—</strong></div></div></section>
<footer class="wizard-actions"><button class="btn btn-quiet" type="button" data-wizard-back hidden>بازگشت</button><button class="btn btn-primary" type="button" data-wizard-next>ادامه</button><button class="btn btn-primary" type="submit" data-wizard-submit hidden>ثبت هزینه</button></footer>
</form></section>
<section data-tab-panel="expense-history" hidden>
<form class="filter-bar" method="GET"><button name="period" value="today">امروز</button><button name="period" value="week">این هفته</button><button name="period" value="month">این ماه</button></form>
<div class="history-list">@forelse($expenses as $expense)<article class="history-card"><div><strong>{{ $expense->category_name }}</strong><small>{{ $expense->user_name ?: 'کاربر' }} · <time>{{ \Illuminate\Support\Carbon::parse($expense->business_at ?? $expense->created_at)->format('Y/m/d H:i') }}</time></small>@if($expense->note)<p>{{ $expense->note }}</p>@endif</div><span class="money-value"><x-money :value="$expense->amount_toman" /></span></article>@empty<div class="empty-state"><x-icon name="receipt"/><strong>هنوز هزینه‌ای ثبت نشده</strong></div>@endforelse</div>
</section>
@endsection
