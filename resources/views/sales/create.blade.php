@extends('layouts.app')
@section('title', 'فروش جدید | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">فروش سریع</span><h1>فروش جدید</h1></div><a class="btn btn-quiet" href="{{ route('sales.index') }}"><x-icon name="history"/> تاریخچه</a></div>
<form class="wizard" method="POST" action="{{ route('sales.store') }}" data-wizard data-sale-wizard novalidate>
@csrf
<input type="hidden" name="request_uuid" value="{{ old('request_uuid', (string) Str::uuid()) }}">
<input type="hidden" name="items[0][product_id]" value="{{ old('items.0.product_id') }}" data-product-id>
<input type="hidden" name="business_at" value="">
<header class="wizard-progress" aria-label="مراحل فروش"><span class="active">کالا</span><span>تعداد</span><span>قیمت</span><span>تأیید</span></header>

<section class="wizard-step active" data-step="0">
    <div class="step-heading"><span>مرحله ۱ از ۴</span><h2>چه کالایی می‌خواهی بفروشی؟</h2></div>
    <div class="search-box"><x-icon name="package"/><input class="input" type="search" autocomplete="off" placeholder="جستجوی نام، مدل، بارکد یا SKU" data-product-search data-context="sale" aria-label="جستجوی کالا"></div>
    <p class="helper">نام یا کد را بنویس؛ موجودی هر کالا همین‌جا دیده می‌شود.</p>
    <div class="search-results" data-product-results aria-live="polite"><div class="skeleton-card"></div><div class="skeleton-card"></div></div>
    <div class="selected-product" data-selected-product hidden></div>
</section>

<section class="wizard-step" data-step="1" hidden>
    <div class="step-heading"><span>مرحله ۲ از ۴</span><h2>چند عدد؟</h2></div>
    <div class="selected-product compact" data-product-summary></div>
    <div class="quantity-control"><button type="button" data-qty-minus aria-label="کم کردن">−</button><input id="sale-quantity" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" type="number" inputmode="numeric" min="1" required><button type="button" data-qty-plus aria-label="اضافه کردن">+</button></div>
    <p class="stock-note">موجودی فعلی: <strong data-current-stock>—</strong> عدد</p>
</section>

<section class="wizard-step" data-step="2" hidden>
    <div class="step-heading"><span>مرحله ۳ از ۴</span><h2>قیمت‌های همین فروش</h2><p>قیمت خرید را برای این تراکنش دستی وارد کن.</p></div>
    <div class="field"><label for="sale-purchase-price">قیمت خرید هر عدد</label><div class="money-input"><input class="input" id="sale-purchase-price" name="items[0][purchase_price_toman]" value="{{ old('items.0.purchase_price_toman') }}" type="text" inputmode="numeric" data-money required><span>تومان</span></div><small data-money-preview></small></div>
    <div class="field"><label for="sale-price">قیمت فروش هر عدد</label><div class="money-input"><input class="input" id="sale-price" name="items[0][unit_price_toman]" value="{{ old('items.0.unit_price_toman') }}" type="text" inputmode="numeric" data-money required><span>تومان</span></div><small data-money-preview></small></div>
    <div class="profit-panel" data-profit-panel><span>سود هر عدد <strong data-unit-profit>—</strong></span><span>سود کل <strong data-total-profit>—</strong></span></div>
</section>

<section class="wizard-step" data-step="3" hidden>
    <div class="step-heading"><span>مرحله ۴ از ۴</span><h2>بررسی و ثبت</h2><p>اطلاعات زیر دقیقاً در سابقه فروش ذخیره می‌شود.</p></div>
    <div class="receipt-preview"><div><span>کالا</span><strong data-review-product>—</strong></div><div><span>تعداد</span><strong data-review-quantity>—</strong></div><div><span>قیمت خرید هر عدد</span><strong data-review-purchase>—</strong></div><div><span>قیمت فروش هر عدد</span><strong data-review-sale>—</strong></div><div><span>سود هر عدد</span><strong data-review-unit-profit>—</strong></div><div class="receipt-total"><span>سود کل</span><strong data-review-total-profit>—</strong></div></div>
    <details class="optional-note"><summary>افزودن توضیح</summary><div class="field"><label for="sale-note">توضیح اختیاری</label><textarea class="input" id="sale-note" name="note">{{ old('note') }}</textarea></div></details>
</section>

<footer class="wizard-actions"><button class="btn btn-quiet" type="button" data-wizard-back hidden>بازگشت</button><button class="btn btn-primary" type="button" data-wizard-next>ادامه</button><button class="btn btn-primary" type="submit" data-wizard-submit hidden>ثبت فروش</button></footer>
</form>
@endsection
