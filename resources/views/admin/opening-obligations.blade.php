@extends('layouts.app')
@section('title','تعهدات افتتاحیه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">شروع کار واقعی</span><h1>تعهدات افتتاحیه</h1><span class="muted">طلب، بدهی، اقساط و چک‌های قبلی بدون ساخت فروش یا خرید جعلی</span></div></div>
<div class="notice notice-info"><strong>این بخش فقط مربوط به روز صفر است.</strong><span>اگر قبل از شروع پایتخت کالا طلب مشتری، بدهی تأمین‌کننده، قسط یا چک باز دارید، آن‌ها را اینجا وارد کنید. اگر موردی ندارید این مرحله را رد کنید. هیچ فروش یا خرید صوری ساخته نمی‌شود و پس از نهایی‌سازی، تاریخچه حفظ خواهد شد.</span></div>

@if(!$batch)
<form class="form-card" method="POST" action="{{ route('admin.opening-obligations.create') }}">@csrf<input type="hidden" name="request_uuid" value="{{ (string) Str::uuid() }}"><div class="field"><label>یادداشت بسته</label><textarea class="input" name="note"></textarea></div><button class="btn btn-primary btn-block" type="submit">ایجاد پیش‌نویس افتتاحیه</button></form>
@else
<section class="card"><div class="section-title"><div><h2>پیش‌نویس #{{ $batch->id }}</h2><span class="section-kicker">{{ $items->count() }} ردیف</span></div><x-status :value="$batch->status" /></div>
<form class="advanced-body" method="POST" action="{{ route('admin.opening-obligations.items',$batch->id) }}">@csrf
<div class="field"><label>نوع تعهد</label><select class="input" name="obligation_type" data-obligation-type><option value="RECEIVABLE">طلب افتتاحیه</option><option value="PAYABLE">بدهی افتتاحیه</option><option value="INCOMING_CHEQUE">چک ورودی افتتاحیه</option><option value="OUTGOING_CHEQUE">چک خروجی افتتاحیه</option></select></div>
<div class="row"><div class="field"><label>مشتری</label><select class="input" name="customer_id"><option value="">انتخاب مشتری</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div><div class="field"><label>تأمین‌کننده</label><select class="input" name="supplier_id"><option value="">انتخاب تأمین‌کننده</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div></div>
<div class="row"><div class="field"><label>مبلغ (تومان)</label><input class="input" type="number" name="amount_toman" min="1" required></div><div class="field"><label>سررسید</label><input class="input" type="date" name="due_date"></div></div>
<div class="field"><label>تعداد اقساط طلب (اختیاری)</label><input class="input" type="number" name="installment_count" min="1" max="120"></div>
<div class="row"><div class="field"><label>نام بانک چک</label><input class="input" name="bank_name"></div><div class="field"><label>شماره چک</label><input class="input" name="cheque_number"></div></div>
<div class="field"><label>یادداشت</label><textarea class="input" name="note"></textarea></div><button class="btn btn-soft btn-block" type="submit">افزودن به پیش‌نویس</button></form>
<div class="list nested-list">@foreach($items as $item)<div class="list-item"><span><strong><x-presentation-label type="reference" :value="$item->obligation_type" /></strong><small class="muted">سررسید {{ $item->due_date ?: 'ثبت نشده' }}</small></span><x-money :value="$item->amount_toman" /></div>@endforeach</div>
<form method="POST" action="{{ route('admin.opening-obligations.finalize',$batch->id) }}" data-confirm="این تعهدات به‌صورت قطعی ثبت شوند؟">@csrf<button class="btn btn-primary btn-block" type="submit">نهایی‌سازی تعهدات افتتاحیه</button></form>
</section>
@endif

<div class="section-title"><h2>بسته‌های قبلی</h2></div><div class="list">@foreach($batches as $item)<div class="list-item"><span><strong>بسته #{{ $item->id }}</strong><small class="muted">{{ $item->created_at }}</small></span><x-status :value="$item->status" /></div>@endforeach</div>
@endsection
