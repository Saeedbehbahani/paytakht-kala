@extends('layouts.app')
@section('title','تنظیمات فروشگاه | پایتخت کالا')
@section('content')
<div class="hero"><div><span class="eyebrow">فقط مدیر فروشگاه</span><h1>تنظیمات فروشگاه</h1><span class="muted">مشخصات ثابت، سیاست فروش و دسته‌بندی‌ها</span></div></div>

<form class="stack" method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    <details class="form-card" open>
        <summary><strong>۱. مشخصات فروشگاه</strong></summary>
        <div class="advanced-body">
            <div class="row"><div class="field"><label>نام فروشگاه</label><input class="input" name="store_name" value="{{ $storeName }}" required></div><div class="field"><label>شماره تماس</label><input class="input" name="store_phone" value="{{ $storePhone }}"></div></div>
            <div class="field"><label>اطلاعات روی فاکتور</label><textarea class="input" name="invoice_info" placeholder="آدرس، پیام تشکر یا قوانین مرجوعی">{{ $invoiceInfo }}</textarea></div>
            <div class="row"><div class="field"><label>حد هشدار پیش‌فرض کالای جدید</label><input class="input" type="number" min="0" name="default_minimum_stock" value="{{ $defaultMinimumStock }}" required></div><div class="field"><label>تلورانس اختلاف بستن روز (تومان)</label><input class="input" type="number" min="0" name="closing_tolerance_toman" value="{{ $closingTolerance }}" required><small>اختلاف داخل این محدوده نیاز به بررسی ایجاد نمی‌کند.</small></div></div>
        </div>
    </details>
    <details class="form-card">
        <summary><strong>۲. سیاست فروش کارکنان</strong></summary>
        <div class="advanced-body">
            @if($employeeMappingWarnings->isNotEmpty())
                <div class="notice notice-warning compact" role="alert"><strong>تنظیم حساب کارکنان کامل نیست</strong>@foreach($employeeMappingWarnings as $warning)<span>{{ $warning }}</span>@endforeach</div>
            @endif
            <div class="field"><label>صندوق پیش‌فرض کارکنان</label><select class="input" name="employee_cash_account_id" required><option value="">انتخاب صندوق ثابت</option>@foreach($employeeCashAccounts as $account)<option value="{{ $account->id }}" @selected((string)$employeeCashAccountId===(string)$account->id)>{{ $account->name }}</option>@endforeach</select><small>تمام عملیات نقدی کارکنان در این صندوق ثبت می‌شود.</small></div>
            <div class="field"><label>کارتخوان پیش‌فرض کارکنان</label><select class="input" name="employee_pos_account_id" required><option value="">انتخاب کارتخوان ثابت</option>@foreach($employeePosAccounts as $account)<option value="{{ $account->id }}" @selected((string)$employeePosAccountId===(string)$account->id)>{{ $account->name }}</option>@endforeach</select><small>تمام فروش‌های کارتخوان کارکنان در این حساب ثبت می‌شود.</small></div>
            <div class="field"><label>حساب کارت به کارت / انتقال کارکنان</label><select class="input" name="employee_card_transfer_account_id" required><option value="">انتخاب حساب بانکی ثابت</option>@foreach($employeeCardTransferAccounts as $account)<option value="{{ $account->id }}" @selected((string)$employeeCardTransferAccountId===(string)$account->id)>{{ $account->name }}</option>@endforeach</select><small>تمام دریافت‌ها و پرداخت‌های انتقالی کارکنان در این حساب ثبت می‌شود.</small></div>
            <div class="field"><label>بیشترین تخفیف مجاز کارمند (درصد)</label><input class="input" type="number" step="0.1" min="0" max="100" name="employee_max_discount_percent" value="{{ $maxDiscount }}" required><small>تخفیف بیشتر ثبت نمی‌شود و مدیر باید فروش را انجام دهد.</small></div>
        </div>
    </details>
    <div class="page-primary-action"><button class="btn btn-primary btn-block" type="submit">ذخیره مشخصات و سیاست فروش</button></div>
</form>

<div class="section-title"><div><h2>۳. دسته‌بندی کالاها</h2><span class="section-kicker">منبع واقعی فرم‌های کالا</span></div></div>
<details class="card" open><summary><strong>＋ دسته کالای جدید</strong></summary><form class="advanced-body" method="POST" action="{{ route('admin.settings.product-categories.store') }}">@csrf<div class="field"><label>نام دسته</label><input class="input" name="name" required maxlength="120"></div><button class="btn btn-primary btn-block" type="submit">ایجاد دسته کالا</button></form></details>
<div class="list">
@foreach($productCategories as $category)
    <form class="card advanced-body" method="POST" action="{{ route('admin.settings.product-categories.update',$category->id) }}">@csrf @method('PATCH')
        <div class="field"><label>نام دسته</label><input class="input" name="name" value="{{ $category->name }}" required maxlength="120"></div>
        <label class="check-row"><input type="checkbox" name="active" value="1" @checked($category->active)> فعال در فرم‌های جدید</label>
        <small class="muted">{{ $category->usage_count }} کالا از این دسته استفاده می‌کند. سوابق هرگز حذف نمی‌شوند.</small>
        <button class="btn btn-secondary btn-block" type="submit">ذخیره دسته کالا</button>
    </form>
@endforeach
</div>

<div class="section-title"><div><h2>۴. دسته‌بندی هزینه‌ها</h2><span class="section-kicker">منبع واقعی فرم ثبت هزینه</span></div></div>
<details class="card"><summary><strong>＋ دسته هزینه جدید</strong></summary><form class="advanced-body" method="POST" action="{{ route('admin.settings.expense-categories.store') }}">@csrf<div class="field"><label>نام دسته</label><input class="input" name="name" required maxlength="120"></div><button class="btn btn-primary btn-block" type="submit">ایجاد دسته هزینه</button></form></details>
<div class="list">
@foreach($expenseCategories as $category)
    <form class="card advanced-body" method="POST" action="{{ route('admin.settings.expense-categories.update',$category->id) }}">@csrf @method('PATCH')
        <div class="field"><label>نام دسته</label><input class="input" name="name" value="{{ $category->name }}" required maxlength="120"></div>
        <label class="check-row"><input type="checkbox" name="active" value="1" @checked($category->active)> فعال در فرم ثبت هزینه</label>
        <small class="muted">{{ $category->usage_count }} هزینه از این دسته استفاده می‌کند. سوابق هرگز حذف نمی‌شوند.</small>
        <button class="btn btn-secondary btn-block" type="submit">ذخیره دسته هزینه</button>
    </form>
@endforeach
</div>
@endsection
