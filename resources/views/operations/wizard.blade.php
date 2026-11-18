@php
    $isSale = $kind === 'sale';
    $wizardProducts = $products->map(fn($p) => $p->only(['id','name','model','brand','sku','barcode','stock_quantity','sale_price_toman','last_replacement_cost_toman','status']));
@endphp
<div class="hero"><div><span class="eyebrow">ثبت مرحله‌به‌مرحله</span><h1>{{ $isSale ? 'فروش جدید' : 'ورود کالا' }}</h1></div></div>
<form class="guided-flow" method="POST" action="{{ route($isSale ? 'sales.store' : 'purchases.store') }}" enctype="multipart/form-data" data-wizard="{{ $kind }}" @if(!$isSale && app(\App\Services\StoreMoneyService::class)->active()) data-funding-form @endif novalidate>
    @csrf
    <input type="hidden" name="request_uuid" value="{{ old('request_uuid', (string) Str::uuid()) }}">
    <script type="application/json" data-wizard-products>@json($wizardProducts)</script>
    <script type="application/json" data-wizard-old>@json(session()->getOldInput())</script>
    <script type="application/json" data-wizard-errors>@json($errors->messages())</script>
    <div class="wizard-progress"><span data-step-label aria-live="polite"></span><progress data-step-progress max="{{ $isSale ? 5 : 6 }}" value="1" aria-label="پیشرفت ثبت"></progress></div>
    <div class="notice notice-danger" data-wizard-error role="alert" hidden></div>
    <section class="form-card wizard-panel" data-step="1">
        <h2 tabindex="-1">{{ $isSale ? 'چه کالایی فروخته شد؟' : 'چه کالایی وارد شد؟' }}</h2><p class="muted">نام، مدل، کد یا بارکد را جستجو و نتیجه را انتخاب کنید.</p>
        <div class="field"><label for="wizard-search">جستجو یا بارکد</label><input id="wizard-search" class="input" type="search" autocomplete="off" data-wizard-search></div>
        <div class="search-results wizard-results" data-wizard-results></div>
        @unless($isSale)<button type="button" class="btn btn-soft" data-draft>＋ کالای جدید</button>@endunless
        <div class="wizard-selected" data-selected-summary aria-live="polite"></div>
        <button type="button" class="btn btn-primary btn-block" data-select-next>ادامه</button>
    </section>
    <section class="form-card wizard-panel" data-step="2" hidden>
        <h2 tabindex="-1">{{ $isSale ? 'اطلاعات فروش کالا' : 'تعداد و بهای خرید' }}</h2><p class="muted">اطلاعات این کالا را بررسی و به فاکتور اضافه کنید.</p>
        <div class="wizard-selected" data-editor-summary></div>
        @unless($isSale)
        <div data-draft-fields hidden>
            <div class="field"><label for="draft-name">نام کالای جدید</label><input class="input" id="draft-name" data-draft-key="name" maxlength="150"></div>
            <div class="field"><label for="draft-category">دسته</label><select class="input" id="draft-category" data-draft-key="category_id"><option value="">انتخاب دسته</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            @foreach(['sku'=>'SKU (اختیاری)','barcode'=>'بارکد (اختیاری)','brand'=>'برند (اختیاری)','model'=>'مدل (اختیاری)'] as $key=>$label)
            <div class="field"><label for="draft-{{ $key }}">{{ $label }}</label><input class="input" id="draft-{{ $key }}" data-draft-key="{{ $key }}"></div>
            @endforeach
            <p class="muted">اگر کالا در سیستم نیست، مشخصات آن را وارد کنید.</p>
            <div class="field"><label for="draft-image">تصویر کالا (اختیاری)</label><input id="draft-image" type="file" name="new_product[image]" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        @endunless
        <div class="field"><label for="wizard-quantity">تعداد</label><input class="input" id="wizard-quantity" data-editor="quantity" type="number" inputmode="numeric" min="1" max="999999" value="1"></div>
        <div class="field"><label for="wizard-price">{{ $isSale ? 'قیمت فروش' : 'قیمت خرید هر واحد' }} (تومان)</label><input class="input" id="wizard-price" data-editor="price" type="number" inputmode="numeric" min="{{ $isSale ? 0 : 1 }}" max="999999999999">@if($isSale)<small class="field-hint">قیمت واقعی فروش این کالا در این معامله</small>@endif</div>
        @if($isSale)
        <div class="field"><label for="wizard-replacement">قیمت خرید روز</label><input class="input" id="wizard-replacement" data-editor="replacement" type="number" inputmode="numeric" min="0" max="999999999999"><small class="field-hint">قیمت خرید مجدد این کالا در حال حاضر. اگر هزینه حمل خرید مجدد مشخص است، مبلغ نهایی تا رسیدن کالا به فروشگاه وارد شود.</small></div>
        <details><summary>قیمت یا تخفیف</summary><div class="field"><label for="wizard-line-discount">تخفیف این ردیف (تومان)</label><input id="wizard-line-discount" class="input" data-editor="discount" type="number" min="0" value="0"></div></details>
        @endif
        <div class="wizard-actions"><button type="button" class="btn btn-soft" data-go="1">برگشت</button><button type="button" class="btn btn-primary" data-save-item>{{ $isSale ? 'افزودن به فاکتور' : 'افزودن به بار' }}</button></div>
    </section>
    <section class="form-card wizard-panel" data-step="3" hidden>
        <h2 tabindex="-1">{{ $isSale ? 'سبد فروش' : 'سبد ورود کالا' }}</h2><p class="muted">اقلام را مرور کنید؛ امکان ویرایش و حذف دارید.</p>
        <div data-cart></div><strong class="wizard-total" data-cart-total></strong>
        <div class="wizard-actions"><button type="button" class="btn btn-soft" data-add-item>＋ افزودن کالای دیگر</button><button type="button" class="btn btn-primary" data-cart-next>{{ $isSale ? 'ادامه به پرداخت' : 'ادامه به هزینه‌ها' }}</button></div>
    </section>
    @unless($isSale)
    <section class="form-card wizard-panel" data-step="4" hidden>
        <h2 tabindex="-1">تخفیف و کرایه بار</h2><p class="muted">هزینه حمل این خرید تا رسیدن کالا به فروشگاه در بهای همین خرید محاسبه می‌شود؛ آن را دوباره به‌عنوان هزینه ثبت نکنید.</p>
        <div class="field"><label for="purchase-discount">تخفیف کل خرید</label><input id="purchase-discount" class="input" name="discount_toman" type="number" min="0" value="{{ old('discount_toman',0) }}"></div>
        <div class="field"><label for="purchase-freight">کرایه بار</label><input id="purchase-freight" class="input" name="shipping_cost_toman" type="number" min="0" value="{{ old('shipping_cost_toman',0) }}"></div>
        <div class="wizard-actions"><button type="button" class="btn btn-soft" data-go="3">برگشت</button><button type="button" class="btn btn-primary" data-cost-next>ادامه به پرداخت</button></div>
    </section>
    @endunless
    <section class="form-card wizard-panel" data-step="{{ $isSale ? 4 : 5 }}" hidden>
        <h2 tabindex="-1">{{ $isSale ? 'روش دریافت' : 'پرداخت خرید' }}</h2><p class="muted">روش و مبلغ را بررسی کنید.</p><strong data-payment-total></strong>
        @if(!$isSale && app(\App\Services\StoreMoneyService::class)->active())
        <fieldset class="field"><legend>پول خرید از کجا پرداخت شد؟</legend><div class="payment-method-choices">@foreach(['STORE'=>'از پول فروشگاه','PERSONAL'=>'از حساب شخصی من','SUPPLIER_CREDIT'=>'نسیه / هنوز پرداخت نشده'] as $value=>$label)<label><input type="radio" name="funding_source" value="{{ $value }}" @checked(old('funding_source','STORE')===$value)><span>{{ $label }}</span></label>@endforeach</div></fieldset>
        <div class="field" data-funding-method><label>روش پرداخت</label><select class="input" name="payment_method"><option value="TRANSFER">انتقال</option><option value="CASH">نقدی</option><option value="CARD">کارت</option><option value="OTHER">سایر</option></select></div><p class="muted" data-funding-person hidden>پرداخت‌کننده: شما</p>
        @else
        <fieldset class="field payment-method-field"><legend>{{ $isSale ? 'روش دریافت' : 'روش پرداخت' }}</legend><div class="payment-method-choices">
            @foreach(($isSale ? ['CASH'=>'نقدی','POS'=>'کارتخوان','CARD_TRANSFER'=>'کارت به کارت / انتقال'] : [''=>'نسیه / بعداً پرداخت می‌شود','CASH'=>'نقدی','CARD_TRANSFER'=>'کارت به کارت / انتقال']) as $value=>$label)
            <label><input type="radio" name="simple_payment_method" value="{{ $value }}" @checked(old('simple_payment_method',$isSale?'POS':'')===$value)><span>{{ $label }}</span></label>
            @endforeach
        </div></fieldset>
        @endif
        <div class="field"><label for="wizard-payment">{{ $isSale ? 'مبلغ نهایی' : 'مبلغ پرداخت‌شده' }} (تومان)</label><input id="wizard-payment" class="input" name="payments[0][amount_toman]" type="number" min="1" value="{{ old('payments.0.amount_toman') }}" @readonly($isSale) @if(!$isSale) data-funding-amount @endif></div>
        @if($isSale)
        <details><summary>تخفیف فاکتور</summary><div class="field"><label for="invoice-discount">تخفیف فاکتور</label><input id="invoice-discount" class="input" name="discount_toman" type="number" min="0" value="{{ old('discount_toman',0) }}"></div><div class="field"><label for="sensitive-reason">دلیل تخفیف</label><textarea id="sensitive-reason" class="input" name="sensitive_reason">{{ old('sensitive_reason') }}</textarea></div></details>
        @else
        <div class="field"><label for="wizard-supplier">تأمین‌کننده</label><select id="wizard-supplier" class="input" name="supplier_id" @if(app(\App\Services\StoreMoneyService::class)->active()) data-funding-supplier @endif><option value="">انتخاب تأمین‌کننده</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>@endforeach</select><small>اگر بدهی باقی می‌ماند، تأمین‌کننده الزامی است.</small></div>
        <details><summary>فاکتور و توضیحات</summary><div class="field"><label for="wizard-invoice">شماره فاکتور</label><input id="wizard-invoice" class="input" name="invoice_number" value="{{ old('invoice_number') }}"></div><div class="field"><label for="wizard-file">تصویر یا PDF فاکتور</label><input id="wizard-file" type="file" name="invoice_image" accept="image/jpeg,image/png,image/webp,application/pdf"></div><div class="field"><label for="wizard-note">یادداشت</label><textarea id="wizard-note" class="input" name="note">{{ old('note') }}</textarea></div></details>
        @endif
        <div class="wizard-actions"><button type="button" class="btn btn-soft" data-go="{{ $isSale ? 3 : 4 }}">برگشت</button><button type="button" class="btn btn-primary" data-review>مرور و تأیید</button></div>
    </section>
    <section class="form-card wizard-panel" data-step="{{ $isSale ? 5 : 6 }}" hidden>
        <h2 tabindex="-1">مرور نهایی</h2><p class="muted">با تأیید این مرحله، فاکتور و گردش موجودی ثبت می‌شود.</p>
        <div data-review-items></div><div class="wizard-selected" data-review-payment></div>
        <div class="wizard-actions page-primary-action"><button type="button" class="btn btn-soft" data-go="{{ $isSale ? 4 : 5 }}">برگشت و ویرایش</button><button type="submit" class="btn btn-primary">{{ $isSale ? 'ثبت قطعی فروش' : 'ثبت قطعی ورود کالا' }}</button></div>
    </section>
    <div data-payload></div>
</form>
<noscript><p class="notice notice-danger">برای ثبت مرحله‌به‌مرحله، JavaScript مرورگر باید فعال باشد.</p></noscript>
