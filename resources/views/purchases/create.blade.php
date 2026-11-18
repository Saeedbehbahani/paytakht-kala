@extends('layouts.app')
@section('title', 'ورود کالا | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">افزایش موجودی</span><h1>ورود کالا</h1></div><a class="btn btn-quiet" href="{{ route('purchases.index') }}"><x-icon name="history"/> تاریخچه</a></div>
<form class="wizard" method="POST" action="{{ route('purchases.store') }}" data-wizard data-purchase-wizard novalidate>
@csrf
<input type="hidden" name="request_uuid" value="{{ old('request_uuid', (string) Str::uuid()) }}"><input type="hidden" name="business_at" value="">
<input type="hidden" name="items[0][product_id]" value="{{ old('items.0.product_id') }}" data-product-id>
<input type="hidden" name="new_product[quantity]" data-new-quantity><input type="hidden" name="new_product[unit_cost_toman]" data-new-cost>
<header class="wizard-progress" aria-label="مراحل ورود"><span class="active">کالا</span><span>تعداد</span><span>قیمت</span><span>تأیید</span></header>
<section class="wizard-step active" data-step="0">
    <div class="step-heading"><span>مرحله ۱ از ۴</span><h2>چه کالایی وارد شده؟</h2></div>
    <div class="search-box"><x-icon name="package"/><input class="input" type="search" autocomplete="off" placeholder="جستجوی نام، مدل، بارکد یا SKU" data-product-search data-context="purchase"></div>
    <div class="search-results" data-product-results aria-live="polite"><div class="skeleton-card"></div></div>
    <button class="new-product-trigger" type="button" data-new-product><x-icon name="plus"/> افزودن کالای جدید</button>
    <div class="new-product-fields" data-new-product-fields hidden>
        <div class="field"><label>نام کالا</label><input class="input" name="new_product[name]" value="{{ old('new_product.name') }}" data-new-product-name></div>
        <div class="row"><div class="field"><label>برند</label><input class="input" name="new_product[brand]" value="{{ old('new_product.brand') }}"></div><div class="field"><label>مدل</label><input class="input ltr" name="new_product[model]" value="{{ old('new_product.model') }}"></div></div>
        <div class="row"><div class="field"><label>بارکد</label><input class="input ltr" name="new_product[barcode]" value="{{ old('new_product.barcode') }}"></div><div class="field"><label>SKU واقعی (اختیاری)</label><input class="input ltr" name="new_product[sku]" value="{{ old('new_product.sku') }}"></div></div>
        <div class="field"><label>دسته</label><select class="input" name="new_product[category_id]"><option value="">بدون دسته</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
    </div>
</section>
<section class="wizard-step" data-step="1" hidden>
    <div class="step-heading"><span>مرحله ۲ از ۴</span><h2>چند عدد وارد شده؟</h2></div><div class="selected-product compact" data-product-summary></div>
    <div class="quantity-control"><button type="button" data-qty-minus>−</button><input name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" type="number" inputmode="numeric" min="1" required><button type="button" data-qty-plus>+</button></div>
</section>
<section class="wizard-step" data-step="2" hidden>
    <div class="step-heading"><span>مرحله ۳ از ۴</span><h2>قیمت خرید نهایی هر عدد</h2><p>همان مبلغ نهایی که کالا برای فروشگاه تمام شده است.</p></div>
    <div class="field"><label>قیمت خرید هر عدد</label><div class="money-input"><input class="input" name="items[0][unit_cost_toman]" value="{{ old('items.0.unit_cost_toman') }}" type="text" inputmode="numeric" data-money required><span>تومان</span></div><small data-money-preview></small></div>
    <div class="profit-panel neutral"><span>ارزش کل ورود <strong data-stockin-total>—</strong></span></div>
</section>
<section class="wizard-step" data-step="3" hidden>
    <div class="step-heading"><span>مرحله ۴ از ۴</span><h2>بررسی و ثبت</h2></div>
    <div class="receipt-preview"><div><span>کالا</span><strong data-review-product>—</strong></div><div><span>تعداد</span><strong data-review-quantity>—</strong></div><div><span>قیمت خرید هر عدد</span><strong data-review-cost>—</strong></div><div class="receipt-total"><span>ارزش کل ورود</span><strong data-review-total>—</strong></div></div>
    <details class="optional-note"><summary>افزودن توضیح</summary><textarea class="input" name="note">{{ old('note') }}</textarea></details>
</section>
<footer class="wizard-actions"><button class="btn btn-quiet" type="button" data-wizard-back hidden>بازگشت</button><button class="btn btn-primary" type="button" data-wizard-next>ادامه</button><button class="btn btn-primary" type="submit" data-wizard-submit hidden>ثبت ورود</button></footer>
</form>
@endsection
